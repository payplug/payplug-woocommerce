<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;

class Bancontact  extends PayplugGenericGateway
{

	protected $enable_refund = true;
	const ENABLE_ON_TEST_MODE = false;

	public function __construct()
	{

		parent::__construct();

		/** @var \WC_Settings_API  override $id */
		$this->id                 = 'bancontact';

		/** @var \WC_Payment_Gateway overwrite for bancontact settings  */
		$this->method_title       = __('payplug_bancontact_title', 'payplug');
		$this->method_description = __('payplug_bancontact_description', 'payplug');

		$this->title = __('payplug_bancontact_title', 'payplug');
		$this->description = '';
		$this->has_fields = false;
		$this->image = 'lg-bancontact-checkout.svg';

		if(!$this->checkGateway()){
			$this->enabled = 'no';
		}

	}

}
