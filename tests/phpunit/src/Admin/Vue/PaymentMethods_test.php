<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin\Vue;

use Payplug\PayplugWoocommerce\Admin\Vue\PaymentMethods;
use Payplug\PayplugWoocommerce\Service\Configuration;
use PHPUnit\Framework\TestCase;

class PaymentMethods_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option('woocommerce_payplug_settings');
        delete_option('woocommerce_currency');
        parent::tearDown();
    }

    private function seed_settings(string $currency, string $oauth_client_data, bool $live = false): void
    {
        update_option('woocommerce_currency', $currency);
        $options = (new Configuration())->extract_options_from_fields();
        $options['mode'] = $live;
        $options['oauth_client_data'] = $oauth_client_data;
        $options['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $options['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'ACC-1';
        update_option('woocommerce_payplug_settings', $options);
    }

    private function payment_method_standard(): array
    {
        return (new PaymentMethods(get_option('woocommerce_payplug_settings')))->payment_method_standard();
    }

    public function test_identifier_is_shown_and_prefilled_on_a_non_eur_shop_connected_with_oauth2(): void
    {
        $this->seed_settings('USD', '{"test":{"client_id":"id","client_secret":"secret"},"live":[]}');

        $result = $this->payment_method_standard();

        self::assertArrayHasKey('advanced_settings', $result);
        $field = $result['advanced_settings']['options'][0]['options'][0];
        self::assertSame('identifier', $field['name']);
        self::assertSame('ACC-1', $field['value']);
    }

    public function test_identifier_is_omitted_on_a_non_eur_shop_with_the_classic_login(): void
    {
        $this->seed_settings('USD', '{}');

        self::assertArrayNotHasKey('advanced_settings', $this->payment_method_standard());
    }

    public function test_identifier_is_omitted_when_oauth2_credentials_are_missing_for_the_stored_mode(): void
    {
        $this->seed_settings('USD', '{"test":{"client_id":"id","client_secret":"secret"},"live":[]}', true);

        self::assertArrayNotHasKey('advanced_settings', $this->payment_method_standard());
    }

    public function test_identifier_is_omitted_on_a_eur_shop(): void
    {
        $this->seed_settings('EUR', '{"test":{"client_id":"id","client_secret":"secret"},"live":[]}');

        self::assertArrayNotHasKey('advanced_settings', $this->payment_method_standard());
    }
}
