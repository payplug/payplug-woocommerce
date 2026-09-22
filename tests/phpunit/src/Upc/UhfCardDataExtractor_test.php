<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\UhfCardDataExtractor;
use PHPUnit\Framework\TestCase;

class UhfCardDataExtractor_test extends TestCase
{
    public function testExtractReadsBrandLast4AndExpirationFromAFullPaymentMethodShape(): void
    {
        $decoded = [
            'paymentMethod' => [
                'id' => 'alias_1',
                'card' => ['network' => 'visa', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['selectedBrand' => 'VISA', 'validityDate' => '2030-12'],
            ],
        ];

        $result = UhfCardDataExtractor::extract($decoded);

        $this->assertSame('VISA', $result['brand']);
        $this->assertSame('4242', $result['last4']);
        $this->assertSame(12, $result['exp_month']);
        $this->assertSame(2030, $result['exp_year']);
    }

    public function testExtractFallsBackToSelectedBrandWhenCardNetworkIsMissing(): void
    {
        $decoded = [
            'paymentMethod' => [
                'details' => ['selectedBrand' => 'mastercard'],
            ],
        ];

        $result = UhfCardDataExtractor::extract($decoded);

        $this->assertSame('MASTERCARD', $result['brand']);
    }

    public function testExtractReturnsEmptyValuesWhenPaymentMethodIsMissing(): void
    {
        $result = UhfCardDataExtractor::extract([]);

        $this->assertSame('', $result['brand']);
        $this->assertSame('', $result['last4']);
        $this->assertSame(0, $result['exp_month']);
        $this->assertSame(0, $result['exp_year']);
    }

    public function testExtractIgnoresAMalformedValidityDate(): void
    {
        $decoded = [
            'paymentMethod' => [
                'details' => ['validityDate' => 'not-a-date'],
            ],
        ];

        $result = UhfCardDataExtractor::extract($decoded);

        $this->assertSame(0, $result['exp_month']);
        $this->assertSame(0, $result['exp_year']);
    }
}
