<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\Upc\UhfCardPersister;
use PHPUnit\Framework\TestCase;

class UhfCardPersister_test extends TestCase
{
    protected function setUp(): void
    {
        UhfCard::create_table();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards');
        parent::tearDown();
    }

    public function testPersistPrefersFetchedDataOverFallback(): void
    {
        $result = (new UhfCardPersister())->persist(
            42,
            'alias_fetched',
            'live',
            ['brand' => 'MASTERCARD', 'last4' => '1111', 'exp_month' => 1, 'exp_year' => 2031],
            ['brand' => 'VISA', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]
        );

        $this->assertTrue($result);

        $card = UhfCard::find_by_alias('alias_fetched', 'live');
        $this->assertSame('VISA', $card->brand);
        $this->assertSame('4242', $card->last4);
    }

    public function testPersistFallsBackWhenFetchedDataIsIncomplete(): void
    {
        $result = (new UhfCardPersister())->persist(
            42,
            'alias_fallback',
            'live',
            ['brand' => 'MASTERCARD', 'last4' => '1111', 'exp_month' => 1, 'exp_year' => 2031],
            []
        );

        $this->assertTrue($result);

        $card = UhfCard::find_by_alias('alias_fallback', 'live');
        $this->assertSame('MASTERCARD', $card->brand);
        $this->assertSame('1111', $card->last4);
    }

    public function testPersistRejectsABrandOutsideTheAllowedWhitelist(): void
    {
        $result = (new UhfCardPersister())->persist(
            42,
            'alias_bad_brand',
            'live',
            [],
            ['brand' => 'AMEX', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]
        );

        $this->assertFalse($result);
        $this->assertNull(UhfCard::find_by_alias('alias_bad_brand', 'live'));
    }

    public function testPersistRejectsANonNumericLast4(): void
    {
        $result = (new UhfCardPersister())->persist(
            42,
            'alias_bad_last4',
            'live',
            [],
            ['brand' => 'VISA', 'last4' => 'abcd', 'exp_month' => 12, 'exp_year' => 2030]
        );

        $this->assertFalse($result);
    }

    public function testPersistRejectsAnOutOfRangeMonth(): void
    {
        $result = (new UhfCardPersister())->persist(
            42,
            'alias_bad_month',
            'live',
            [],
            ['brand' => 'VISA', 'last4' => '4242', 'exp_month' => 13, 'exp_year' => 2030]
        );

        $this->assertFalse($result);
    }

    public function testPersistReturnsFalseWhenNoUsableDataIsAvailableAtAll(): void
    {
        $result = (new UhfCardPersister())->persist(42, 'alias_empty', 'live', [], []);

        $this->assertFalse($result);
    }
}
