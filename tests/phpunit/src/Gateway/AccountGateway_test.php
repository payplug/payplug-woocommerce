<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\AccountGateway;
use PHPUnit\Framework\TestCase;

class AccountGateway_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    public function test_is_legacy_logged_for_an_api_key_session_without_jwt(): void
    {
        update_option('woocommerce_payplug_settings', [
            'api_key' => '{"test":"sk_test_x","live":""}',
            'jwt' => '{}',
        ]);

        $account = new AccountGateway();

        self::assertTrue($account->is_logged());
        self::assertTrue($account->is_legacy_logged());
    }

    public function test_is_not_legacy_logged_for_an_oauth_session(): void
    {
        update_option('woocommerce_payplug_settings', [
            'api_key' => '{"test":"t","live":""}',
            'jwt' => '{"test":{"access_token":"t"}}',
        ]);

        $account = new AccountGateway();

        self::assertTrue($account->is_logged());
        self::assertFalse($account->is_legacy_logged());
    }

    public function test_is_not_legacy_logged_when_not_logged_in(): void
    {
        update_option('woocommerce_payplug_settings', [
            'api_key' => '{}',
            'jwt' => '{}',
        ]);

        self::assertFalse((new AccountGateway())->is_legacy_logged());
    }
}
