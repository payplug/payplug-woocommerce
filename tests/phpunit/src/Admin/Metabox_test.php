<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Payplug\PayplugWoocommerce\Admin\Metabox;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\UpcTransactionMetadata;
use Payplug\PayplugWoocommerce\Upc\UpcTransactionMetadataRecorder;
use PHPUnit\Framework\TestCase;

class Metabox_test extends TestCase
{
    /** @var \WC_Order */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = wc_create_order();
        $this->order->set_payment_method('payplug');
        $this->order->set_currency('CHF');
        $this->order->set_total(50.00);
        $this->order->save();
    }

    protected function tearDown(): void
    {
        $this->order->delete(true);
        parent::tearDown();
    }

    private function render(): string
    {
        ob_start();
        (new Metabox())->render(wc_get_order($this->order->get_id()));

        return (string) ob_get_clean();
    }

    private function save_upc_snapshot(array $meta): void
    {
        $this->order->update_meta_data(UpcTransactionMetadataRecorder::META_KEY, $meta);
        $this->order->save();
    }

    private function paid_uhf_snapshot(): array
    {
        $meta = UpcTransactionMetadata::initial('pay_uhf_1', 5000, true, true);
        $meta = UpcTransactionMetadata::mark_paid($meta, 1700000000);

        return UpcTransactionMetadata::with_card($meta, ['brand' => 'VISA', 'last4' => '4242', 'exp_month' => 7, 'exp_year' => 2030]);
    }

    private static function text(string $html): string
    {
        return (string) preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags($html)));
    }

    public function test_retail_order_output_is_unchanged(): void
    {
        PayplugWoocommerceHelper::save_transaction_metadata($this->order, [
            'transaction_id' => 'pay_retail',
            'paid' => true,
            'refunded' => false,
            'amount' => '5000',
            'amount_refunded' => '0',
            '3ds' => false,
            'live' => false,
            'paid_at' => '1700000000',
            'card_last4' => '1111',
            'card_exp_month' => '1',
            'card_exp_year' => '2031',
            'card_brand' => 'Visa',
            'card_country' => 'FR',
        ]);
        $this->save_upc_snapshot($this->paid_uhf_snapshot());

        $text = self::text($this->render());

        self::assertStringContainsString('pay_retail', $text);
        self::assertStringContainsString('Visa (FR)', $text);
        self::assertStringContainsString('**** **** **** 1111', $text);
        self::assertStringContainsString('01/2031', $text);
        self::assertStringContainsString('Test', $text);
        self::assertStringNotContainsString('pay_uhf_1', $text);
    }

    public function test_uhf_order_shows_id_status_amount_card_mask_3ds_expiry_and_mode(): void
    {
        $this->save_upc_snapshot($this->paid_uhf_snapshot());

        $text = self::text($this->render());

        self::assertStringNotContainsString('No metadata available', $text);
        self::assertStringContainsString('pay_uhf_1', $text);
        self::assertStringContainsString('Paid', $text);
        self::assertRegExp('/Amount\s*:\s*CHF\s*50\.00/', $text);
        self::assertStringContainsString(date_i18n(get_option('date_format', 'j F Y'), 1700000000), $text);
        self::assertStringContainsString('**** **** **** 4242', $text);
        self::assertStringContainsString('07/2030', $text);
        self::assertRegExp('/3 D Secure\s*:\s*Yes/', $text);
        self::assertStringContainsString('Live', $text);
    }

    public function test_uhf_card_row_has_no_empty_parentheses_without_country(): void
    {
        $this->save_upc_snapshot($this->paid_uhf_snapshot());

        $text = self::text($this->render());

        self::assertRegExp('/Credit card\s*:\s*VISA\s/', $text);
        self::assertStringNotContainsString('()', $text);
    }

    public function test_uhf_status_partially_refunded_then_refunded(): void
    {
        $meta = UpcTransactionMetadata::with_refund($this->paid_uhf_snapshot(), 1000);
        $this->save_upc_snapshot($meta);

        self::assertStringContainsString('Partially refunded', self::text($this->render()));

        $this->save_upc_snapshot(UpcTransactionMetadata::with_refund($meta, 4000));

        $text = self::text($this->render());
        self::assertStringContainsString('Refunded', $text);
        self::assertStringNotContainsString('Partially refunded', $text);
    }

    public function test_unpaid_uhf_order_shows_not_paid(): void
    {
        $this->save_upc_snapshot(UpcTransactionMetadata::initial('pay_uhf_1', 5000, false, false));

        $text = self::text($this->render());

        self::assertStringContainsString('Not Paid', $text);
        self::assertStringNotContainsString('Credit card', $text);
        self::assertStringNotContainsString('Card mask', $text);
    }

    public function test_order_without_any_metadata_still_shows_the_empty_message(): void
    {
        self::assertStringContainsString('No metadata available for the current order.', self::text($this->render()));
    }

    public function test_non_array_upc_meta_is_treated_as_empty(): void
    {
        $this->save_upc_snapshot([]);
        $this->order->update_meta_data(UpcTransactionMetadataRecorder::META_KEY, 'corrupt');
        $this->order->save();

        self::assertStringContainsString('No metadata available for the current order.', self::text($this->render()));
    }

    /**
     * @dataProvider partial_upc_meta_provider
     */
    public function test_partial_upc_meta_is_treated_as_empty(array $meta): void
    {
        $this->save_upc_snapshot($meta);

        self::assertStringContainsString('No metadata available for the current order.', self::text($this->render()));
    }

    public function partial_upc_meta_provider(): array
    {
        $complete = UpcTransactionMetadata::initial('pay_uhf_1', 5000, true, false);

        return [
            'only paid' => [['paid' => true]],
            'missing transaction_id' => [array_diff_key($complete, ['transaction_id' => true])],
            'missing amount' => [array_diff_key($complete, ['amount' => true])],
            'missing live' => [array_diff_key($complete, ['live' => true])],
        ];
    }

    public function test_upc_meta_with_only_the_required_keys_renders(): void
    {
        $this->save_upc_snapshot(['transaction_id' => 'pay_uhf_min', 'amount' => 5000, 'live' => false]);

        $text = self::text($this->render());

        self::assertStringNotContainsString('No metadata available', $text);
        self::assertStringContainsString('pay_uhf_min', $text);
        self::assertStringContainsString('Not Paid', $text);
        self::assertStringContainsString('Test', $text);
    }

    public function test_non_payplug_order_renders_nothing(): void
    {
        $this->order->set_payment_method('cod');
        $this->save_upc_snapshot($this->paid_uhf_snapshot());

        self::assertSame('', trim($this->render()));
    }
}
