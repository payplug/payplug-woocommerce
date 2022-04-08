<?php

namespace Payplug\PayplugWoocommerce\Front\Layout;

interface InterfaceOneyLayout
{

	static function simulationPopupContentWithoutFees($oney);
	static function simulationPopupContent($oney);
	static function footerOneyWithoutFees($min, $max);
	static function footerOneyWithFees($min, $max);
	static function disabledOneyPopup($oney);
	static function payWithOney($oney);

}
