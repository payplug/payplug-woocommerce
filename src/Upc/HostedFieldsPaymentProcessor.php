<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Gateway\PayplugCreditCard;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceConfigurationRepository;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceOrderStateMutator;
use Payplug\PayplugWoocommerce\Upc\Builders\AliasPaymentContextBuilder;
use Payplug\PayplugWoocommerce\Upc\Builders\PaymentCaptureContextBuilder;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Exceptions\ApiException;
use PayplugUnifiedCore\Exceptions\InvalidHostedFieldException;
use PayplugUnifiedCore\Exceptions\InvalidPaymentException;
use PayplugUnifiedCore\Exceptions\InvalidRefundRequestException;
use PayplugUnifiedCore\Exceptions\PaymentNotFoundException;
use PayplugUnifiedCore\Exceptions\PayplugException;
use PayplugUnifiedCore\Exceptions\RefundAmountException;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;

/**
 * The hosted_fields (UHF) half of PayplugCreditCard: payment creation through the Unified API and
 * refunds of UPC-paid orders. The gateway only routes here (embedded_mode === 'hosted_fields' for
 * payments, a bound UPC operation for refunds) and keeps the Retail API flows to itself.
 */
class HostedFieldsPaymentProcessor
{
    /**
     * Comfortably above the worst case of one attempt (10s timeout per call, see
     * Upc\Adapters\WooCommerceOAuthHttpClient/WooCommerceUnifiedApiHttpClient): OAuth token fetch + payment
     * creation, plus a reconciliation of a previous in-flight attempt (getPayment + getOperation)
     * - long enough to span one real attempt, short enough that a crashed request never locks an
     * order out for long.
     */
    private const PROCESS_PAYMENT_LOCK_TTL_SECONDS = 60;

    /**
     * How long a 3DS-pending attempt blocks a new payment on the same order (see
     * resolve_in_flight_attempt()). 3DS challenges expire well before this.
     */
    private const PENDING_ATTEMPT_WINDOW_SECONDS = 1800;

    /**
     * Same reasoning as PROCESS_PAYMENT_LOCK_TTL_SECONDS - comfortably above the OAuth token
     * fetch + refund call's own combined worst case (10s timeout each).
     */
    private const PROCESS_REFUND_LOCK_TTL_SECONDS = 30;

    /** @var PayplugCreditCard */
    private $gateway;

    public function __construct(PayplugCreditCard $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * @return array{result: string, redirect?: string}
     */
    public function process_payment(int $order_id): array
    {
        $order = wc_get_order($order_id);

        if (!$order instanceof \WC_Order) {
            PayplugGateway::log(sprintf('Order #%s not found.', $order_id), 'error');
            wc_add_notice(__('Order not found.', 'payplug'), 'error');

            return ['result' => 'failure'];
        }

        // An order that already succeeded (payment_complete()'d) or was refunded must never get a
        // second payment created against it - a double-click, or a resubmit after a slow-but-
        // successful first response, would otherwise charge the customer twice. A 3DS attempt
        // still in flight is handled once the lock is held (see resolve_in_flight_attempt()).
        if (WooCommerceOrderStateMutator::isTerminal($order->get_status())) {
            return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
        }

        // Same range check every other Payplug payment path runs (PayplugGateway::process_payment()
        // for popup/integrated, ApplePay, Oney3x) before ever calling the API. Deliberately after
        // the terminal-status short-circuit above: an order that already succeeded must still
        // redirect to the order-received page even if its amount would fail this check today
        // (e.g. the account's thresholds were tightened after the order was paid).
        $validated_amount = $this->gateway->validate_order_amount(AmountHelper::toCents((float) $order->get_total(), $order->get_currency()), $order->get_currency());

        if (is_wp_error($validated_amount)) {
            PayplugGateway::log(sprintf('Invalid amount %s for order #%s.', $order->get_total(), $order_id), 'error');
            wc_add_notice($validated_amount->get_error_message(), 'error');

            return ['result' => 'failure'];
        }

        $uhf_card_choice = isset($_POST['payplug_uhf_card_choice']) ? sanitize_text_field(wp_unslash($_POST['payplug_uhf_card_choice'])) : '';
        // The BO flag is re-checked here (not just relied on to keep the choice off the page in
        // the first place): a posted card choice with the flag off falls through to requiring
        // hf_token below, exactly like no choice was ever posted - defense in depth against a
        // forged POST.
        $use_alias = $this->gateway->save_card && '' !== $uhf_card_choice && 'other' !== $uhf_card_choice;

        $hf_token = isset($_POST['hf_token']) ? sanitize_text_field(wp_unslash($_POST['hf_token'])) : '';

        if (!$use_alias && '' === $hf_token) {
            wc_add_notice(__('payplug_hosted_fields_missing_token', 'payplug'), 'error');

            return ['result' => 'failure'];
        }

        $selected_brand = isset($_POST['hf_selected_brand']) ? sanitize_text_field(wp_unslash($_POST['hf_selected_brand'])) : '';
        $save_card = !empty($_POST['savecard']);
        // sanitize_text_field() also strips line breaks, so the name can't inject anything
        // into the payment's fullName beyond plain text.
        $cardholder = isset($_POST['hf_cardholder']) ? sanitize_text_field(wp_unslash($_POST['hf_cardholder'])) : '';
        $card_fallback = [
            'brand' => $selected_brand,
            'last4' => isset($_POST['hf_last4']) ? sanitize_text_field(wp_unslash($_POST['hf_last4'])) : '',
            'exp_month' => isset($_POST['hf_expiration_month']) ? (int) $_POST['hf_expiration_month'] : 0,
            'exp_year' => isset($_POST['hf_expiration_year']) ? (int) $_POST['hf_expiration_year'] : 0,
        ];

        // Two requests for the *same* order genuinely in flight at once (a double-click, or two
        // tabs) are always the same attempt - never a legitimate new one - so the second must
        // wait rather than create a second payment. A *sequential* retry while a previous 3DS
        // attempt may still complete is handled by resolve_in_flight_attempt() below.
        $lock = new WooCommerceLock();
        $lock_key = 'payplug_upc_process_payment_' . $order_id;

        if (!$lock->acquire($lock_key, self::PROCESS_PAYMENT_LOCK_TTL_SECONDS)) {
            wc_add_notice(__('payplug_hosted_fields_tokenization_error', 'payplug'), 'error');

            return ['result' => 'failure'];
        }

        // Re-read and re-check now that the lock is actually held: the status read above happened
        // before acquire(), so a request that was preempted for as long as another one's entire
        // API round trip could otherwise still build and send a second payment against an order
        // that has, by now, already succeeded.
        $order = wc_get_order($order_id);

        if (!$order instanceof \WC_Order) {
            PayplugGateway::log(sprintf('Order #%s not found.', $order_id), 'error');
            wc_add_notice(__('Order not found.', 'payplug'), 'error');
            $lock->release($lock_key);

            return ['result' => 'failure'];
        }

        if (WooCommerceOrderStateMutator::isTerminal($order->get_status())) {
            $lock->release($lock_key);

            return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
        }

        $in_flight = $this->resolve_in_flight_attempt($order);

        if (null !== $in_flight) {
            $lock->release($lock_key);

            return $in_flight;
        }

        try {
            if ($use_alias) {
                $card = UhfCard::find_for_customer((int) $uhf_card_choice, get_current_user_id(), $this->gateway->mode);

                if (null === $card) {
                    wc_add_notice(__('payplug_hosted_fields_tokenization_error', 'payplug'), 'error');

                    return ['result' => 'failure'];
                }

                $dto = (new AliasPaymentContextBuilder())->build($order, $card->alias_id);
                $output = (new UnifiedApiPaymentServiceFactory())->create()->createPayment($dto);

                // A one-click payment never creates a NEW alias (it pays with one that already
                // exists), so save_card is always false here regardless of whatever the client
                // posted - PaymentCaptureOutcomeApplier's own alias-persistence branch is then
                // correctly a no-op for this request.
                return (new PaymentCaptureOutcomeApplier())->apply($order, $output);
            }

            $dto = (new PaymentCaptureContextBuilder())->build($order, $hf_token, $selected_brand, $save_card, $cardholder);
            $output = (new UnifiedApiPaymentServiceFactory())->create()->createPayment($dto);

            return (new PaymentCaptureOutcomeApplier())->apply($order, $output, $save_card, $card_fallback);
        } catch (ApiException $e) {
            PayplugGateway::log(sprintf('UPC payment creation failed for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } catch (InvalidHostedFieldException $e) {
            PayplugGateway::log(sprintf('UPC payment creation rejected for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } catch (InvalidPaymentException $e) {
            PayplugGateway::log(sprintf('UPC payment creation rejected for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } catch (PayplugException $e) {
            // Catch-all for every other UPC exception (InvalidCommonFieldsException from a
            // misconfigured/empty account id, InvalidPhoneNumberException, ...) - all of them
            // share PayplugException as their base. Without this, one of their messages (internal,
            // not written for a shopper) would escape to WC_Checkout::process_checkout()'s generic
            // \Exception handler and get shown verbatim as a checkout error notice.
            PayplugGateway::log(sprintf('UPC payment creation rejected for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } catch (\LogicException $e) {
            PayplugGateway::log(sprintf('UPC payment creation misconfigured for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } finally {
            $lock->release($lock_key);
        }

        wc_add_notice(__('payplug_hosted_fields_tokenization_error', 'payplug'), 'error');

        return ['result' => 'failure'];
    }

    /**
     * Refuses a new payment while a previous 3DS attempt on the same order may still complete
     * (spec §4.6: Sylius never creates a second payment when hosted_fields_created_at /
     * alias_payment_created_at is already set). A shopper coming back from the bank (back
     * button, closed tab) before the webhook landed would otherwise be charged twice, and
     * persist_operation() would rebind the order to the second operation, orphaning the first.
     *
     * The previous attempt is first reconciled against the Unified API (throttle bypassed): if
     * it is now paid the shopper is sent to the order-received page, if it failed a new attempt
     * is allowed. Past PENDING_ATTEMPT_WINDOW_SECONDS the attempt is considered abandoned (3DS
     * challenges expire well before that), so a lost webhook plus an unreachable API can never
     * lock an order out of payment for good.
     *
     * @return array|null the process_payment() result to return, or null to create a new payment
     */
    private function resolve_in_flight_attempt(\WC_Order $order): ?array
    {
        if (PaymentOutcome::THREE_DS_PENDING !== $order->get_meta('_payplug_upc_outcome')
            || '' === (string) $order->get_meta('_payplug_upc_operation_id')) {
            return null;
        }

        $created_at = (int) $order->get_meta('_payplug_upc_created_at');

        // No timestamp means an attempt persisted before it was recorded: its age is unknown, so
        // it is treated as abandoned rather than risking a permanent block.
        if ($created_at <= 0 || time() - $created_at > self::PENDING_ATTEMPT_WINDOW_SECONDS) {
            PayplugGateway::log(sprintf('Previous UPC 3DS attempt for order #%s is older than %d seconds, allowing a new attempt.', $order->get_id(), self::PENDING_ATTEMPT_WINDOW_SECONDS));

            return null;
        }

        (new PaymentReconciler())->reconcile($order, true);

        $order = wc_get_order($order->get_id());

        if (!$order instanceof \WC_Order) {
            return null;
        }

        if (WooCommerceOrderStateMutator::isTerminal($order->get_status())) {
            return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
        }

        if (PaymentOutcome::THREE_DS_PENDING !== $order->get_meta('_payplug_upc_outcome')) {
            return null;
        }

        PayplugGateway::log(sprintf('UPC payment for order #%s refused: a previous 3DS attempt is still in progress.', $order->get_id()));
        wc_add_notice(__('payplug_hosted_fields_payment_in_progress', 'payplug'), 'error');

        return ['result' => 'failure'];
    }

    /**
     * Only called for an order bound to a UPC operation - PayplugCreditCard::process_refund()
     * keeps every other order on the Retail API.
     *
     * @param float|string|null $amount
     *
     * @return bool|\WP_Error
     */
    public function process_refund(\WC_Order $order, string $operation_id, $amount, string $reason)
    {
        $order_id = $order->get_id();

        PayplugGateway::log(sprintf('Processing UPC refund for order #%s.', $order_id));

        // No check here for whether the operation has actually reached a captured/paid state
        // (e.g. still THREE_DS_PENDING) - deliberately left to the Unified API's own validation
        // rather than an extra getPayment() round trip before every refund. Worst case is an
        // ApiException on an operation that was never payable, caught below; no data corruption.
        $configuration_repository = new WooCommerceConfigurationRepository();
        $account_id = $configuration_repository->getPublicKeyId();

        // A guard, not just a nicety: without it an unconfigured account id goes out as an empty
        // string, the API rejects it with a generic 4xx, and that lands in the ApiException catch
        // below indistinguishable from any other API failure - turning a one-line config problem
        // into a support investigation. Checked before acquiring the lock since there is nothing
        // here a concurrent request could race on.
        if ('' === $account_id) {
            PayplugGateway::log(sprintf('UPC refund misconfigured for order #%s: hosted_fields.identifier (account id) is not set.', $order_id), 'error');

            return new \WP_Error('process_refund_error', __('The transaction could not be refunded. Please try again.', 'payplug'));
        }

        // Guards concurrent refund requests for the same order (double-click, two admin tabs)
        // only - NOT a sequential retry of a request that already timed out. If createRefund()'s
        // HTTP call times out (see PROCESS_REFUND_LOCK_TTL_SECONDS) after the Unified API already
        // processed the refund, this method still returns a WP_Error, WooCommerce deletes the
        // just-created local refund record (see wc_create_refund()), and a merchant retrying from
        // the "Please try again" notice creates a second, real refund at Payplug - there is no
        // idempotency key available from createRefund() to prevent this, and no reconciliation
        // path analogous to the payment side's webhook/PaymentReconciler exists for refunds.
        $lock = new WooCommerceLock();
        $lock_key = 'payplug_upc_process_refund_' . $order_id;

        if (!$lock->acquire($lock_key, self::PROCESS_REFUND_LOCK_TTL_SECONDS)) {
            PayplugGateway::log(sprintf('Another refund request for order #%s is already in progress.', $order_id), 'error');

            return new \WP_Error('process_refund_error', __('The transaction could not be refunded. Please try again.', 'payplug'));
        }

        try {
            $amount_in_cents = null !== $amount ? AmountHelper::toCents((float) $amount, $order->get_currency()) : null;

            $response = (new UnifiedApiPaymentServiceFactory())->create()->createRefund(
                $operation_id,
                $account_id,
                (string) $order_id,
                sprintf(__('Refund for order #%s', 'payplug'), $order->get_order_number()),
                // Always null: submerchantExternalId only applies to EUR/Galitt UDV account
                // configurations, which UHF never targets (non-EUR accounts own no submerchant -
                // see the UHF technical spec). Sending one here would be for an account this
                // module never talks to.
                null,
                $amount_in_cents,
                $order->get_currency()
            );

            $refund_id = $this->extract_refund_id($response['body']);

            if (null !== $refund_id) {
                // Namespaced separately from the Retail API's own `_pr_<id>` dedupe key (see
                // PayplugResponse::process_refund()) rather than reusing it: a future UPC refund
                // notification handler dedupes against this key the same way PayplugResponse
                // dedupes against `_pr_<id>` today, but the two id spaces (Retail vs Unified API)
                // must never be conflated.
                $order->add_meta_data(sprintf('_payplug_upc_refund_%s', wc_clean($refund_id)), $refund_id, true);
                $order->save_meta_data();
            }

            // Deliberately not firing payplug_gateway_refund_created: its existing consumers
            // expect a Retail API RefundResource object (see PayplugGateway::process_refund()),
            // not this endpoint's raw {status, body} array - firing it here would hand them a
            // shape they don't expect, a behavior change for anyone already hooking it.
            $order->add_order_note($this->build_refund_note($amount_in_cents, $order->get_currency(), $reason, $refund_id));
            PayplugGateway::log(sprintf('UPC refund complete for order #%s.', $order_id));

            return true;
        } catch (RefundAmountException | InvalidRefundRequestException | PaymentNotFoundException $e) {
            PayplugGateway::log(sprintf('UPC refund rejected for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } catch (PayplugException $e) {
            // Catches ApiException and every other UPC exception not explicitly rejected above -
            // mirrors process_payment()'s own discipline against letting an internal API message
            // escape to the BO user.
            PayplugGateway::log(sprintf('UPC refund failed for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } finally {
            $lock->release($lock_key);
        }

        return new \WP_Error('process_refund_error', __('The transaction could not be refunded. Please try again.', 'payplug'));
    }

    /**
     * The refund operation's own id: operationIds[0]. Observed on staging (2026-10-05), the
     * refund response's top-level "id" is the refunded PAYMENT's id, identical for every refund
     * of the same order - using it would give two refunds the same dedupe key. The top-level id
     * remains a fallback for a response without operationIds. Returns null for a body that
     * doesn't parse or carries neither, so an unrecognized shape degrades to "no id persisted".
     *
     * Assumption, to be confirmed with the API team: operationIds[0] is THIS refund's operation.
     * Every refund response seen on staging (EUR, USD and JPY accounts) carried exactly one id.
     * If the API ever returns several, only the first is used as the dedupe key.
     */
    public function extract_refund_id(string $response_body): ?string
    {
        $data = json_decode($response_body, true);

        if (isset($data['operationIds'][0]) && is_string($data['operationIds'][0]) && '' !== $data['operationIds'][0]) {
            return $data['operationIds'][0];
        }

        return isset($data['id']) && is_string($data['id']) && '' !== $data['id'] ? $data['id'] : null;
    }

    /**
     * Extracted from process_refund() specifically to be testable without a network call - the
     * try block above can only be exercised end-to-end against a real (or real-looking) Unified
     * API, which this test suite has no way to reach.
     */
    public function build_refund_note(?int $amount_in_cents, string $currency, string $reason, ?string $refund_id = null): string
    {
        $amount_text = null !== $amount_in_cents
            ? sprintf(__('refunded %s', 'payplug'), wc_price(AmountHelper::fromCents($amount_in_cents, $currency), ['currency' => $currency]))
            : __('fully refunded', 'payplug');

        $note = null !== $refund_id
            ? sprintf(__('Refund %s: %s', 'payplug'), wc_clean($refund_id), $amount_text)
            : sprintf(__('Refund: %s', 'payplug'), $amount_text);

        if (!empty($reason)) {
            $note .= sprintf(' (%s)', esc_html($reason));
        }

        return $note;
    }
}
