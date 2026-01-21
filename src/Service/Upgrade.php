<?php

namespace Payplug\PayplugWoocommerce\Service;

use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use Payplug\PayplugWoocommerce\Traits\GatewayGetter;

class Upgrade
{
	use ServiceGetter;
	use GatewayGetter;

	public function run_upgrade()
	{
		// Check if new options need to be setted
		$configuration = $this->get_service('configuration');
		$version = $configuration->get_option('version');

		if(!empty($version) && version_compare($version, PAYPLUG_GATEWAY_VERSION, ">=")) {
			return;
		}

		$options = $configuration->validate_configuration();

		if (empty($options)) {
			return;
		}

		// set the current version
		$options['version'] = PAYPLUG_GATEWAY_VERSION;

		// replace current option by new one
		$configuration->clean_option();
		$configuration->update_options($options);

		// then refresh the permissions
		$account_gateway = $this->get_gateway('account');

		// then get permissions
		$formated_account_permissions = $account_gateway->get_permissions();

		// and update options
		$configuration->update_options($formated_account_permissions['global']);
		$configuration->update_payment_permissions($formated_account_permissions['payment_methods']);

		return;
	}
}
