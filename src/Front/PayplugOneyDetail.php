<?php

namespace Payplug\PayplugWoocommerce\Front;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PayplugOneyDetail
{

    private $account;
    private $min_amount = "XXX";
    private $max_amount = "XXX";

    public function __construct()
    {
        add_action( 'wp_ajax_simulate_oney_payment', [ $this, 'simulate_oney_payment' ]);
        add_action( 'wp_ajax_nopriv_simulate_oney_payment', [ $this, 'simulate_oney_payment' ]);
        if (!is_admin()) {
            if(PayplugWoocommerceHelper::is_oney_available()) {
                wp_enqueue_style('payplug-oney', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-oney.css', [], PAYPLUG_GATEWAY_VERSION);
                wp_enqueue_script('payplug-oney-mobile', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-detect-mobile.js', [], PAYPLUG_GATEWAY_VERSION, true);
                wp_enqueue_script('payplug-oney', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-oney.js', [
                    'jquery',
                    'jquery-ui-position'
                ], PAYPLUG_GATEWAY_VERSION, true);
                // Product page
                add_action('woocommerce_single_product_summary', [$this, 'oney_simulate_payment_detail']);
    
                // Total cart
                add_action('woocommerce_cart_totals_after_order_total', [$this, 'oney_simulate_payment_detail']);
            }
        }
    }

    /**
     * Simulate Oney Payment
     * 
     * @return void
     */
    public function simulate_oney_payment() {
        $total_price = $_POST['price'];
        $oney_range = PayplugWoocommerceHelper::get_min_max_oney();
        $this->min_amount = $oney_range['min'];
        $this->max_amount = $oney_range['max'];
        if ($total_price < $this->min_amount || $total_price > $this->max_amount) {
            $oney_response = false;
        } else {
            try {
                $api = new \Payplug\PayplugWoocommerce\Gateway\PayplugApi(new PayplugGatewayOney3x());
                $api->init();
                $oney_response = $api->simulate_oney_payment($total_price);
            } catch (\Exception $e) {
                PayplugGatewayOney3x::log("Simulate Oney Payment, " . $e->getMessage() );
                $oney_response = null;
            }
        }
        $result = $this->get_simulate_oney_payment_popup($oney_response);
        wp_send_json_success(
			array(
				'popup' => $result
			)
        );
        wp_die();
    }

    /**
     * Show oney simulation details
     * 
     * @param $oney_response
     * @return string
     */
    public function get_simulate_oney_payment_popup($oney_response) {

        $cgv =  sprintf(__("Offre de financement avec apport obligatoire, 
        réservée aux particuliers et valable pour tout achat de %s€ à %s€. 
        Sous réserve d'acceptation par Oney Bank. Vous disposez d'un délai de 14 jours pour renoncer à votre crédit. 
        Oney Bank - SA au capital de 51 286 585€ - 34 Avenue de Flandre 59170 Croix - 546 380 197 RCS Lille Métropole - n° Orias 07 023 261 www.orias.fr 
        Correspondance : CS 60 006 - 59895 Lille Cedex - www.oney.fr", "payplug"), $this->min_amount, $this->max_amount);
        $f = function($fn) { return $fn; }; 
        if($oney_response) {
            $popup = "
            <div id='oney-popup-arrow' class='triangle-left'></div>
            <div class='oney-img oney-logo no-margin'></div>
            <div class='oney-title'>
                <p class='no-margin oney-color'>{$f(__('PAYMENT', 'payplug'))}  </p>
                <p class='no-margin bold oney-color'>{$f(__('BY CREDIT CARD', 'payplug'))}</p>
            </div>
            <div class='oney-content oney-3x-content'>
                <div class='oney-img oney-3x no-margin'></div>
                <div class='oney-details'>
                    <p class='bold no-margin'> {$f(__('Bring', 'payplug'))} : {$oney_response['x3_with_fees']['down_payment_amount']}  €</p>
                    <p class='bold no-margin'>+2  {$f(__('monthly payment of', 'payplug'))} : {$oney_response['x3_with_fees']['installments'][0]['amount']} € </p>
                    <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))} : {$oney_response['x3_with_fees']['total_cost']} € </p>
                    <p class='no-margin'> {$f(__('TAEG', 'payplug'))} : {$oney_response['x3_with_fees']['effective_annual_percentage_rate']}  % </p>
                </div>
            </div>
            <div class='oney-separator'></div>
            <div class='oney-content oney-4x-content'>
                <div class='oney-img oney-4x no-margin'></div>
                <div class='oney-details'>
                    <p class='bold no-margin'> {$f(__('Bring', 'payplug'))} : {$oney_response['x4_with_fees']['down_payment_amount']}  €</p>
                    <p class='bold no-margin'>+3  {$f(__('monthly payment of', 'payplug'))} : {$oney_response['x4_with_fees']['installments'][0]['amount']}  € </p>
                    <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))} : {$oney_response['x4_with_fees']['total_cost']} € </p>
                    <p class='no-margin'> {$f(__('TAEG', 'payplug'))} : {$oney_response['x4_with_fees']['effective_annual_percentage_rate']}  % </p>
                </div>
            </div>
            <div class='oney-content oney-cgv-content'>
                {$cgv}
            </div>";
        } else {            
            $description = ($oney_response === null) ? "" : sprintf(__('Payments for this amount (%s) are not authorised with this payment gateway.', 'payplug'), $_POST['price']);
            $popup = "
            $description
            <div id='oney-popup-arrow' class='triangle-left'></div>
            <div class='oney-content oney-cgv-content'>
                {$cgv}
            </div>";
        }

        return $popup;        
    }

    /**
     * Button to show oney popup
     * 
     * @return void
     */
    public function oney_simulate_payment_detail()
    {
        wp_localize_script('payplug-oney', 'payplug_config', array(
            'ajax_url'      => admin_url('admin-ajax.php'),
            'ajax_action'   => 'simulate_oney_payment',
            'is_cart'       => is_cart()
        ));

        global $product;
        $total_price = (is_cart()) ? floatval(WC()->cart->cart_contents_total) : (int) ($product->get_price());
		$total_qty = (is_cart()) ? (int) (WC()->cart->cart_contents_count) : (int) ($product->get_min_purchase_quantity());
        $oney_range = PayplugWoocommerceHelper::get_min_max_oney();
        $this->min_amount = $oney_range['min'];
        $this->max_amount = $oney_range['max'];
		$this->max_qty = PayplugWoocommerceHelper::get_max_qty_oney();
        $disabled = "";
        if ($total_price < $this->min_amount || $total_price > $this->max_amount) {
            $disabled = "disabled";
        }
        
?>
        <div class="payplug-oney <?php echo $disabled; ?>" data-price="<?php echo $total_price ?>">
            <?php echo __('OR PAY IN', 'payplug'); ?>
            <div class="oney-img oney-3x4x"></div>
            <div id="oney-show-popup" class="bold oney-color">?</div>
        </div>
        <div class="payplug-oney <?php echo $disabled; ?>" id="oney-popup">
            <div class="payplug-lds-roller"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
        </div>
    <?php
    }
}
