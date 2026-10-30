<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Card;

use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Card\CardRecorderFromOperation;
use PHPUnit\Framework\TestCase;

class CardRecorderFromOperation_test extends TestCase
{
    /** @var \WC_Order */
    private $order;

    protected function setUp(): void
    {
        $this->order = wc_create_order();
        $this->order->set_customer_id(1);
        $this->order->update_meta_data('_payplug_uhf_save_card', '1');
        $this->order->save();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (UhfCard::check_table_exists()) {
            global $wpdb;
            $wpdb->query('TRUNCATE TABLE ' . $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards');
        }
        wp_delete_post($this->order->get_id(), true);
        parent::tearDown();
    }

    private function operation(string $alias_id = 'alias_op_1'): array
    {
        return [
            'paymentMethod' => [
                'id' => $alias_id,
                'card' => ['network' => 'visa', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['selectedBrand' => 'VISA', 'validityDate' => '2099-12'],
            ],
        ];
    }

    private function mode(): string
    {
        return PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
    }

    public function testPersistsTheCardWhenSaveCardWasRequested(): void
    {
        (new CardRecorderFromOperation())->maybe_persist($this->order, $this->operation());

        $card = UhfCard::find_by_alias('alias_op_1', $this->mode());

        $this->assertNotNull($card);
        $this->assertSame(1, (int) $card->customer_id);
        $this->assertSame('VISA', $card->brand);
        $this->assertSame('4242', $card->last4);
    }

    public function testDoesNothingWhenSaveCardWasNotRequested(): void
    {
        $this->order->delete_meta_data('_payplug_uhf_save_card');
        $this->order->save();

        (new CardRecorderFromOperation())->maybe_persist($this->order, $this->operation());

        $this->assertNull(UhfCard::find_by_alias('alias_op_1', $this->mode()));
    }

    public function testDoesNothingForAGuestOrder(): void
    {
        $this->order->set_customer_id(0);
        $this->order->save();

        (new CardRecorderFromOperation())->maybe_persist($this->order, $this->operation());

        $this->assertNull(UhfCard::find_by_alias('alias_op_1', $this->mode()));
    }

    public function testDoesNothingWhenTheOperationCarriesNoAliasId(): void
    {
        $operation = $this->operation();
        unset($operation['paymentMethod']['id']);

        (new CardRecorderFromOperation())->maybe_persist($this->order, $operation);

        $this->assertSame([], UhfCard::get_customer_cards(1, $this->mode()));
    }

    public function testDoesNothingWhenTheCardDataIsRejected(): void
    {
        $operation = $this->operation();
        $operation['paymentMethod']['card']['network'] = 'amex';
        $operation['paymentMethod']['details']['selectedBrand'] = 'AMEX';

        (new CardRecorderFromOperation())->maybe_persist($this->order, $operation);

        $this->assertNull(UhfCard::find_by_alias('alias_op_1', $this->mode()));
    }
}
