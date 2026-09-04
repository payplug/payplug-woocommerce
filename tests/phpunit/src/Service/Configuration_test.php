<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\Configuration;
use PHPUnit\Framework\TestCase;

class Configuration_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    public function testHostedFieldsKeyMaterialDefaultsToEmptyStrings(): void
    {
        $configuration = new Configuration();
        $configuration->initialize_option();

        $this->assertSame('', $configuration->get_option('payment_methods.configuration.payplug.hosted_fields.public_key_id'));
        $this->assertSame('', $configuration->get_option('payment_methods.configuration.payplug.hosted_fields.public_key_value'));
    }

    public function testUpdateOptionsPersistsHostedFieldsKeyMaterial(): void
    {
        $configuration = new Configuration();
        $configuration->initialize_option();

        $options = $configuration->get_options();
        $options['payment_methods']['configuration']['payplug']['hosted_fields']['public_key_id'] = 'key_id_123';
        $options['payment_methods']['configuration']['payplug']['hosted_fields']['public_key_value'] = 'key_value_456';
        $configuration->update_options($options);

        // re-read from a fresh instance, forcing a round-trip through get_option('woocommerce_payplug_settings')
        $reloaded = new Configuration();

        $this->assertSame('key_id_123', $reloaded->get_option('payment_methods.configuration.payplug.hosted_fields.public_key_id'));
        $this->assertSame('key_value_456', $reloaded->get_option('payment_methods.configuration.payplug.hosted_fields.public_key_value'));
    }

    public function testDoesNotAlterExistingRetailApiFields(): void
    {
        $configuration = new Configuration();
        $configuration->initialize_option();

        $options = $configuration->get_options();
        $options['payment_methods']['configuration']['payplug']['embedded_mode'] = 'redirect';
        $options['payment_methods']['configuration']['payplug']['hosted_fields']['public_key_id'] = 'key_id_123';
        $configuration->update_options($options);

        $reloaded = new Configuration();

        $this->assertSame('redirect', $reloaded->get_option('payment_methods.configuration.payplug.embedded_mode'));
    }
}
