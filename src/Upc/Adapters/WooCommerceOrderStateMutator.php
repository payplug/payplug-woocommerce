<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\IOrderStateMutator;
use PayplugUnifiedCore\DataValues\PaymentOutcome;

class WooCommerceOrderStateMutator implements IOrderStateMutator
{
    /**
     * Deliberately excludes 'failed' and 'cancelled': WooCommerce treats both as payable again
     * (WC_Order::needs_payment() covers 'failed'; a 'cancelled' order is commonly revived by a
     * stock-hold timer's own late arrival racing a real payment). A retried checkout binds a
     * fresh operation id to the order before this runs (PaymentCaptureOutcomeApplier::persist_operation()),
     * so a genuine new PAID outcome on a 'failed'/'cancelled' order must still be allowed through -
     * only a truly finished order (paid, fulfilled, or refunded) must never be re-mutated.
     */
    private const TERMINAL_STATUSES = ['processing', 'completed', 'refunded'];

    /**
     * PaymentOutcome::PAID's 'processing' entry is descriptive only - apply() below never uses it
     * to set the status directly, it goes through WC_Order::payment_complete() instead, which
     * picks 'processing' vs 'completed' itself. The entry stays so the isset() guard in apply()
     * still recognizes PAID as a mapped (not silently ignored) outcome.
     */
    private const OUTCOME_TO_STATUS = [
        PaymentOutcome::PAID => 'processing',
        PaymentOutcome::CAPTURE_REQUIRED => 'on-hold',
        PaymentOutcome::AUTHORIZED => 'on-hold',
        PaymentOutcome::REFUNDED => 'refunded',
        PaymentOutcome::FAILED => 'failed',
    ];

    public function apply(string $orderId, string $outcome): void
    {
        if (!isset(self::OUTCOME_TO_STATUS[$outcome])) {
            // PaymentOutcome::THREE_DS_PENDING, or anything unmapped: no transition, same as
            // Sylius's own SyliusOrderStateMutator "default => null" case.
            return;
        }

        $order = wc_get_order((int) $orderId);

        if (!$order instanceof \WC_Order) {
            return;
        }

        if (in_array($order->get_status(), self::TERMINAL_STATUSES, true)) {
            // WooCommerce has no state-machine "can I transition?" guard the way Sylius does -
            // this is the manual equivalent, so a synchronous outcome and a later webhook
            // applying the same (or a stale) outcome can't fight each other or regress a
            // finished order.
            return;
        }

        if (PaymentOutcome::PAID === $outcome) {
            // payment_complete() (unlike a bare update_status()) sets date_paid and the
            // transaction id, fires 'woocommerce_payment_complete' (Subscriptions, Bookings,
            // accounting connectors all hook it), and auto-completes virtual/downloadable-only
            // orders instead of parking them in 'processing'. The operation id is already
            // persisted as order meta by WooCommercePaymentRepository::save(), called just before
            // this method on every path (synchronous checkout and webhook alike).
            $order->payment_complete((string) $order->get_meta('_payplug_upc_operation_id'));

            return;
        }

        $order->update_status(self::OUTCOME_TO_STATUS[$outcome]);
    }
}
