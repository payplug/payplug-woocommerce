<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceOrderStateMutator;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommercePaymentRepository;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Exceptions\PaymentNotFoundException;

/**
 * The one place a final UPC outcome is applied to an order. Three paths can each bring the final
 * outcome of the same operation, possibly at the same time: the synchronous payment-creation
 * response (PaymentCaptureOutcomeApplier), the webhook (Front\UpcWebhook) and the order-received page
 * reconciliation (PaymentReconciler). They all go through process(), so whichever wins the lock
 * applies the outcome once and the others find it already treated - 'woocommerce_payment_complete'
 * never fires twice for the same operation.
 *
 * Same sequence as Sylius's treat() (spec §4.3): lock per operation id (TTL 30s), isTreated(),
 * save, apply, markTreated().
 */
class OperationOutcomeProcessor
{
    public const APPLIED = 'applied';

    public const ALREADY_TREATED = 'already_treated';

    public const LOCKED = 'locked';

    private const LOCK_TTL_SECONDS = 30;

    /**
     * @param OperationData $operation must carry the order's PRIMARY operation id: the lock key
     *                                 and the treated marker are both keyed on it, so the three
     *                                 paths contend for the same lock even when the webhook was matched
     *                                 on the alt id
     * @param callable|null $on_paid run inside the lock when the outcome is PAID, before the
     *                               operation is marked treated (saved-card persistence)
     *
     * @throws PaymentNotFoundException when the order the operation belongs to no longer exists
     *
     * @return string one of self::APPLIED, self::ALREADY_TREATED, self::LOCKED
     */
    public function process(OperationData $operation, ?callable $on_paid = null): string
    {
        $lock = new WooCommerceLock();
        $lock_key = 'payplug_upc_treat_' . $operation->operationId;

        if (!$lock->acquire($lock_key, self::LOCK_TTL_SECONDS)) {
            return self::LOCKED;
        }

        try {
            $payment_repository = new WooCommercePaymentRepository();

            if ($payment_repository->isTreated($operation->operationId)) {
                return self::ALREADY_TREATED;
            }

            $payment_repository->save($operation);
            (new WooCommerceOrderStateMutator())->apply($operation->orderId, $operation->outcome);

            if (PaymentOutcome::PAID === $operation->outcome && null !== $on_paid) {
                $on_paid();
            }

            if (PaymentOutcome::FAILED === $operation->outcome) {
                // Never marked treated: unmapped "Acceptation" execCodes (0002/0003/0004, spec
                // §4.4) fall into FAILED too, and a later PAID notification for this same
                // operation must still be able to complete the order. Logged at error level so
                // such a code is easy to spot on a payment that later succeeds.
                (new WooCommerceLogger())->error(sprintf(
                    'UPC operation "%s" failed for order #%s (execCode %s).',
                    $operation->operationId,
                    $operation->orderId,
                    $operation->execCode
                ));

                return self::APPLIED;
            }

            $payment_repository->markTreated($operation->operationId);

            return self::APPLIED;
        } finally {
            $lock->release($lock_key);
        }
    }
}
