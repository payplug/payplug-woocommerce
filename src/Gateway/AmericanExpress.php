<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;

class AmericanExpress extends PayplugGenericGateway
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
		$this->has_fields = false;
		$this->image = 'Amex_logo_color.svg';

		if(!$this->checkGateway())
			$this->enabled = 'no';

	}

}
