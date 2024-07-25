<?php

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Payplug\PayplugWoocommerce\Admin\Ajax;
use Payplug\PayplugWoocommerce\Admin\Metabox;
use Payplug\PayplugWoocommerce\Admin\Notices;
use Payplug\PayplugWoocommerce\Admin\WoocommerceActions;
use Payplug\PayplugWoocommerce\Controller\ApplePay;
use Payplug\PayplugWoocommerce\Front\PayplugOney\Requests\OneyWithFees;
use Payplug\PayplugWoocommerce\Front\PayplugOney\Requests\OneyWithoutFees;

use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugAmex;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugCreditCard;
use Payplug\PayplugWoocommerce\Gateway\blocks\PayplugBancontact;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugOney;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugSatispay;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugSofort;

class PayplugWoocommerce {

	/**
	 * @var PayplugWoocommerce
	 */
	private static $instance;

	/**
	 * PayPlug admin notices
	 *
	 * @var Notices
	 */
	public $notices;

	/**
	 * PayPlug metabox
	 *
	 * @var Metabox
	 */
	public $metabox;

	/**
	 * Custom woocommerce actions
	 *
	 * @var WoocommerceActions
	 */
	public $actions;

	/**
	 * @var PayplugWoocommerceRequest
	 */
	public $requests;

	/**
	 * Ajax actions handler
	 *
	 * @var Ajax
	 */
	public $ajax;

	/**
	 * Get the singleton instance.
	 *
	 * @return PayplugWoocommerce
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Singleton instance can't be cloned.
	 */
	private function __clone() {
	}

	/**
	 * Singleton instance can't be serialized.
	 */
	public function __wakeup() {
	}

	/**
	 * PayplugWoocommerce constructor.
	 */
	private function __construct() {

		// Bail early if WooCommerce is not activated
		if ( ! defined( 'WC_VERSION' ) ) {
			add_action( 'admin_notices', function () {
				?>
				<div id="message" class="notice notice-error">
					<p><?php _e( 'PayPlug requires an active version of WooCommerce', 'payplug' ); ?></p>
				</div>
				<?php
			} );

			return;
		}

		if ( PayplugWoocommerceHelper::is_pre_30() ) {
			require_once PAYPLUG_GATEWAY_PLUGIN_DIR . '/woocommerce-compat.php';
		}

		$this->notices  = new Notices();
		$this->metabox  = new Metabox();
		$this->actions  = new WoocommerceActions();
		$this->requests = new PayplugWoocommerceRequest();
		new Front\ApplePay();
		$this->ajax     = new Ajax();

		if( PayplugWoocommerceHelper::show_oney_popup() ) {
			$this->animationHandlers();
		}

		add_action( 'woocommerce_payment_gateways', [ $this, 'register_payplug_gateway' ] );

		// Registers WooCommerce Blocks integration.
		add_action( 'woocommerce_blocks_loaded', [$this, 'woocommerce_gateways_block_support'] );

		add_filter( 'plugin_action_links_' . PAYPLUG_GATEWAY_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Register PayPlug gateway.
	 *
	 * @param $methods
	 *
	 * @return array
	 */
	public function register_payplug_gateway( $methods ) {
		$methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGateway';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGatewayOney3x';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGatewayOney4x';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGatewayOney3xWithoutFees';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGatewayOney4xWithoutFees';
		$methods[] = __NAMESPACE__ . '\\Gateway\\Bancontact';
		$methods[] = __NAMESPACE__ . '\\Gateway\\AmericanExpress';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Mybank';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Giropay';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Ideal';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Sofort';
		$methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Satispay';

		$methods[] = __NAMESPACE__ . '\\Controller\\ApplePay';

		return $methods;
	}

	/**
	 * Add additional action links.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links = [] ) {
		$plugin_links = [
			'<a href="' . esc_url( PayplugWoocommerceHelper::get_setting_link() ) . '">' . esc_html__( 'Settings', 'payplug' ) . '</a>',
		];

		return array_merge( $plugin_links, $links );
	}

	public function animationHandlers()
	{
		$options = get_option('woocommerce_payplug_settings', []);

		//if live and don't have country setted on the option
		if ( !isset($options['payplug_merchant_country']) ) {
			$options['payplug_merchant_country'] = PayplugWoocommerceHelper::UpdateCountryOption($options);
		}

		//failsafe
		if( empty($options['payplug_merchant_country']) || empty($options['oney_type']) ){
			return ;
		}

		if( !class_exists( "\\Payplug\\PayplugWoocommerce\\Front\\PayplugOney\\Country\\Oney" .$options['payplug_merchant_country'] )){
			return ;
		}


		//check if the options has the Country setted
		Switch($options['oney_type']){
			case "without_fees" : new OneyWithoutFees();break;
			default: new OneyWithFees();break;
		}
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public function woocommerce_gateways_block_support(){
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new PayplugCreditCard() );
					$payment_method_registry->register( new PayplugBancontact() );
					$payment_method_registry->register( new PayplugSatispay() );
					$payment_method_registry->register( new PayplugAmex() );
					$payment_method_registry->register( new PayplugSofort() );
					$payment_method_registry->register( new PayplugOney() );
				}
			);
		}
	}

}
