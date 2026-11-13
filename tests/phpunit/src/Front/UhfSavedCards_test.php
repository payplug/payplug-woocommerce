<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\UhfSavedCards;
use Payplug\PayplugWoocommerce\Model\UhfCard;
use PHPUnit\Framework\TestCase;

class UhfSavedCards_test extends TestCase
{
    /** @var mixed the registered 'payplug' gateway, restored in tearDown() */
    private $original_gateway;

    /** @var int */
    private $customer_id;

    protected function setUp(): void
    {
        parent::setUp();
        require_once ABSPATH . 'wp-admin/includes/user.php';

        // UhfSavedCards reads the mode from the registered 'payplug' gateway: pin it to 'test'
        // so these tests don't depend on whatever the suite's stored settings happen to be.
        $gateways = WC()->payment_gateways();
        $this->original_gateway = $gateways->payment_gateways['payplug'] ?? null;
        $gateways->payment_gateways['payplug'] = (object) ['mode' => 'test'];

        $this->customer_id = wp_create_user('uhf_saved_' . wp_rand(), wp_generate_password());
    }

    protected function tearDown(): void
    {
        $gateways = WC()->payment_gateways();

        if (null === $this->original_gateway) {
            unset($gateways->payment_gateways['payplug']);
        } else {
            $gateways->payment_gateways['payplug'] = $this->original_gateway;
        }

        if (UhfCard::check_table_exists()) {
            global $wpdb;
            $wpdb->query('TRUNCATE TABLE ' . $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards');
        }

        remove_all_filters('wp_redirect');
        wc_clear_notices();
        wp_set_current_user(0);
        unset($_GET['payplug_delete_uhf_card'], $_GET['_wpnonce']);
        wp_delete_user($this->customer_id);
        parent::tearDown();
    }

    /**
     * maybe_handle_delete() ends with wp_safe_redirect() + exit: throwing from the 'wp_redirect'
     * filter stops it right before the exit, with every side effect (deletion, notice) done.
     */
    private function handle_delete(): void
    {
        add_filter('wp_redirect', static function (): void {
            throw new \RuntimeException('redirected');
        });

        try {
            (new UhfSavedCards())->maybe_handle_delete();
            $this->fail('Expected a redirect.');
        } catch (\RuntimeException $e) {
            $this->assertSame('redirected', $e->getMessage());
        }
    }

    private function lastNotice(string $type): string
    {
        $notices = wc_get_notices($type);

        return $notices ? end($notices)['notice'] : '';
    }

    public function testAddCbLabelAddsAnUppercaseCbLabel(): void
    {
        $labels = (new UhfSavedCards())->add_cb_label(['visa' => 'Visa']);

        $this->assertSame(['visa' => 'Visa', 'cb' => 'CB'], $labels);
    }

    public function testAddCardsToListAppendsTheCustomersCardsForTheCurrentMode(): void
    {
        UhfCard::insert($this->customer_id, 'alias_list_1', 'VISA', '4242', 3, 2099, 'test');
        UhfCard::insert($this->customer_id, 'alias_list_live', 'VISA', '1111', 3, 2099, 'live');

        $list = (new UhfSavedCards())->add_cards_to_list([], $this->customer_id);

        $this->assertCount(1, $list['cc']);
        $this->assertSame('4242', $list['cc'][0]['method']['last4']);
        $this->assertSame('03/2099', $list['cc'][0]['expires']);
        $this->assertStringContainsString('payplug_delete_uhf_card=', $list['cc'][0]['actions']['delete']['url']);
    }

    public function testAddCardsToListLeavesTheListUntouchedWithoutAPayplugGateway(): void
    {
        unset(WC()->payment_gateways()->payment_gateways['payplug']);
        UhfCard::insert($this->customer_id, 'alias_list_2', 'VISA', '4242', 3, 2099, 'test');

        $list = (new UhfSavedCards())->add_cards_to_list(['cc' => []], $this->customer_id);

        $this->assertSame(['cc' => []], $list);
    }

    public function testDeleteRemovesTheCustomersOwnCard(): void
    {
        $card_id = UhfCard::insert($this->customer_id, 'alias_delete_1', 'VISA', '4242', 3, 2099, 'test');
        wp_set_current_user($this->customer_id);
        $_GET['payplug_delete_uhf_card'] = (string) $card_id;
        $_GET['_wpnonce'] = wp_create_nonce('payplug-delete-uhf-card-' . $card_id);

        $this->handle_delete();

        $this->assertNull(UhfCard::find_for_customer($card_id, $this->customer_id, 'test'));
        $this->assertSame(__('payplug_uhf_saved_cards_deleted_notice', 'payplug'), $this->lastNotice('success'));
    }

    public function testDeleteRejectsAnInvalidNonce(): void
    {
        $card_id = UhfCard::insert($this->customer_id, 'alias_delete_2', 'VISA', '4242', 3, 2099, 'test');
        wp_set_current_user($this->customer_id);
        $_GET['payplug_delete_uhf_card'] = (string) $card_id;
        $_GET['_wpnonce'] = 'forged';

        $this->handle_delete();

        $this->assertNotNull(UhfCard::find_for_customer($card_id, $this->customer_id, 'test'));
        $this->assertSame(__('payplug_uhf_saved_cards_invalid_notice', 'payplug'), $this->lastNotice('error'));
    }

    public function testDeleteNeverRemovesAnotherCustomersCard(): void
    {
        $card_id = UhfCard::insert($this->customer_id, 'alias_delete_3', 'VISA', '4242', 3, 2099, 'test');
        $other_customer_id = wp_create_user('uhf_other_' . wp_rand(), wp_generate_password());
        wp_set_current_user($other_customer_id);
        // A valid nonce for the other customer: only the ownership check stands in the way.
        $_GET['payplug_delete_uhf_card'] = (string) $card_id;
        $_GET['_wpnonce'] = wp_create_nonce('payplug-delete-uhf-card-' . $card_id);

        $this->handle_delete();

        $this->assertNotNull(UhfCard::find_for_customer($card_id, $this->customer_id, 'test'));
        $this->assertSame(__('payplug_uhf_saved_cards_invalid_notice', 'payplug'), $this->lastNotice('error'));

        wp_delete_user($other_customer_id);
    }
}
