<?php

namespace Payplug\PayplugWoocommerce\Upc;

class UhfCardDataExtractor
{
    /**
     * Reads the same `paymentMethod.{id, card.{network, code6x4}, details.{selectedBrand,
     * validityDate}}` shape carried by both the Unified API's public operation resource
     * (getOperation()) and the async webhook notification body - see the Sylius reference
     * implementation's CardDataFromPaymentMethodExtractor, which this ports.
     *
     * @param array<string, mixed> $decoded json_decode()'d operation or webhook body
     *
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}
     */
    public static function extract(array $decoded): array
    {
        $payment_method = isset($decoded['paymentMethod']) && is_array($decoded['paymentMethod']) ? $decoded['paymentMethod'] : [];
        $card = isset($payment_method['card']) && is_array($payment_method['card']) ? $payment_method['card'] : [];
        $details = isset($payment_method['details']) && is_array($payment_method['details']) ? $payment_method['details'] : [];

        $brand = '';
        if (isset($card['network']) && is_scalar($card['network'])) {
            $brand = (string) $card['network'];
        } elseif (isset($details['selectedBrand']) && is_scalar($details['selectedBrand'])) {
            $brand = (string) $details['selectedBrand'];
        }

        $last4 = '';
        if (isset($card['code6x4']) && is_scalar($card['code6x4'])) {
            $last4 = substr((string) $card['code6x4'], -4);
        }

        $exp_month = 0;
        $exp_year = 0;
        if (
            isset($details['validityDate'])
            && is_scalar($details['validityDate'])
            && 1 === preg_match('/^(\d{4})-(\d{2})$/', (string) $details['validityDate'], $matches)
        ) {
            $exp_year = (int) $matches[1];
            $exp_month = (int) $matches[2];
        }

        return [
            'brand' => strtoupper($brand),
            'last4' => $last4,
            'exp_month' => $exp_month,
            'exp_year' => $exp_year,
        ];
    }
}
