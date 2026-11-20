<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Automattic\WooCommerce\StoreApi\Utilities\PaymentUtils;
use Payplug\PayplugWoocommerce\Front\UhfSavedCards;
use Payplug\PayplugWoocommerce\Model\UhfCard;
use PHPUnit\Framework\TestCase;

class UhfSavedCards_test extends TestCase
{
    private const NATIVE_GATEWAY_ID = 'payplug_test_native_tokens';

    /** @var array the registered gateways, restored in tearDown() */
    private $original_gateways;

    /** @var int|string key of the 'payplug' gateway in WC()->payment_gateways()->payment_gateways */
    private $payplug_key;

    /** @var int */
    private $customer_id;

    protected function setUp(): void
    {
        parent::setUp();
        require_once ABSPATH . 'wp-admin/includes/user.php';

        // UhfSavedCards reads the mode from the registered 'payplug' gateway: pin it to 'test'
        // so these tests don't depend on whatever the suite's stored settings happen to be.
        // payment_gateways() re-keys the gateways by their id, so the stub replaces the real
        // 'payplug' entry in place and carries the id and enabled flag WooCommerce reads.
        $gateways = WC()->payment_gateways();
        $this->original_gateways = $gateways->payment_gateways;
        $this->payplug_key = 'payplug';

        foreach ($gateways->payment_gateways as $key => $gateway) {
            if (isset($gateway->id) && 'payplug' === $gateway->id) {
                $this->payplug_key = $key;
            }
        }

        $gateways->payment_gateways[$this->payplug_key] = (object) ['id' => 'payplug', 'mode' => 'test', 'enabled' => 'no'];

        $this->customer_id = wp_create_user('uhf_saved_' . wp_rand(), wp_generate_password());
    }

    protected function tearDown(): void
    {
        foreach (\WC_Payment_Tokens::get_customer_tokens($this->customer_id) as $token) {
            $token->delete(true);
        }

        WC()->payment_gateways()->payment_gateways = $this->original_gateways;

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

    public function testAddCardsToListRowsCarryTheUhfGatewayMarker(): void
    {
        UhfCard::insert($this->customer_id, 'alias_marker_1', 'VISA', '4242', 3, 2099, 'test');
        UhfCard::insert($this->customer_id, 'alias_marker_2', 'MASTERCARD', '4444', 4, 2098, 'test');

        $list = (new UhfSavedCards())->add_cards_to_list([], $this->customer_id);

        $this->assertCount(2, $list['cc']);
        foreach ($list['cc'] as $row) {
            $this->assertSame(UhfSavedCards::SAVED_METHOD_GATEWAY, $row['method']['gateway'] ?? null);
        }
    }

    public function testAddCardsToListKeepsTheMyAccountTableFields(): void
    {
        UhfCard::insert($this->customer_id, 'alias_fields_1', 'VISA', '4242', 3, 2099, 'test');

        $row = (new UhfSavedCards())->add_cards_to_list([], $this->customer_id)['cc'][0];

        $this->assertSame('VISA', $row['method']['brand']);
        $this->assertSame('4242', $row['method']['last4']);
        $this->assertSame('03/2099', $row['expires']);
        $this->assertFalse($row['is_default']);
        $this->assertSame('Delete', $row['actions']['delete']['name']);
        $this->assertArrayNotHasKey('default', $row['actions']);
    }

    public function testAddCardsToListAppendsAfterTheNativeTokenRows(): void
    {
        UhfCard::insert($this->customer_id, 'alias_append_1', 'VISA', '4242', 3, 2099, 'test');
        $native_row = ['method' => ['gateway' => 'payplug', 'last4' => '1111', 'brand' => 'Visa'], 'is_default' => true];

        $list = (new UhfSavedCards())->add_cards_to_list(['cc' => [$native_row]], $this->customer_id);

        $this->assertCount(2, $list['cc']);
        $this->assertSame($native_row, $list['cc'][0]);
        $this->assertSame('4242', $list['cc'][1]['method']['last4']);
    }

    public function testTheUhfGatewayMarkerIsNeverARegisteredGatewayId(): void
    {
        $gateway_ids = array_keys(WC()->payment_gateways()->payment_gateways());

        $this->assertContains('payplug', $gateway_ids);
        $this->assertNotContains(UhfSavedCards::SAVED_METHOD_GATEWAY, $gateway_ids);
    }

    public function testStoreApiSavedMethodsSkipUhfCardsWithoutAWarning(): void
    {
        UhfCard::insert($this->customer_id, 'alias_store_api_1', 'VISA', '4242', 3, 2099, 'test');
        wp_set_current_user($this->customer_id);
        $this->assertContains('4242', $this->savedListLast4s(), 'UhfSavedCards must be hooked on the saved-methods list.');

        // The Blocks checkout and the Store API read every row's method.gateway: a missing key
        // raises a PHP warning, which PHPUnit turns into an error here.
        $saved_methods = PaymentUtils::get_saved_payment_methods();

        $this->assertSame([], $saved_methods['enabled']['cc']);
        $this->assertNull($saved_methods['default']);
    }

    public function testStoreApiStillReturnsANativeTokenNextToAUhfCard(): void
    {
        WC()->payment_gateways()->payment_gateways[self::NATIVE_GATEWAY_ID] = (object) ['id' => self::NATIVE_GATEWAY_ID, 'enabled' => 'yes'];
        $token_id = $this->createNativeToken('1111');
        UhfCard::insert($this->customer_id, 'alias_store_api_2', 'VISA', '4242', 3, 2099, 'test');
        wp_set_current_user($this->customer_id);
        $this->assertContains('1111', $this->savedListLast4s());
        $this->assertContains('4242', $this->savedListLast4s());

        $saved_methods = PaymentUtils::get_saved_payment_methods();

        $this->assertCount(1, $saved_methods['enabled']['cc']);
        $this->assertSame(self::NATIVE_GATEWAY_ID, $saved_methods['enabled']['cc'][0]['method']['gateway']);
        $this->assertSame($token_id, $saved_methods['enabled']['cc'][0]['tokenId']);
        $this->assertSame($token_id, $saved_methods['default']['tokenId']);
    }

    private function createNativeToken(string $last4): int
    {
        $token = new \WC_Payment_Token_CC();
        $token->set_token('tok_' . wp_rand());
        $token->set_gateway_id(self::NATIVE_GATEWAY_ID);
        $token->set_card_type('visa');
        $token->set_last4($last4);
        $token->set_expiry_month('12');
        $token->set_expiry_year('2099');
        $token->set_user_id($this->customer_id);
        $token->set_default(true);
        $token->save();

        return $token->get_id();
    }

    private function savedListLast4s(): array
    {
        $rows = wc_get_customer_saved_methods_list($this->customer_id)['cc'] ?? [];

        return array_map(static fn (array $row): string => (string) ($row['method']['last4'] ?? ''), $rows);
    }

    public function testAddCardsToListLeavesTheListUntouchedWithoutAPayplugGateway(): void
    {
        unset(WC()->payment_gateways()->payment_gateways[$this->payplug_key]);
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
