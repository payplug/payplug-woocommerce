<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayRequirements;
use Payplug\PayplugWoocommerce\Gateway\PayplugPermissions;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

class Bancontact extends PayplugGateway
{

	public function __construct()
	{

		parent::__construct();

		/** @var \WC_Settings_API  override $id */
		$this->id                 = 'bancontact';

		/** @var \WC_Payment_Gateway overwrite for bancontact settings  */
		$this->method_title       = __('payplug_bancontact_title', 'payplug');
		$this->method_description = __('payplug_bancontact_description', 'payplug');

		$this->title = __('payplug_bancontact_title', 'payplug');
		$this->description = __('payplug_bancontact_description', 'payplug');

		$this->checkBancontact();
	}

	private function checkBancontact(){
		$account = PayplugWoocommerceHelper::get_account_data_from_options();

		if (isset($account['payment_methods']['bancontact']['enabled'])) {
			return  $account['payment_methods']['bancontact']['enabled'];
		}
		return true;
	}

	/**
	 * Get payment icons.
	 *
	 * @return string
	 */
	public function get_icon()
	{
		$available_img = 'lg-bancontact-checkout.png';
		$icons = apply_filters('payplug_payment_icons', [
			'payplug' => sprintf('<img src="%s" alt="Oney 4x" class="payplug-payment-icon" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/' . $available_img)),
		]);
		$icons_str = '';
		foreach ($icons as $icon) {
			$icons_str .= $icon;
		}
		return $icons_str;
	}

}
