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

    /**
     * @dataProvider invalid_responses_provider
     *
     * @param mixed $response
     */
    public function test_invalid_responses($response): void
    {
        self::assertFalse(AccountGateway::is_valid_account_response($response));
    }

    public function invalid_responses_provider(): array
    {
        return [
            'null' => [null],
            'empty array' => [[]],
            'string' => ['{"payment_methods":{}}'],
            'no payment_methods' => [['configuration' => ['currencies' => ['EUR']]]],
            'no configuration' => [['payment_methods' => ['bancontact' => []]]],
            'payment_methods not an array' => [['payment_methods' => 'x', 'configuration' => []]],
            'configuration not an array' => [['payment_methods' => ['bancontact' => []], 'configuration' => 'x']],
            'empty payment_methods' => [['payment_methods' => [], 'configuration' => ['currencies' => ['EUR']]]],
            'empty configuration' => [['payment_methods' => ['bancontact' => []], 'configuration' => []]],
        ];
    }

    public function test_valid_response(): void
    {
        self::assertTrue(AccountGateway::is_valid_account_response([
            'id' => 'acc_1',
            'configuration' => ['currencies' => ['EUR'], 'min_amounts' => ['EUR' => 30], 'max_amounts' => ['EUR' => 2000000]],
            'payment_methods' => ['bancontact' => ['enabled' => true]],
        ]));
    }
}
