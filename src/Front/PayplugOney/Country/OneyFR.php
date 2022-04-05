<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney\Country;

class OneyFR extends OneyBase implements InterfaceOneyType
{

	/**
	 * @var string
	 */
	private $icon_withoutfees = 'oney-without-fees-3x4x';

	/**
	 * @var string
	 */
	private $icon_withfees = 'oney-3x4x';

	public function __construct()
	{
		parent::__construct();

	}

	public function setIcon($icon = '')
	{
		switch ($this->getOneyType()){
			case "without_fees": parent::setIcon($this->icon_withoutfees);break;
			default: parent::setIcon($this->icon_withfees);break;
		}

	}


}
