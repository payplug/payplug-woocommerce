<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use PayplugUnifiedCore\Exceptions\InvalidCurrencyException;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;

/**
 * Single entry point for converting an order total to the Unified API's minor units. UPC's
 * AmountHelper::toCents() throws InvalidCurrencyException for a currency it can't convert (a
 * 3-decimal one such as KWD, or a malformed code): every UHF path goes through here, so that
 * exception never escapes to the checkout, the webhook or the thank-you page.
 */
final class UpcOrderAmount
{
    /**
     * @var array<string, bool> per-request cache, keyed by currency code
     */
    private static $supported_currencies = [];

    public static function is_supported_currency(string $currency): bool
    {
        if (!isset(self::$supported_currencies[$currency])) {
            try {
                AmountHelper::toCents(1.0, $currency);
                self::$supported_currencies[$currency] = true;
            } catch (InvalidCurrencyException $e) {
                self::$supported_currencies[$currency] = false;
            }
        }

        return self::$supported_currencies[$currency];
    }

    /**
     * @return int|null the order total in minor units, or null (logged) when its currency can't
     *                  be converted - each caller turns that into its own controlled failure
     */
    public static function total(\WC_Order $order): ?int
    {
        try {
            return AmountHelper::toCents((float) $order->get_total(), $order->get_currency());
        } catch (InvalidCurrencyException $e) {
            (new WooCommerceLogger())->error(sprintf(
                'UPC amount conversion failed for order #%s (currency "%s"): %s',
                $order->get_id(),
                $order->get_currency(),
                $e->getMessage()
            ));

            return null;
        }
    }
}
