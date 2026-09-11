<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceConfigurationRepository;
use PHPUnit\Framework\TestCase;

class WooCommerceConfigurationRepository_test extends TestCase
{
    protected function setUp(): void
    {
        (new Configuration())->initialize_option();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    private function setOauthClientData(bool $live): void
    {
        $configuration = new Configuration();
        $options = $configuration->get_options();
        $options['mode'] = $live;
        $options['oauth_client_data'] = json_encode([
            'live' => ['client_id' => 'live_id_123', 'client_secret' => 'live_secret_123'],
            'test' => ['client_id' => 'test_id_123', 'client_secret' => 'test_secret_123'],
        ]);
        $configuration->update_options($options);
    }

    public function testGetClientIdReadsLiveCredentialsInLiveMode(): void
    {
        $this->setOauthClientData(true);

        $this->assertSame('live_id_123', (new WooCommerceConfigurationRepository())->getClientId());
    }

    public function testGetClientSecretReadsLiveCredentialsInLiveMode(): void
    {
        $this->setOauthClientData(true);

        $this->assertSame('live_secret_123', (new WooCommerceConfigurationRepository())->getClientSecret());
    }

    public function testGetClientIdReadsTestCredentialsInTestMode(): void
    {
        $this->setOauthClientData(false);

        $this->assertSame('test_id_123', (new WooCommerceConfigurationRepository())->getClientId());
    }

    public function testGetClientIdReturnsEmptyStringWhenOauthClientDataIsMissing(): void
    {
        $this->assertSame('', (new WooCommerceConfigurationRepository())->getClientId());
    }

    public function testGetPublicKeyIdReturnsTheAccountIdBoField(): void
    {
        $configuration = new Configuration();
        $options = $configuration->get_options();
        $options['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acc_test_123';
        $configuration->update_options($options);

        $this->assertSame('acc_test_123', (new WooCommerceConfigurationRepository())->getPublicKeyId());
    }

    public function testGetPublicKeyValueReturnsEmptyString(): void
    {
        $this->assertSame('', (new WooCommerceConfigurationRepository())->getPublicKeyValue());
    }

    public function testSetThenGetRoundTripsAnArbitraryKey(): void
    {
        $repository = new WooCommerceConfigurationRepository();
        $repository->set('payplug_upc_test_key', 'some-value');

        $this->assertSame('some-value', $repository->get('payplug_upc_test_key'));

        delete_option('payplug_upc_test_key');
    }

    public function testGetReturnsNullForAnUnsetKey(): void
    {
        $this->assertNull((new WooCommerceConfigurationRepository())->get('payplug_upc_never_set_key'));
    }
}
