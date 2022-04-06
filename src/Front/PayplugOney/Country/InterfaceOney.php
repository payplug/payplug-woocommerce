<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney\Country;

interface InterfaceOney
{

	public function handleTotalProducts();

	public function addTotalProducts($qty);

	public function resetTotalProducts();

	public function set_min_amount($amount);

	public function set_max_amount($amount);

	public function setIcon($icon);

	public function isDisable();

	public function setDisable($disable);

	public function setTotalPrice($total_price);

	public function setSimulatedClass($simulatedClass);

	public function setOneyType($oney_type);

	public function get_min_amount();

	public function get_max_amount();

	public function getIcon();

	public function getTotalProducts();

	public function getTotalPrice();

	public function getSimulatedClass();

	public function setPayplugOptions($payplugOptions);

	public function getOneyType();

	public function getPayplugOptions();




}
