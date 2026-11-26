<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\UhfSavedCards;
use PHPUnit\Framework\TestCase;

/**
 * Retail cards carry no currency and the Retail API is EUR-only: outside EUR the checkout
 * list (Blocks / Store API) must not offer them. My Account keeps them.
 */
class UhfSavedCardsRetailCurrency_test extends TestCase
{
    private const TOKENS_HOOK = 'woocommerce_get_customer_payment_tokens';

    /** @var int */
    private $customer_id;

    /** @var \WP_Hook|null */
    private $original_tokens_hook;

    protected function setUp(): void
    {
        parent::setUp();
        require_once ABSPATH . 'wp-admin/includes/user.php';
        update_option('woocommerce_currency', 'EUR');
        $this->customer_id = wp_create_user('retail_cur_' . wp_rand(), wp_generate_password());
        $this->original_tokens_hook = isset($GLOBALS['wp_filter'][self::TOKENS_HOOK])
            ? clone $GLOBALS['wp_filter'][self::TOKENS_HOOK]
            : null;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp']->query_vars['payment-methods']);
        wp_set_current_user(0);
        foreach (\WC_Payment_Tokens::get_tokens(['user_id' => $this->customer_id, 'gateway_id' => 'payplug']) as $token) {
            $token->delete(true);
        }
        remove_all_filters(self::TOKENS_HOOK);
        if (null !== $this->original_tokens_hook) {
            $GLOBALS['wp_filter'][self::TOKENS_HOOK] = $this->original_tokens_hook;
        }
        wp_delete_user($this->customer_id);
        delete_option('woocommerce_currency');
        parent::tearDown();
    }

    public function test_list_is_unchanged_in_eur(): void
    {
        $list = ['cc' => [$this->row('payplug', true), $this->row(UhfSavedCards::SAVED_METHOD_GATEWAY)]];

        $this->assertSame($list, $this->hide($list));
    }

    /**
     * @dataProvider non_eur_currencies
     */
    public function test_retail_rows_are_removed_outside_eur(string $currency): void
    {
        update_option('woocommerce_currency', $currency);
        $list = ['cc' => [$this->row('payplug'), $this->row(UhfSavedCards::SAVED_METHOD_GATEWAY)]];

        $this->assertSame([$this->row(UhfSavedCards::SAVED_METHOD_GATEWAY)], $this->hide($list)['cc']);
    }

    public function non_eur_currencies(): array
    {
        return [['USD'], ['JPY']];
    }

    public function test_list_is_unchanged_on_the_my_account_payment_methods_endpoint(): void
    {
        update_option('woocommerce_currency', 'USD');
        $GLOBALS['wp']->query_vars['payment-methods'] = '';
        $list = ['cc' => [$this->row('payplug', true)]];

        $this->assertSame($list, $this->hide($list));
    }

    public function test_other_gateways_other_groups_and_uhf_rows_are_untouched_in_order(): void
    {
        update_option('woocommerce_currency', 'USD');
        $list = [
            'cc' => [$this->row('other_gateway'), $this->row('payplug'), $this->row(UhfSavedCards::SAVED_METHOD_GATEWAY)],
            'echeck' => [$this->row('payplug')],
        ];

        $result = $this->hide($list);

        $this->assertSame([$this->row('other_gateway'), $this->row(UhfSavedCards::SAVED_METHOD_GATEWAY)], $result['cc']);
        $this->assertSame($list['echeck'], $result['echeck']);
    }

    public function test_a_default_retail_row_is_removed_and_other_flags_are_kept(): void
    {
        update_option('woocommerce_currency', 'USD');
        $list = ['cc' => [$this->row('payplug', true), $this->row('other_gateway', false)]];

        $this->assertSame([$this->row('other_gateway', false)], $this->hide($list)['cc']);
    }

    public function test_the_result_is_reindexed(): void
    {
        update_option('woocommerce_currency', 'USD');
        $list = ['cc' => [$this->row('payplug'), $this->row('other_gateway'), $this->row('payplug'), $this->row(UhfSavedCards::SAVED_METHOD_GATEWAY)]];

        $this->assertSame([0, 1], array_keys($this->hide($list)['cc']));
    }

    public function test_the_cc_group_is_removed_when_only_retail_rows_were_there(): void
    {
        update_option('woocommerce_currency', 'USD');

        $this->assertSame([], $this->hide(['cc' => [$this->row('payplug')]]));
    }

    public function test_an_empty_list_is_unchanged(): void
    {
        update_option('woocommerce_currency', 'USD');

        $this->assertSame([], $this->hide([]));
    }

    public function test_the_callback_is_registered_after_wc_and_uhf_builders(): void
    {
        // has_filter() needs the registered instance: look the callback up by method name.
        global $wp_filter;
        $found = [];
        foreach ($wp_filter['woocommerce_saved_payment_methods_list']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && 'hide_retail_cards_outside_eur' === $callback['function'][1]) {
                    $found[] = $priority;
                }
            }
        }

        $this->assertContains(20, $found);
    }

    public function test_through_wc_customer_saved_methods_list(): void
    {
        // filter_tokens() of gateways built earlier in the suite would apply their own mode and
        // merchant: this test is about the saved-methods list only.
        remove_all_filters(self::TOKENS_HOOK);
        wp_set_current_user($this->customer_id);
        $token = new \WC_Payment_Token_CC();
        $token->set_gateway_id('payplug');
        $token->set_user_id($this->customer_id);
        $token->set_token('card_test_123');
        $token->set_last4('4242');
        $token->set_expiry_year((string) ((int) date('Y') + 2));
        $token->set_expiry_month('12');
        $token->set_card_type('visa');
        $token->save();

        $gateways_of = static function (): array {
            $list = wc_get_customer_saved_methods_list(get_current_user_id());

            return array_column(array_column($list['cc'] ?? [], 'method'), 'gateway');
        };

        $this->assertContains('payplug', $gateways_of());

        update_option('woocommerce_currency', 'USD');
        $this->assertNotContains('payplug', $gateways_of());
    }

    private function row(string $gateway, bool $default = false): array
    {
        return ['method' => ['gateway' => $gateway], 'is_default' => $default, 'actions' => []];
    }

    private function hide(array $list): array
    {
        return (new UhfSavedCards())->hide_retail_cards_outside_eur($list, $this->customer_id);
    }
}
