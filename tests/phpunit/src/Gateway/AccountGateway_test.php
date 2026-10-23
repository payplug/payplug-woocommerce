<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\AccountGateway;
use PHPUnit\Framework\TestCase;

class AccountGateway_test extends TestCase
{
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
