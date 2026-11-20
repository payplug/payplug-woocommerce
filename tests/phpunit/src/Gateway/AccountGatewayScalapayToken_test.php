<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\AccountGateway;
use PHPUnit\Framework\TestCase;

class AccountGatewayScalapayToken_test extends TestCase
{
    public function test_reads_the_token_of_the_account(): void
    {
        self::assertSame('tok_test_123', AccountGateway::scalapay_merchant_token($this->account(' tok_test_123 ')));
    }

    /**
     * @dataProvider unusable_accounts_provider
     */
    public function test_unusable_token_is_empty(array $account): void
    {
        self::assertSame('', AccountGateway::scalapay_merchant_token($account));
    }

    public function unusable_accounts_provider(): array
    {
        return [
            'empty token' => [$this->account('')],
            'null token' => [$this->account(null)],
            'non string token' => [$this->account(['tok'])],
            'integer token' => [$this->account(123)],
            'no token key' => [['payment_methods' => ['scalapay' => ['enabled' => true]]]],
            'scalapay not an array' => [['payment_methods' => ['scalapay' => 'tok']]],
            'no scalapay' => [['payment_methods' => ['satispay' => ['enabled' => true]]]],
            'empty account' => [[]],
        ];
    }

    /**
     * @param mixed $token
     */
    private function account($token): array
    {
        $account = ['payment_methods' => ['scalapay' => ['enabled' => true]]];
        $node = &$account;
        foreach (AccountGateway::SCALAPAY_MERCHANT_TOKEN_PATH as $key) {
            $node = &$node[$key];
        }
        $node = $token;

        return $account;
    }
}
