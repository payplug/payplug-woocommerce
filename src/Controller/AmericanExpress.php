<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\Resource\Payment as PaymentResource;

class AmericanExpress extends PayplugGateway
{

	public function __construct() {

		parent::__construct();

		/** @var \WC_Settings_API  override $id */
		$this->id = 'american_express';

		/** @var \WC_Payment_Gateway overwrite for apple pay settings */
		$this->method_title = __('payplug_amex_title', 'payplug');
		$this->method_description = "";

		$this->title = __('payplug_amex_title', 'payplug');
		$this->description = '';

		if(!$this->checkAmericanExpress())
			$this->enabled = 'no';

	}

	/**
	 *
	 * Check American Express Authorization
	 *
	 * @return bool
	 */
	private function checkAmericanExpress(){
		$account = PayplugWoocommerceHelper::get_account_data_from_options();

		if (isset($account['payment_methods']['american_express']['enabled']) ) {

			if( !empty($account['american_express']) && $account['american_express'] === 'yes' )
				return  $account['payment_methods']['american_express']['enabled'];

		}

		return false;
	}

	/**
	 * @return bool|void
	 */
	public function process_admin_options() {
		$data = $this->get_post_data();
		if (isset($data['woocommerce_payplug_mode'])) {
			if ($data['woocommerce_payplug_mode'] === '0') {
				$options = get_option('woocommerce_payplug_settings', []);
				$options['american_express'] = 'no';
				update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );
			}
		}

		if (isset($data['woocommerce_payplug_american_express'])) {
			if (($data['woocommerce_payplug_american_express'] == 1) && (!$this->checkAmericanExpress())) {
				$options = get_option('woocommerce_payplug_settings', []);
				$options['american_express'] = 'no';
				update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );
				add_action( 'admin_notices', [$this ,"display_notice"] );
			}
		}

	}

	/**
	 * Display unauthorized error
	 *
	 * @return void
	 */
	public static function display_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo __( 'payplug_amex_unauthorized_message', 'payplug' ); ?></p>
		</div>
		<?php
	}


}
