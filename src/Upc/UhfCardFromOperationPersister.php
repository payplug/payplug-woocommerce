<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;

/**
 * Shared by Front\UpcWebhook (the async notification body) and PaymentReconciler (the
 * synchronous getOperation() fetch on the shopper's return to the order-received page, used as
 * a fallback when the webhook hasn't arrived yet) - both read the identical
 * paymentMethod.{id, card.{network, code6x4}, details.{selectedBrand, validityDate}} shape and
 * both need the exact same "was this order's save-card checkbox actually ticked" gate before
 * persisting anything. Extracted so the reconciliation fallback doesn't silently drop a
 * customer's save-card intent when it wins the race against the webhook for a 3DS-confirmed
 * payment (the webhook's own isTreated() guard would otherwise skip persistence entirely once
 * the reconciler has already marked the operation treated).
 */
class UhfCardFromOperationPersister
{
    /**
     * @param array<string, mixed> $decoded json_decode()'d operation or webhook body
     */
    public function maybe_persist(\WC_Order $order, array $decoded): void
    {
        if ('1' !== $order->get_meta('_payplug_uhf_save_card')) {
            return;
        }

        if (0 === $order->get_customer_id()) {
            return;
        }

        $alias_id = isset($decoded['paymentMethod']['id']) && is_scalar($decoded['paymentMethod']['id'])
            ? (string) $decoded['paymentMethod']['id']
            : '';

        if ('' === $alias_id) {
            (new WooCommerceLogger())->error(sprintf(
                'UPC: save-card was requested but no alias id was found for order #%s.',
                $order->get_id()
            ));

            return;
        }

        $card_data = UhfCardDataExtractor::extract($decoded);
        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';

        $persisted = (new UhfCardPersister())->persist($order->get_customer_id(), $alias_id, $mode, [], $card_data);

        if (!$persisted) {
            // Same reasoning as the missing-alias-id branch above: a sanitization rejection
            // (unexpected brand, malformed code6x4/validityDate) is just as much a reason the
            // card didn't get saved, and deserves the same log line.
            (new WooCommerceLogger())->error(sprintf(
                'UPC: save-card was requested for order #%s but the card data was rejected (%s).',
                $order->get_id(),
                wp_json_encode($card_data)
            ));
        }
    }
}
