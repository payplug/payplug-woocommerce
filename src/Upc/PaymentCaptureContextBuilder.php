<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Upc\Traits\BuildsCommonFields;
use PayplugUnifiedCore\Dto\HostedFieldDto;

class PaymentCaptureContextBuilder
{
    use BuildsCommonFields;

    /**
     * Same allowlist as the client-side check (HostedFields.ALLOWED_BRANDS in
     * payplug-hosted-fields.js / ALLOWED_BRANDS in wc-payplug-hostedFields-blocks.js) -
     * re-asserted here because PayplugWoocommerce::render_upc_3ds_redirect() echoes the Unified
     * API's challenge HTML unescaped on the shop's own origin, and selectedBrand reaches that API
     * call with nothing but sanitize_text_field() applied to raw checkout POST data.
     */
    private const ALLOWED_BRANDS = ['CB', 'VISA', 'MASTERCARD'];

    public function build(\WC_Order $order, string $hfToken, string $selectedBrand, bool $saveCard): HostedFieldDto
    {
        if (!in_array(strtoupper($selectedBrand), self::ALLOWED_BRANDS, true)) {
            // Case-insensitive so an already-valid value's own casing is preserved verbatim
            // (the client sends it uppercased, but nothing here requires that).
            $selectedBrand = '';
        }

        $common = $this->build_common($order);

        $payment_method = [
            'details' => [
                'selectedBrand' => $selectedBrand,
                'fullName' => $order->get_formatted_billing_full_name(),
            ],
        ];

        if ($saveCard) {
            $payment_method['saveFutureUsage'] = true;
        }

        return new HostedFieldDto(
            $common,
            $hfToken,
            $saveCard ? 'ONE_CLICK' : null,
            $this->build_browser(),
            $this->build_customer($order),
            $payment_method
        );
    }
}
