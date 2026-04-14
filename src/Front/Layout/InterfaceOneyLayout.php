<?php

namespace Payplug\PayplugWoocommerce\Front\Layout;

interface InterfaceOneyLayout
{
    public static function simulationPopupContentWithoutFees($oney);

    public static function simulationPopupContent($oney);

    public static function footerOneyWithoutFees($min, $max);

    public static function footerOneyWithFees($min, $max);

    public static function disabledOneyPopup($oney);

    public static function payWithOney($oney);
}
