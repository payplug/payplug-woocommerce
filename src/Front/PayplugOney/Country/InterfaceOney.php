<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney\Country;

interface InterfaceOney
{

	public function handleTotalProducts();

	public function addTotalProducts($qty);

	public function resetTotalProducts();

}
