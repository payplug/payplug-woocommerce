<?php

namespace Payplug\PayplugWoocommerce\Upc\Builders;

use PayplugUnifiedCore\Dto\HostedFieldDto;

class PaymentCaptureContextBuilder
{
    use BuildsCommonFields;

    /**
     * Same allowlist as the client-side check (HostedFields.ALLOWED_BRANDS in
     * payplug-hosted-fields.js / ALLOWED_BRANDS in wc-payplug-hostedFields-blocks.js) -
     * re-asserted here because Front\UpcCheckoutReturn::render_upc_3ds_redirect() echoes the Unified
     * API's challenge HTML unescaped on the shop's own origin, and selectedBrand reaches that API
     * call with nothing but sanitize_text_field() applied to raw checkout POST data.
     *
     * Keep the three lists in sync.
     *
     * No CB: it is only accepted in EUR, and UHF only ever handles non-EUR carts (a EUR shop is
     * never in hosted_fields mode, see PayplugCreditCard's constructor).
     */
    private const ALLOWED_BRANDS = ['VISA', 'MASTERCARD'];

    public static function is_allowed_brand(string $selectedBrand): bool
    {
        // Case-insensitive so an already-valid value's own casing is preserved verbatim
        // (the client sends it uppercased, but nothing here requires that).
        return in_array(strtoupper($selectedBrand), self::ALLOWED_BRANDS, true);
    }

    /**
     * @param string $cardholder the name typed in the card form's cardholder field - the billing
     *                           name is sent instead when it is empty (e.g. a client that predates the field)
     */
    public function build(\WC_Order $order, string $hfToken, string $selectedBrand, bool $saveCard, string $cardholder = ''): HostedFieldDto
    {
        if (!self::is_allowed_brand($selectedBrand)) {
            // Defense in depth: HostedFieldsPaymentProcessor already refuses a disallowed brand
            // before calling build(), but this builder must stay safe for any other caller.
            $selectedBrand = '';
        }

        $common = $this->build_common($order);

        $payment_method = [
            'details' => [
                'selectedBrand' => $selectedBrand,
                'fullName' => '' !== trim($cardholder) ? trim($cardholder) : $order->get_formatted_billing_full_name(),
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
