<?php

namespace Payplug\PayplugWoocommerce\Admin;

use Payplug\PayplugWoocommerce\Service\ApmAmountsSave;
use Payplug\PayplugWoocommerce\Service\ApmCountriesSave;
use Payplug\PayplugWoocommerce\Service\ApmEligibility;

class Validator
{
    public static function enabled($value)
    {
        if ($value == 1) {
            return true;
        }

        if ($value == 0) {
            return false;
        }

        http_response_code(400);
        wp_send_json_error(['error' => 'enabled is missing']);
    }

    public static function mode($value)
    {
        if ($value == 1) {
            return false;
        }

        if ($value == 0) {
            return true;
        }

        http_response_code(400);
        wp_send_json_error(['error' => 'mode is missing']);
    }

    public static function payment_method($value)
    {
        if (!empty($value) && in_array($value, ['redirect', 'popup', 'integrated'])) {
            return true;
        }

        http_response_code(400);
        wp_send_json_error(['error' => 'payment_method is missing']);
    }

    public static function debug($value)
    {
        if ($value == 1) {
            return true;
        }

        if ($value == 0) {
            return false;
        }

        http_response_code(400);
        wp_send_json_error(['error' => 'mode is missing']);

        return false;
    }

    public static function save_card($value)
    {
        if ($value == 1) {
            return true;
        }

        if ($value == 0) {
            return false;
        }

        http_response_code(400);
        wp_send_json_error(['error' => 'oneclick is missing']);
    }

    public static function genericPaymentGateway($value, $payment, $test_mode)
    {
        if ($test_mode) {
            return false;
        }

        if ($value == 1 || $value) {
            return true;
        }

        return false;
    }

    /**
     * prevent saving when neither cart and checkout is enabled
     *
     * @param $cart
     * @param $product
     * @param $checkout
     *
     * @return true
     */
    public static function applePayPaymentGatewayOptions($apple_pay, $cart, $product, $checkout, $carriers)
    {
        if ($apple_pay === false) {
            return true;
        }

        if ($cart === false && $product === false && $checkout === false) {
            http_response_code(200);

            $arr = [
                'msg' => __('applepay_cart_checkout_option_validation', 'payplug'),
                'class' => 'error',
                'title' => __('applepay_cart_checkout_option_validation_title', 'payplug'),
                'close' => __('payplug_ok', 'payplug'),
            ];

            wp_send_json_error($arr);
        }

        if (($cart === true || $product === true) && empty($carriers)) {
            http_response_code(200);

            $arr = [
                'msg' => __('applepay_cart_carrier_enabled', 'payplug'),
                'class' => 'error',
                'title' => __('applepay_cart_checkout_option_validation_title', 'payplug'),
                'close' => __('payplug_ok', 'payplug'),
            ];

            wp_send_json_error($arr);
        }

        return true;
    }

    public static function oney($value)
    {
        if ($value == 1) {
            return true;
        }

        return false;

        http_response_code(400);
        wp_send_json_error(['error' => 'oney is missing']);
    }

    public static function oney_type($value)
    {
        if (isset($value) && !empty($value)) {
            if (in_array($value, ['with_fees', 'without_fees'])) {
                return true;
            }
        }

        return false;
    }

    public static function oney_thresholds($min, $max)
    {
        $rmin = 100;
        $rmax = 3000;
        if ($min > 99 && $min < $max) {
            $rmin = $min;
        }

        if ($max <= 3000 && $max > $min) {
            $rmax = $max;
        }

        return ['min' => $rmin, 'max' => $rmax];
    }

    /**
     * Validate the min/max amounts posted for a PPRO payment method; an invalid value aborts the
     * whole save with an explicit message.
     *
     * @param mixed $min euros, null when not posted
     * @param mixed $max euros, null when not posted
     * @param int $api_min cents
     * @param int $api_max cents
     *
     * @return array|null cents to store ({} = none), null when the amounts were not posted
     */
    public static function apm_amounts(string $apm, $min, $max, int $api_min, int $api_max)
    {
        $result = ApmAmountsSave::parse($min, $max, $api_min, $api_max);
        if ($result['skip']) {
            return null;
        }

        if (null !== $result['error']) {
            $brand = ApmEligibility::brand($apm);
            switch ($result['error']) {
                case 'out_of_bounds':
                    $message = sprintf(__('payplug_apm_amount_out_of_bounds_error', 'payplug'), $brand, ApmAmountsSave::euros($api_min), ApmAmountsSave::euros($api_max));
                    break;
                case 'max_gt_min':
                    $message = sprintf(__('payplug_apm_amount_max_gt_min_error', 'payplug'), $brand);
                    break;
                default:
                    $message = sprintf(__('payplug_apm_amount_invalid_error', 'payplug'), $brand);
                    break;
            }
            self::apm_error($message);
        }

        return $result['store'];
    }

    /**
     * Validate the billing countries posted for a PPRO payment method; a country that cannot be
     * proposed aborts the whole save.
     *
     * @param mixed $submitted posted <apm>_countries
     * @param string[]|null $stored null = no restriction stored
     * @param string[]|null $api_countries null = ALL
     * @param string[] $shop_countries
     *
     * @return string|null value to store ('' = no restriction), null to keep the stored one
     */
    public static function apm_countries(string $apm, $submitted, ?array $stored, ?array $api_countries, array $shop_countries)
    {
        $proposable = ApmCountriesSave::proposable($api_countries, $shop_countries);
        $result = is_array($submitted)
            ? ApmCountriesSave::normalise($stored, $submitted, $proposable, $api_countries, $shop_countries)
            : ['skip' => false, 'error' => 'country_invalid', 'value' => null];

        if (null !== $result['error']) {
            self::apm_error(sprintf(__('payplug_apm_country_invalid_error', 'payplug'), ApmEligibility::brand($apm)));
        }
        if ($result['skip']) {
            return null;
        }

        return null === $result['value'] ? '' : json_encode($result['value']);
    }

    private static function apm_error(string $message): void
    {
        http_response_code(200);
        wp_send_json_error([
            'msg' => $message,
            'class' => 'error',
            'title' => null,
            'close' => __('payplug_ok', 'payplug'),
        ]);
    }

    public static function oney_product_animation($status)
    {
        if ($status) {
            return true;
        } else {
            return false;
        }
    }
}
