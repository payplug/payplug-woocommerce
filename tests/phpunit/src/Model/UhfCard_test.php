<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Model;

use Payplug\PayplugWoocommerce\Model\UhfCard;
use PHPUnit\Framework\TestCase;

class UhfCard_test extends TestCase
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

    public function testInsertThenFindByAliasReturnsTheStoredCard(): void
    {
        $id = UhfCard::insert(42, 'alias_1', 'VISA', '4242', 12, 2030, 'live');

        $card = UhfCard::find_by_alias('alias_1', 'live');

        $this->assertNotFalse($id);
        $this->assertSame('VISA', $card->brand);
        $this->assertSame('4242', $card->last4);
        $this->assertSame(42, (int) $card->customer_id);
    }

    public function testInsertingTheSameAliasAndModeTwiceReturnsTheExistingRowIdInsteadOfDuplicating(): void
    {
        $first_id = UhfCard::insert(42, 'alias_dup', 'VISA', '4242', 12, 2030, 'live');
        $second_id = UhfCard::insert(42, 'alias_dup', 'MASTERCARD', '1111', 1, 2031, 'live');

        $this->assertSame($first_id, $second_id);

        $card = UhfCard::find_by_alias('alias_dup', 'live');
        $this->assertSame('VISA', $card->brand);
    }

    public function testTheSameAliasCanExistOnceForLiveAndOnceForTest(): void
    {
        UhfCard::insert(42, 'alias_both', 'VISA', '4242', 12, 2030, 'live');
        UhfCard::insert(42, 'alias_both', 'VISA', '4242', 12, 2030, 'test');

        $this->assertNotNull(UhfCard::find_by_alias('alias_both', 'live'));
        $this->assertNotNull(UhfCard::find_by_alias('alias_both', 'test'));
    }

    public function testGetCustomerCardsReturnsOnlyThatCustomersCardsForTheGivenMode(): void
    {
        UhfCard::insert(42, 'alias_mine', 'VISA', '4242', 12, 2099, 'live');
        UhfCard::insert(99, 'alias_other_customer', 'VISA', '4242', 12, 2099, 'live');
        UhfCard::insert(42, 'alias_test_mode', 'VISA', '4242', 12, 2099, 'test');

        $cards = UhfCard::get_customer_cards(42, 'live');

        $this->assertCount(1, $cards);
        $this->assertSame('alias_mine', $cards[0]->alias_id);
    }

    public function testGetCustomerCardsExcludesAnExpiredCard(): void
    {
        UhfCard::insert(42, 'alias_expired', 'VISA', '4242', 1, 2020, 'live');
        UhfCard::insert(42, 'alias_valid', 'VISA', '4242', 12, 2099, 'live');

        $cards = UhfCard::get_customer_cards(42, 'live');

        $this->assertCount(1, $cards);
        $this->assertSame('alias_valid', $cards[0]->alias_id);
    }

    public function testFindForCustomerReturnsNullWhenTheCardBelongsToAnotherCustomer(): void
    {
        $id = UhfCard::insert(42, 'alias_owned', 'VISA', '4242', 12, 2099, 'live');

        $this->assertNull(UhfCard::find_for_customer($id, 99, 'live'));
        $this->assertNotNull(UhfCard::find_for_customer($id, 42, 'live'));
    }

    public function testFindForCustomerReturnsNullWhenTheModeDoesNotMatch(): void
    {
        $id = UhfCard::insert(42, 'alias_mode', 'VISA', '4242', 12, 2099, 'live');

        $this->assertNull(UhfCard::find_for_customer($id, 42, 'test'));
    }
}
