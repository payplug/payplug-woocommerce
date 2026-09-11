<?php

namespace Payplug\PayplugWoocommerce\Front;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceConfigurationRepository;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceOrderStateMutator;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommercePaymentRepository;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Exceptions\InvalidNotificationException;
use PayplugUnifiedCore\Exceptions\InvalidOperationDataException;
use PayplugUnifiedCore\Exceptions\PaymentNotFoundException;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;
use PayplugUnifiedCore\Utilities\Helpers\WebhookNotificationHelper;

class UpcWebhook
{
    private const LOCK_TTL_SECONDS = 30;

    /**
     * Fixed path Payplug's Unified API notifier actually delivers to - confirmed against the
     * Sylius reference implementation (Controller/UnifiedApiIpnAction.php): the Unified API's
     * notification mechanism is an account/realm-scoped "Receiver" configured once in Cockpit
     * with a single, fixed URL, not a per-merchant/per-payment-method one - a wc_ajax_*-style
     * dynamic query-string endpoint (this class's original approach) is never called, since
     * nothing about that URL is a fixed, predictable path. No leading/trailing slash: spliced into
     * the rewrite pattern and into home_url() below.
     */
    private const ROUTE = 'payplug/v2/ipn';

    private const QUERY_VAR = 'payplug_upc_ipn';

    /**
     * Bumped whenever ROUTE changes shape. register_activation_hook() cannot safely flush this
     * rule in: activation runs inside plugin_sandbox_scrape()'s include, in the same request
     * where 'plugins_loaded' (and so this class's own composer autoload, wired to it in
     * payplug.php) has already fired for the *previous* set of active plugins - a class method
     * used as an activation callback is not guaranteed to be autoloadable yet, and empirically
     * (2026-09-10) isn't. Comparing this option on every 'init' instead means the flush reliably
     * happens on the very next real page load after activation, an update, or a version bump -
     * still much cheaper than flushing unconditionally on every request.
     */
    private const ROUTE_VERSION = '1';

    private const ROUTE_VERSION_OPTION = 'payplug_upc_ipn_route_version';

    public function __construct()
    {
        add_action('init', [$this, 'register_rewrite_rule']);
        add_filter('query_vars', [$this, 'register_query_var']);
        add_action('template_redirect', [$this, 'maybe_handle_request']);
    }

    /**
     * Registered on every 'init'. Declaring the rule alone only affects memory, not the
     * persisted 'rewrite_rules' option that request routing actually reads - the ROUTE_VERSION
     * check right below is what makes it take effect, by flushing once after activation, an
     * update, or a version bump. 'top' priority so a permalink structure that could otherwise
     * swallow this path (e.g. a page/post literally named "payplug") never wins over it.
     */
    public function register_rewrite_rule(): void
    {
        add_rewrite_rule('^' . self::ROUTE . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top');

        if (self::ROUTE_VERSION !== get_option(self::ROUTE_VERSION_OPTION)) {
            // Deferred to 'wp_loaded' rather than flushed right here: flush_rewrite_rules()
            // persists whatever rules are registered *at the moment it runs* into the
            // 'rewrite_rules' option - flushing inline on 'init' (priority 10, same as this
            // method's own registration) would silently drop any other plugin's rule added at a
            // later 'init' priority, or on 'wp_loaded' itself, until someone manually re-saves
            // Settings > Permalinks. Priority 99 gives every well-behaved plugin's own
            // registration a chance to run first.
            add_action('wp_loaded', [$this, 'flush_route_once'], 99);
        }
    }

    public function flush_route_once(): void
    {
        flush_rewrite_rules();
        update_option(self::ROUTE_VERSION_OPTION, self::ROUTE_VERSION);

        if ('' === (string) get_option('permalink_structure')) {
            // add_rewrite_rule() is a no-op for request routing on "Plain" permalinks: there's
            // no .htaccess/nginx rule sending an arbitrary path through index.php, so Apache/
            // nginx 404s /payplug/v2/ipn before WordPress ever loads - no 3DS/async payment can
            // ever be confirmed, silently. Logged once here (gated on the same version bump as
            // the flush above) rather than on every request.
            (new WooCommerceLogger())->error(
                'Payplug UPC IPN route (/payplug/v2/ipn) requires "pretty" permalinks - this '
                . 'site is on "Plain" permalinks, so Payplug\'s 3DS/async payment notifications '
                . 'will never reach it. Visit Settings > Permalinks and choose any structure '
                . 'other than "Plain".'
            );
        }
    }

    /**
     * @param string[] $vars
     *
     * @return string[]
     */
    public function register_query_var(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    /**
     * Real entry point - dispatches on the fixed /payplug/v2/ipn path (both GET and POST, matching
     * the Sylius reference route's own accepted methods) once WordPress has resolved the query var
     * above. Reads the raw request once and delegates to receive_notification(), which is
     * unit-testable on its own. The exit call below makes this method itself impossible to unit
     * test directly (a PHP process exit can't be caught) - matches_route() carries the one part of
     * this method worth testing in isolation.
     */
    public function maybe_handle_request(): void
    {
        if (!$this->matches_route()) {
            return;
        }

        $status = $this->receive_notification((string) file_get_contents('php://input'));
        http_response_code($status);
        exit;
    }

    public function matches_route(): bool
    {
        return '1' === (string) get_query_var(self::QUERY_VAR);
    }

    /**
     * @return int the HTTP status to answer the notification with - anything other than a 2xx
     *             makes Payplug's own notifier retry the delivery later, which is what a
     *             genuinely recoverable failure (unknown order, an unbound operation that hasn't
     *             raced persist_operation() yet, a persistence error) needs, since this
     *             notification is the only signal that can ever resolve a 3DS-pending order.
     *             Amount mismatch and an unbound operation on an already-terminal order are
     *             never recoverable - both return a permanent status (400/200) instead
     */
    public function receive_notification(string $raw_body): int
    {
        $logger = new WooCommerceLogger();
        $configuration_repository = new WooCommerceConfigurationRepository();
        $expected_header = (string) ($configuration_repository->get('payplug_webhook_authorization_header') ?? '');

        try {
            $operation_data = WebhookNotificationHelper::parse($this->request_headers(), $raw_body, $expected_header);
        } catch (InvalidNotificationException $e) {
            $logger->error(sprintf('UPC webhook notification rejected: %s', $e->getMessage()));

            return 400;
        }

        if (PaymentOutcome::THREE_DS_PENDING === $operation_data->outcome) {
            // No new information yet - must not touch isTreated/markTreated, or the later, final
            // notification for this same operation would be permanently blocked from applying.
            return 200;
        }

        $order = wc_get_order((int) $operation_data->orderId);

        if (!$order instanceof \WC_Order) {
            $logger->error(sprintf('UPC webhook notification references unknown order "%s".', $operation_data->orderId));

            // 409, not 400: this notification can legitimately arrive before the order even
            // exists from this plugin's perspective in a genuine race, and 400 reads as
            // permanent to most notifiers - Payplug must keep retrying.
            return 409;
        }

        // The order must already be bound to this exact operation by the synchronous payment
        // creation (PaymentCaptureOutcomeApplier persists the operation id in every branch). This
        // is what makes the endpoint safe despite being unauthenticated whenever no webhook
        // secret is configured: an order id and its total are both guessable by anyone, a
        // Unified API operation id is not. Fail closed - no stored id, or a different one, and
        // nothing is mutated.
        //
        // Two identities are accepted because a payment and its operations are distinct resources
        // in the Unified API: the payment-creation response's top-level "id" and, when present,
        // its operationIds[0] (see PaymentCaptureOutcomeApplier::persist_operation()). Which one
        // the notifier sends cannot be confirmed without a real Unified API environment. Both are
        // API-issued and unguessable, so accepting either widens nothing an attacker can reach.
        // Both comparisons are evaluated unconditionally, so the check's cost does not reveal
        // which field a guess matched.
        $stored_operation_id = (string) $order->get_meta('_payplug_upc_operation_id');
        $stored_operation_id_alt = (string) $order->get_meta('_payplug_upc_operation_id_alt');

        $matches_primary = '' !== $stored_operation_id && hash_equals($stored_operation_id, $operation_data->operationId);
        $matches_alt = '' !== $stored_operation_id_alt && hash_equals($stored_operation_id_alt, $operation_data->operationId);

        if (!$matches_primary && !$matches_alt) {
            $logger->error(sprintf('UPC webhook notification operation "%s" is not the operation bound to order #%s.', $operation_data->operationId, $order->get_id()));

            if (WooCommerceOrderStateMutator::isTerminal($order->get_status())) {
                // The order already reached a final state through a different (or since-
                // superseded) operation - this notification's own operation id can never bind to
                // anything now, so it isn't racing persist_operation(), it's simply dead. 200
                // acknowledges it so Payplug's notifier stops retrying a delivery that can never
                // succeed, rather than 409 (which would otherwise retry it for its whole window).
                return 200;
            }

            // 409, not 400: the notification can race PaymentCaptureOutcomeApplier::persist_operation()
            // committing the binding - Payplug must retry rather than give up permanently.
            return 409;
        }

        if (AmountHelper::toCents((float) $order->get_total()) !== $operation_data->amount) {
            $logger->error(sprintf('UPC webhook notification amount mismatch for order #%s.', $order->get_id()));

            return 400;
        }

        // Keyed on the order's primary operation id - always set by persist_operation() - rather
        // than the id this exact notification carries, so a delivery bound to the alt id and
        // PaymentReconciler::apply_reconciled_state() (which only ever knows the primary id)
        // contend for the same lock instead of two different ones.
        $lock = new WooCommerceLock();
        $lock_key = 'payplug_upc_treat_' . $stored_operation_id;

        if (!$lock->acquire($lock_key, self::LOCK_TTL_SECONDS)) {
            // Contended: another delivery (a Payplug retry) is already handling this operation.
            return 200;
        }

        try {
            $payment_repository = new WooCommercePaymentRepository();

            if ($payment_repository->isTreated($operation_data->operationId)) {
                return 200;
            }

            // Persisted with $stored_operation_id, never $operation_data->operationId directly:
            // on an alt-only match, the latter is the alt id, and save() would otherwise
            // overwrite the order's true primary operation id with it - contradicting
            // PaymentCaptureOutcomeApplier::persist_operation()'s own guarantee that the alt id
            // "never becomes the order's primary operation binding".
            $payment_repository->save(new OperationData(
                $stored_operation_id,
                $operation_data->execCode,
                $operation_data->outcome,
                $operation_data->amount,
                $operation_data->orderId
            ));
            (new WooCommerceOrderStateMutator())->apply($operation_data->orderId, $operation_data->outcome);
            $payment_repository->markTreated($operation_data->operationId);

            return 200;
        } catch (InvalidOperationDataException $e) {
            // Not expected in practice - $stored_operation_id is non-empty here (guaranteed by
            // the matches_primary/matches_alt check above) and every other field comes from
            // $operation_data, already validated by WebhookNotificationHelper::parse(). Guarded
            // anyway, the same way PaymentCaptureOutcomeApplier::persist_operation() and
            // PaymentReconciler::apply_reconciled_state() both guard their own OperationData
            // construction - this endpoint must not fatal on an assumption that turns out wrong.
            $logger->error(sprintf('UPC webhook built invalid operation data for order #%s: %s', $order->get_id(), $e->getMessage()));

            return 500;
        } catch (PaymentNotFoundException $e) {
            $logger->error(sprintf('UPC webhook could not persist operation "%s": %s', $operation_data->operationId, $e->getMessage()));

            return 500;
        } finally {
            $lock->release($lock_key);
        }
    }

    /**
     * @return array<string, string>
     */
    private function request_headers(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $header_name = str_replace('_', '-', substr($key, 5));
                $headers[$header_name] = (string) $value;
            }
        }

        // Apache CGI/FastCGI (several managed hosts included) strips Authorization from
        // $_SERVER['HTTP_AUTHORIZATION'] entirely, leaving it only in REDIRECT_HTTP_AUTHORIZATION
        // - the exact same fallback WordPress core's own
        // wp_populate_basic_auth_from_authorization_header() uses. Without it, a merchant who
        // *does* configure a webhook secret would have every single delivery rejected as missing
        // the header, on any such host.
        if (!isset($headers['AUTHORIZATION']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['AUTHORIZATION'] = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $headers;
    }
}
