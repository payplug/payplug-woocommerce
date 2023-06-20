<?php

namespace Payplug\PayplugWoocommerce\Gateway\PPRO;
use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;

class Ideal extends PayplugGenericGateway
{

	protected $min_thresholds;
	protected $max_thresholds;
	protected $allowed_country_codes = [];

	public function __construct()
	{

		parent::__construct();

		//since we're calling the parent construct we need to redefine the payment properties
		//once we detach the cc from default payment method, this will be no longer needed
		$this->id = 'ideal';
		$this->method_title = __("pay_with_ideal", "payplug");
		$this->title = __("pay_with_ideal", "payplug");
		$this->method_description = "";
		$this->description = "";
		$this->image = 'Ideal.svg';

		//WOOCO FIELDS
		$this->has_fields = false;
		$this->enabled = "yes";

		if (!$this->checkGateway()) {
			$this->enabled = "no";
		}

	}

}
