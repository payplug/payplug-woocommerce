<?php

namespace Payplug\PayplugWoocommerce\Admin\Vue;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Service\ApmEligibility;
use Payplug\PayplugWoocommerce\Service\ApmSettings;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;

class PaymentMethods
{
    use ServiceGetter;

    private $configuration;

    public function __construct($options)
    {
        $this->configuration = $this->get_service('configuration');
    }

    /**
     * @param $active
     *
     * @return array
     */
    public function payment_method_standard()
    {
        $method = [
            'redirect' => false,
            'popup' => false,
            'integrated' => false,
        ];

        $embedded_mode = $this->configuration->get_option('payment_methods.configuration.payplug.embedded_mode');
        $method[$embedded_mode] = true;

        $active = $this->configuration->get_option('payment_methods.configuration.payplug.active');

        return [
            'type' => 'payment_method',
            'name' => 'standard',
            'title' => __('payplug_section_standard_payment_title', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/standard.svg'),
            'checked' => $active,
            'available_test_mode' => true,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_standard_payment_description', 'payplug'),
                    'advanced_options' => __('payplug_section_standard_payment_advanced_options_label', 'payplug'),
                ],
                'sandbox' => [
                    'description' => __('payplug_section_standard_payment_description', 'payplug'),
                    'advanced_options' => __('payplug_section_standard_payment_advanced_options_label', 'payplug'),
                ],
            ],
            'options' => [
                $this->title_field(),
                $this->description_field(),
                $this->embeded_option($method),
                $this->standard_warning_message(),
                $this->one_click_option(),
            ],
        ];
    }

    private function description_field()
    {
        $description = $this->configuration->get_option('payment_methods.configuration.payplug.description');

        return [
            'type' => 'payment_option',
            'sub_type' => 'input',
            'name' => 'standard_payment_description',
            'title' => __('payplug_standard_payment_description_title', 'payplug'),
            'value' => $description,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_standard_payment_description_description', 'payplug'),
                    'placeholder' => __('payplug_standard_payment_description_placeholder', 'payplug'),
                ],
                'sandbox' => [
                    'description' => __('payplug_standard_payment_description_description', 'payplug'),
                    'placeholder' => __('payplug_standard_payment_description_placeholder', 'payplug'),
                ],
            ],
        ];
    }

    private function title_field()
    {
        $title = $this->configuration->get_option('payment_methods.configuration.payplug.title');
        $title = $title ? $title : __('payplug_standard_payment_title_placeholder', 'payplug');

        return [
            'type' => 'payment_option',
            'sub_type' => 'input',
            'name' => 'standard_payment_title',
            'title' => __('payplug_standard_payment_title_title', 'payplug'),
            'value' => $title,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_standard_payment_title_description', 'payplug'),
                    'placeholder' => __('payplug_standard_payment_title_placeholder', 'payplug'),
                ],
                'sandbox' => [
                    'description' => __('payplug_standard_payment_title_description', 'payplug'),
                    'placeholder' => __('payplug_standard_payment_title_placeholder', 'payplug'),
                ],
            ],
        ];
    }

    /**
     * @param $active
     *
     * @return array|bool[]|false[]
     */
    public function standard_warning_message($active = null)
    {
        $arr = [
            'type' => 'warning_message',
            'sub_type' => 'warning',
            'name' => 'warning_message',
            'payment_method' => 'integrated',
            'description' => __('payplug_section_standard_ip_warning', 'payplug'),
        ];

        return $arr;
    }

    /**
     * @param $active
     *
     * @return array|bool[]|false[]
     */
    public function one_click_option($active = null)
    {
        $option = [
            'checked' => $this->configuration->get_option('payment_methods.configuration.payplug.save_card'),
            'type' => 'payment_option',
            'sub_type' => 'switch',
            'name' => 'one_click',
            'title' => __('payplug_section_one_click_option_title', 'payplug'),
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_one_click_option_description', 'payplug'),
                    'link_know_more' => Component::link(
                        __('payplug_know_more_label', 'payplug'),
                        'https://support.payplug.com/hc/en-gb/articles/4409698334098',
                        '_blank'
                    ),
                ],
                'sandbox' => [
                    'description' => __('payplug_section_one_click_option_description', 'payplug'),
                    'link_know_more' => Component::link(
                        __('payplug_know_more_label', 'payplug'),
                        'https://support.payplug.com/hc/en-gb/articles/4409698334098',
                        '_blank'
                    ),
                ],
            ],
        ];

        return $option;
    }

    /**
     * @return array
     */
    public function embeded_option($method)
    {
        $options = [
            [
                'name' => 'payplug_embedded',
                'label' => __('payplug_section_standard_payment_option_integrated_label', 'payplug'),
                'value' => 'integrated',
                'checked' => $method['integrated'],
            ],
            [
                'name' => 'payplug_embedded',
                'label' => __('payplug_section_standard_payment_option_popup_label', 'payplug'),
                'value' => 'popup',
                'checked' => $method['popup'],
            ],
            [
                'name' => 'payplug_embedded',
                'label' => __('payplug_section_standard_payment_option_redirected_label', 'payplug'),
                'value' => 'redirect',
                'checked' => $method['redirect'],
            ],
        ];

        return [
            'type' => 'payment_option',
            'sub_type' => 'IOptions',
            'name' => 'embeded',
            'title' => __('payplug_section_standard_payment_option_title', 'payplug'),
            'descriptions' => [
                'live' => [
                    'description_redirect' => __('payplug_section_standard_payment_redirected_description', 'payplug'),
                    'description_popup' => __('payplug_section_standard_payment_popup_description', 'payplug'),
                    'description_integrated' => __('payplug_section_standard_payment_integrated_description', 'payplug'),
                    'link_know_more' => Component::link(
                        __('payplug_know_more_label', 'payplug'),
                        __('payplug_embeded_option_url', 'payplug'),
                        '_blank'
                    ),
                ],
                'sandbox' => [
                    'description_redirect' => __('payplug_section_standard_payment_redirected_description', 'payplug'),
                    'description_popup' => __('payplug_section_standard_payment_popup_description', 'payplug'),
                    'description_integrated' => __('payplug_section_standard_payment_integrated_description', 'payplug'),
                    'link_know_more' => Component::link(
                        __('payplug_know_more_label', 'payplug'),
                        __('payplug_embeded_option_url', 'payplug'),
                        '_blank'
                    ),
                ],
            ],
            'options' => $options,
        ];
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_applepay($active, $options, $carriers = [])
    {
        $display = json_decode($options['payment_methods']['configuration']['apple_pay']['display'], true);
        $checkout = (bool) $display['checkout'];
        $cart = (bool) $display['cart'];
        $product = (bool) $display['product'];

        if ($active === true && !$checkout && !$cart) {
            $checkout = true;
        }

        return [
            'type' => 'payment_method',
            'name' => 'applepay',
            'title' => __('payplug_section_applepay_payment_title', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/applepay.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_applepay_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_applepay_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_applepay_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_applepay_more_url', 'payplug'), '_blank'),
                ],
            ],
            'options' =>
                [
                    [
                        'type' => 'payment_option',
                        'sub_type' => 'IOptions',
                        'name' => 'applepay_display',
                        'title' => __('applepay_display_choice_title', 'payplug'),
                        'options' =>
                            [
                                [
                                    'name' => 'applepay_checkout',
                                    'title' => __('applepay_display_checkout', 'payplug'),
                                    'image_url' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/cart/applepay_checkout.svg'),
                                    'label' => __('applepay_display_checkout', 'payplug'),
                                    'value' => 'checkout',
                                    'switch' => true,
                                    'checked' => $checkout || ($active && !$cart),
                                ],
                                [
                                    'name' => 'applepay_cart',
                                    'title' => __('applepay_display_cart', 'payplug'),
                                    'image_url' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/cart/applepay_cart.svg'),
                                    'label' => __('applepay_display_cart', 'payplug'),
                                    'value' => 'cart',
                                    'switch' => true,
                                    'checked' => $cart,
                                ],
                                [
                                    'name' => 'applepay_product',
                                    'title' => __('applepay_display_product', 'payplug'),
                                    'image_url' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/cart/applepay_cart.svg'),
                                    'label' => __('applepay_display_product', 'payplug'),
                                    'value' => 'product',
                                    'switch' => true,
                                    'checked' => $product,
                                ],
                            ],
                        'carriers' =>
                            [
                                'title' => __('applepay_active_carriers_title', 'payplug'),
                                'alert' => __('applepay_active_carriers_alert', 'payplug'),
                                'descriptions' => [
                                    'live' => [
                                        'description' => __('applepay_active_carriers_description', 'payplug'),
                                        'description_bold' => __('applepay_active_carriers_description_bold', 'payplug'),
                                        'description_warning' => __('applepay_active_carriers_description_warning', 'payplug'),
                                    ],
                                    'sandbox' => [],
                                ],
                                'instructions' => __('applepay_active_carriers_instructions', 'payplug'),
                                'carriers_list' => PayplugWoocommerceHelper::available_shipping_methods($carriers),

                            ],
                    ],
                ],
        ];
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_bancontact($active = false, $options = [])
    {
        return array_merge([
            'type' => 'payment_method',
            'name' => 'bancontact',
            'title' => __('payplug_section_bancontact_payment_title', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/bancontact.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_bancontact_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_bancontact_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_bancontact_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_bancontact_more_url', 'payplug'), '_blank'),
                ],
            ],
        ], self::apm_restrictions('bancontact', $options));
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_amex($active = false)
    {
        return [
            'type' => 'payment_method',
            'name' => 'american_express',
            'title' => __('payplug_section_american_express_payment_title', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/Amex_logo_color.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_american_express_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_amex_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_amex_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_amex_more_url', 'payplug'), '_blank'),
                ],
            ],
        ];
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_satispay($active = false, $options = [])
    {
        return array_merge([
            'type' => 'payment_method',
            'name' => 'satispay',
            'title' => __('payplug_satispay_activate', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/satispay.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_satispay_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_satispay_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_satispay_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_satispay_more_url', 'payplug'), '_blank'),
                ],
            ],
        ], self::apm_restrictions('satispay', $options));
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_mybank($active = false, $options = [])
    {
        return array_merge([
            'type' => 'payment_method',
            'name' => 'mybank',
            'title' => __('payplug_mybank_activate', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/mybank.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_mybank_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_mybank_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_mybank_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_mybank_more_url', 'payplug'), '_blank'),
                ],
            ],
        ], self::apm_restrictions('mybank', $options));
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_ideal($active = false, $options = [])
    {
        return array_merge([
            'type' => 'payment_method',
            'name' => 'ideal',
            'title' => __('payplug_ideal_activate', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/ideal.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_ideal_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_ideal_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_ideal_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_ideal_more_url', 'payplug'), '_blank'),
                ],
            ],
        ], self::apm_restrictions('ideal', $options));
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_wero($active = false, $options = [])
    {
        return array_merge([
            'type' => 'payment_method',
            'name' => 'wero',
            'title' => __('payplug_wero_activate', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/wero.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_wero_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_wero_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_wero_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_wero_more_url', 'payplug'), '_blank'),
                ],
            ],
        ], self::apm_restrictions('wero', $options));
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_bizum($active = false, $options = [])
    {
        return array_merge([
            'type' => 'payment_method',
            'name' => 'bizum',
            'title' => __('payplug_bizum_activate', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/bizum.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_bizum_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_bizum_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_bizum_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_bizum_more_url', 'payplug'), '_blank'),
                ],
            ],
        ], self::apm_restrictions('bizum', $options));
    }

    /**
     * @param $active
     *
     * @return array
     */
    public static function payment_method_scalapay($active = false, $options = [])
    {
        return array_merge([
            'type' => 'payment_method',
            'name' => 'scalapay',
            'title' => __('payplug_scalapay_activate', 'payplug'),
            'image' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/scalapay.svg'),
            'checked' => $active,
            'available_test_mode' => false,
            'descriptions' => [
                'live' => [
                    'description' => __('payplug_section_scalapay_payment_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_scalapay_more_url', 'payplug'), '_blank'),
                ],
                'sandbox' => [
                    'description' => __('payplug_scalapay_unavailable_testmode_description', 'payplug'),
                    'link_know_more' => Component::link(__('payplug_know_more_label', 'payplug'), __('payplug_scalapay_more_url', 'payplug'), '_blank'),
                ],
            ],
        ], self::apm_restrictions('scalapay', $options));
    }

    /**
     * Amount range and billing countries blocks, none without cached API limits.
     *
     * @param string $apm
     * @param array $options woocommerce_payplug_settings
     *
     * @return array
     */
    private static function apm_restrictions($apm, $options)
    {
        $config = ApmSettings::config_for(is_array($options) ? $options : [], $apm);
        $limits = $config['limits'];
        if (null === $limits) {
            return [];
        }

        $builder = new ApmRestrictionsBlock(static fn (string $key): string => __($key, 'payplug'));

        $country_names = [];
        foreach (WC()->countries->get_countries() as $code => $name) {
            $country_names[$code] = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        }

        return [
            'advanced_options' => [
                $builder->thresholds(
                    $apm,
                    ApmEligibility::effective_amounts($limits, $config['custom_amounts']),
                    ['min' => $limits['min'], 'max' => $limits['max']]
                ),
            ],
            'countries' => $builder->countries(
                $apm,
                $limits['countries'],
                $config['custom_countries'],
                array_keys(WC()->countries->get_allowed_countries()),
                $country_names
            ),
        ];
    }
}
