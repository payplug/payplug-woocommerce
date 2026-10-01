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

    /**
     * Regression test for the unique key originally being (alias_id, mode) rather than
     * (customer_id, alias_id, mode): if PayPlug ever returns the same alias for the same
     * physical card used by two different customers, the second insert() would silently return
     * the FIRST customer's row id instead of storing its own - a customer who explicitly ticked
     * "save my card" would see no error and no saved card.
     */
    public function testInsertingTheSameAliasForTwoDifferentCustomersCreatesTwoSeparateRows(): void
    {
        $first_id = UhfCard::insert(42, 'alias_shared', 'VISA', '4242', 12, 2030, 'live');
        $second_id = UhfCard::insert(99, 'alias_shared', 'VISA', '4242', 12, 2030, 'live');

        $this->assertNotSame($first_id, $second_id);
        $this->assertNotNull(UhfCard::find_for_customer((int) $first_id, 42, 'live'));
        $this->assertNotNull(UhfCard::find_for_customer((int) $second_id, 99, 'live'));
    }

    public function testDeleteRemovesTheCardOnlyForItsOwningCustomer(): void
    {
        $id = UhfCard::insert(42, 'alias_delete_scoped', 'VISA', '4242', 12, 2099, 'live');

        $this->assertFalse(UhfCard::delete((int) $id, 99));
        $this->assertNotNull(UhfCard::find_for_customer((int) $id, 42, 'live'));

        $this->assertTrue(UhfCard::delete((int) $id, 42));
        $this->assertNull(UhfCard::find_for_customer((int) $id, 42, 'live'));
    }

    /**
     * Regression test for the migration itself: maybe_create_table() only creates a table that
     * doesn't exist yet, it never alters one that already does (unlike dbDelta()) - so an
     * install that already had this table from before the (customer_id, alias_id, mode) key fix
     * needs create_table()'s own explicit ALTER to actually take effect on it.
     */
    public function testCreateTableMigratesAnExistingTableWithTheOldTwoColumnUniqueKey(): void
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        $wpdb->query("
            CREATE TABLE `{$table_name}` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `alias_id` VARCHAR(64) NOT NULL,
                `brand` VARCHAR(20) NOT NULL,
                `last4` VARCHAR(4) NOT NULL,
                `exp_month` TINYINT UNSIGNED NOT NULL,
                `exp_year` SMALLINT UNSIGNED NOT NULL,
                `mode` VARCHAR(4) NOT NULL,
                `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `alias_id_mode` (`alias_id`, `mode`),
                KEY `customer_id` (`customer_id`)
            )
        ");

        UhfCard::create_table();

        $first_id = UhfCard::insert(42, 'alias_pre_migration', 'VISA', '4242', 12, 2030, 'live');
        $second_id = UhfCard::insert(99, 'alias_pre_migration', 'VISA', '4242', 12, 2030, 'live');

        $this->assertNotSame($first_id, $second_id, 'The old (alias_id, mode) key is still in effect - the migration did not run.');
    }
}
