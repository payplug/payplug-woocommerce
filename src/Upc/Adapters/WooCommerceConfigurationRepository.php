<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use PayplugUnifiedCore\Contracts\IConfigurationRepository;

class WooCommerceConfigurationRepository implements IConfigurationRepository
{
    use ServiceGetter;

    public function get(string $key): ?string
    {
        $value = get_option($key, false);

        return false === $value ? null : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        update_option($key, $value);
    }

    public function getClientId(): string
    {
        return (string) ($this->get_oauth_client_data()['client_id'] ?? '');
    }

    public function getClientSecret(): string
    {
        return (string) ($this->get_oauth_client_data()['client_secret'] ?? '');
    }

    public function getPublicKeyId(): string
    {
        return (string) ($this->get_configuration()->get_option('payment_methods.configuration.payplug.hosted_fields.identifier') ?? '');
    }

    public function getPublicKeyValue(): string
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    private function get_oauth_client_data(): array
    {
        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $oauth_client_data = json_decode((string) $this->get_configuration()->get_option('oauth_client_data'), true);

        return isset($oauth_client_data[$mode]) && is_array($oauth_client_data[$mode]) ? $oauth_client_data[$mode] : [];
    }
}
