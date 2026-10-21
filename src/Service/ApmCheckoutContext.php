<?php

namespace Payplug\PayplugWoocommerce\Service;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

/**
 * Amount (cents), currency and billing country the PPRO eligibility is evaluated against.
 */
class ApmCheckoutContext
{
    /**
     * Order being paid on the order-pay page, otherwise the current cart and customer.
     *
     * @return array{amount: int, currency: string, country: string}|null null outside a cart context (admin, REST)
     */
    public static function current(): ?array
    {
        $order_id = absint(get_query_var('order-pay'));
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order instanceof \WC_Order) {
                return self::from_order($order);
            }
        }

        if (null === WC()->cart || null === WC()->customer) {
            return null;
        }

        return [
            'amount' => (int) PayplugWoocommerceHelper::get_payplug_amount(WC()->cart->get_total('edit')),
            'currency' => (string) get_woocommerce_currency(),
            'country' => (string) WC()->customer->get_billing_country(),
        ];
    }

    /**
     * @return array{amount: int, currency: string, country: string}
     */
    public static function from_order(\WC_Order $order): array
    {
        return [
            'amount' => (int) PayplugWoocommerceHelper::get_payplug_amount($order->get_total()),
            'currency' => (string) $order->get_currency(),
            'country' => (string) $order->get_billing_country(),
        ];
    }
}
