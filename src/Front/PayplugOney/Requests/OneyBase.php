<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney\Requests;

use function is_cart;
use function is_product;
use Payplug\PayplugWoocommerce\Front\PayplugOney\OneySimulation;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

abstract class OneyBase
{
    /**
     * @var string
     */
    private $country;

    /**
     * @var OneySimulation
     */
    private $simulation = [];

    /**
     * Dependency injection.
     *
     * @var \Payplug\PayplugWoocommerce\Front\PayplugOney\Country\OneyBase
     */
    private $oney;

    public function __construct()
    {
        add_action('wp_ajax_simulate_oney_payment', [$this, 'simulateOneyPayment']);
        add_action('wp_ajax_nopriv_simulate_oney_payment', [$this, 'simulateOneyPayment']);
        add_action('woocommerce_cart_totals_after_order_total', [$this, 'showOneyAnimationCart']);

        $options = get_option('woocommerce_payplug_settings', []);

        if (isset($options['oney_product_animation']) && ('yes' == $options['oney_product_animation'])) {
            add_action('woocommerce_before_add_to_cart_form', [$this, 'showOneyAnimationProduct']);
        }
    }

    /**
     * request simulation
     * print results.
     */
    public function simulateOneyPayment()
    {
        $simulation = new OneySimulation($this->oney);
        $this->simulation = $simulation->OneySimulation();
        $html = $this->drawAnimation();

        wp_send_json_success(
            [
                'popup' => $html,
            ]
        );

        wp_die();
    }

    /**
     * draw html popup.
     *
     * @return string
     */
    public function drawAnimation()
    {
        $class = 'Payplug\\PayplugWoocommerce\\Front\\Layout\\Oney' . $this->getCountry();

        switch ($this->oney->getOneyType()) {
            case 'without_fees':
                $footer = $class::footerOneyWithoutFees($this->oney->get_min_amount(), $this->oney->get_max_amount());
                $content = $class::simulationPopupContentWithoutFees($this);

                break;

            default:
                $footer = $class::footerOneyWithFees($this->oney->get_min_amount(), $this->oney->get_max_amount());
                $content = $class::simulationPopupContent($this);

                break;
        }

        return <<<HTML
 			{$content}
			{$footer}
HTML;
    }

    /**
     * Button to show oney popup cart page.
     */
    public function showOneyAnimationCart()
    {
        if ((is_cart()) && PayplugWoocommerceHelper::is_oney_available() && !PayplugWoocommerceHelper::is_subscription()) {
            global $product;

            $total_price = (is_numeric(floatval(WC()->cart->total))) ? floatval(WC()->cart->total) : (float) ($product->get_price());
            $this->oney->setTotalPrice($total_price);
            $this->oney->handleTotalProducts();

            //don't show animation
            if (!PayplugWoocommerceHelper::check_order_max_amount($this->oney->getTotalPrice())) {
                return false;
            }

            if ($this->oney->getTotalPrice() < $this->oney->get_min_amount() || $this->oney->getTotalPrice() > $this->oney->get_max_amount() || $this->oney->getTotalProducts() >= PayplugGatewayOney3x::ONEY_PRODUCT_QUANTITY_MAXIMUM) {
                $this->oney->setDisable(true);
            }

            $this->oneyGeneratePopup();
        }
    }

    /**
     * Button to show oney popup product page.
     */
    public function showOneyAnimationProduct()
    {
        global $product;

        if ((is_product()) && PayplugWoocommerceHelper::is_oney_available() && !in_array($product->get_type(), ['subscription', 'downloadable_subscription', 'virtual_subscription', 'variable-subscription'])) {
            $price = $product->get_price();

            if (method_exists($product, 'get_available_variations')) {
                $available_variations = $product->get_available_variations();
            }

            if (!empty($available_variations)) {
                foreach ($available_variations as $k => $value) {
                    $this->oney->setVariations($value);
                }
            }

            $this->oney->setTotalPrice($price);
            $this->oney->handleTotalProducts();

            //don't show animation
            if (!PayplugWoocommerceHelper::check_order_max_amount($price)) {
                return false;
            }

            if ($price < $this->oney->get_min_amount() || $price > $this->oney->get_max_amount() || $this->oney->getTotalProducts() >= PayplugGatewayOney3x::ONEY_PRODUCT_QUANTITY_MAXIMUM) {
                $this->oney->setDisable(true);
            }

            $this->oneyGeneratePopup();
        }
    }

    /**
     * get Html for Oney.
     */
    public function oneyGeneratePopup()
    {
        $class = 'Payplug\\PayplugWoocommerce\\Front\\Layout\\Oney' . $this->getCountry();
        $class = new $class();
        echo $class::payWithOney($this->oney);
        echo $class::disabledOneyPopup($this->oney);
    }

    /**
     * @param $options
     *
     * @return string
     */
    public function setCountry($options)
    {
        $this->country = !empty($options['payplug_merchant_country']) ? $options['payplug_merchant_country'] : 'FR';
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return array|OneySimulation
     */
    public function getSimulation()
    {
        return $this->simulation;
    }

    /**
     * @param $oney
     */
    public function setOney($oney)
    {
        $this->oney = $oney;
    }
}
