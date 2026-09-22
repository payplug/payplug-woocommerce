<?php

namespace Payplug\PayplugWoocommerce\Upc\Traits;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceConfigurationRepository;
use PayplugUnifiedCore\Dto\AddressDto;
use PayplugUnifiedCore\Dto\BillingDto;
use PayplugUnifiedCore\Dto\BrowserDto;
use PayplugUnifiedCore\Dto\CommonFieldsDto;
use PayplugUnifiedCore\Dto\ContactDto;
use PayplugUnifiedCore\Dto\CustomerDto;
use PayplugUnifiedCore\Dto\ShippingDto;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;

/**
 * Shared by PaymentCaptureContextBuilder (HostedFieldDto) and AliasPaymentContextBuilder
 * (PaymentDto) - both build the identical account/amount/currency/orderId/successUrl/cancelUrl/
 * notificationUrl/billing/shipping/browser/customer context, differing only in their own
 * payment-method-specific fields (hfToken vs. aliasId).
 */
trait BuildsCommonFields
{
    private function build_common(\WC_Order $order): CommonFieldsDto
    {
        $configuration_repository = new WooCommerceConfigurationRepository();

        $common = new CommonFieldsDto(
            $configuration_repository->getPublicKeyId(),
            AmountHelper::toCents((float) $order->get_total()),
            $order->get_currency(),
            (string) $order->get_id(),
            ''
        );
        $common->description = $this->build_description($order);
        $common->successUrl = $order->get_checkout_order_received_url();
        // The order-received page would otherwise show "Thank you, your order has been received"
        // for a payment that was never completed - checkout_payment_url() sends an abandoned 3DS
        // challenge back to the order-pay page instead.
        $common->cancelUrl = add_query_arg('payplug_upc_cancelled', '1', $order->get_checkout_payment_url());
        // Per the UHF spec, the Unified API's own notification mechanism ignores this field
        // entirely - delivery is exclusively via the account/realm-scoped "Receiver". Sent anyway
        // to satisfy the DTO's contract.
        $common->notificationUrl = home_url('/payplug/v2/ipn');
        $common->billing = $this->build_billing($order);
        $common->shipping = $this->build_shipping($order);

        return $common;
    }

    private function build_description(\WC_Order $order): string
    {
        $items = $order->get_items();
        $first_item = reset($items);

        return $first_item instanceof \WC_Order_Item_Product
            ? (string) $first_item->get_name()
            : sprintf(__('Order #%s', 'payplug'), $order->get_order_number());
    }

    private function build_billing(\WC_Order $order): BillingDto
    {
        return new BillingDto(
            new AddressDto(
                $order->get_billing_address_1(),
                $order->get_billing_city(),
                $order->get_billing_country(),
                $order->get_billing_state(),
                $order->get_billing_postcode()
            ),
            new ContactDto(
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                $order->get_billing_phone()
            )
        );
    }

    private function build_shipping(\WC_Order $order): ShippingDto
    {
        return new ShippingDto(
            new AddressDto(
                $order->get_shipping_address_1(),
                $order->get_shipping_city(),
                $order->get_shipping_country(),
                $order->get_shipping_state(),
                $order->get_shipping_postcode()
            ),
            new ContactDto(
                $order->get_shipping_first_name(),
                $order->get_shipping_last_name()
            ),
            $order->get_billing_email()
        );
    }

    private function build_browser(): BrowserDto
    {
        return new BrowserDto(
            isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
            isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : ''
        );
    }

    private function build_customer(\WC_Order $order): ?CustomerDto
    {
        if (0 === $order->get_customer_id()) {
            return null;
        }

        return new CustomerDto((string) $order->get_customer_id(), $order->get_billing_email());
    }
}
