<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Card;

use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Card\CardRecorder;
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
        $reset_logger = fn () => new \WC_Logger();
        add_filter('woocommerce_logging_class', $reset_logger);
        wc_get_logger();
        remove_filter('woocommerce_logging_class', $reset_logger);
        if (UhfCard::check_table_exists()) {
            global $wpdb;
            $wpdb->query('TRUNCATE TABLE ' . $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards');
        }
        wp_delete_post($this->order->get_id(), true);
        parent::tearDown();
    }

    private function operation(string $alias_id = 'alias_op_1', string $alias_key = 'id'): array
    {
        return [
            'paymentMethod' => [
                $alias_key => $alias_id,
                'card' => ['network' => 'visa', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['selectedBrand' => 'VISA', 'validityDate' => $this->expiry_year() . '-12'],
            ],
        ];
    }

    private function expiry_year(): int
    {
        return (int) gmdate('Y') + 5;
    }

    private function mode(): string
    {
        return PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
    }

    private function capture_wc_logs(): \WC_Logger
    {
        $logger = new class () extends \WC_Logger {
            public $entries = [];

            public function log($level, $message, $context = []): void
            {
                $this->entries[] = ['level' => $level, 'message' => $message];
            }
        };
        $use_logger = fn () => $logger;
        add_filter('woocommerce_logging_class', $use_logger);
        wc_get_logger();
        remove_filter('woocommerce_logging_class', $use_logger);

        return $logger;
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

    public function testPersistsTheCardUnderTheStoredId(): void
    {
        (new CardRecorderFromOperation())->maybe_persist($this->order, $this->operation('alias_stored_1', 'storedId'));

        $card = UhfCard::find_by_alias('alias_stored_1', $this->mode());

        $this->assertNotNull($card);
        $this->assertSame(1, (int) $card->customer_id);
        $this->assertSame('VISA', $card->brand);
        $this->assertSame('4242', $card->last4);
    }

    public function testPrefersTheStoredIdOverTheId(): void
    {
        $operation = $this->operation('alias_stored_1', 'storedId');
        $operation['paymentMethod']['id'] = 'alias_op_1';

        (new CardRecorderFromOperation())->maybe_persist($this->order, $operation);

        $this->assertNotNull(UhfCard::find_by_alias('alias_stored_1', $this->mode()));
        $this->assertNull(UhfCard::find_by_alias('alias_op_1', $this->mode()));
        $this->assertCount(1, UhfCard::get_customer_cards(1, $this->mode()));
    }

    public function testFallsBackToTheIdWhenTheStoredIdIsEmpty(): void
    {
        $operation = $this->operation('alias_op_1');
        $operation['paymentMethod']['storedId'] = '';

        (new CardRecorderFromOperation())->maybe_persist($this->order, $operation);

        $this->assertNotNull(UhfCard::find_by_alias('alias_op_1', $this->mode()));
        $this->assertCount(1, UhfCard::get_customer_cards(1, $this->mode()));
    }

    public function testFallsBackToTheIdWhenTheStoredIdIsNotAString(): void
    {
        $operation = $this->operation('alias_op_1');
        $operation['paymentMethod']['storedId'] = true;

        (new CardRecorderFromOperation())->maybe_persist($this->order, $operation);

        $this->assertNotNull(UhfCard::find_by_alias('alias_op_1', $this->mode()));
        $this->assertNull(UhfCard::find_by_alias('1', $this->mode()));
        $this->assertCount(1, UhfCard::get_customer_cards(1, $this->mode()));
    }

    public function testSavesTheCardOnceWhenTheSameOperationIsProcessedTwice(): void
    {
        $operation = $this->operation('alias_stored_1', 'storedId');

        (new CardRecorderFromOperation())->maybe_persist($this->order, $operation);
        (new CardRecorderFromOperation())->maybe_persist($this->order, $operation);

        $this->assertCount(1, UhfCard::get_customer_cards(1, $this->mode()));
    }

    public function testDoesNotDuplicateACardAlreadySavedUnderTheSameAlias(): void
    {
        (new CardRecorder())->persist(1, 'alias_stored_1', $this->mode(), [], [
            'brand' => 'VISA',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => $this->expiry_year(),
        ]);

        (new CardRecorderFromOperation())->maybe_persist($this->order, $this->operation('alias_stored_1', 'storedId'));

        $this->assertCount(1, UhfCard::get_customer_cards(1, $this->mode()));
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

    /**
     * @dataProvider unusablePaymentMethods
     *
     * @param mixed $payment_method
     */
    public function testDoesNothingWhenThePaymentMethodCarriesNoUsableAlias($payment_method): void
    {
        $operation = $this->operation();
        $operation['paymentMethod'] = $payment_method;
        $logger = $this->capture_wc_logs();

        (new CardRecorderFromOperation())->maybe_persist($this->order, $operation);

        $this->assertSame([], UhfCard::get_customer_cards(1, $this->mode()));
        $this->assertContains([
            'level' => 'error',
            'message' => sprintf('UPC: save-card was requested but no alias id was found for order #%s.', $this->order->get_id()),
        ], $logger->entries);
    }

    public function unusablePaymentMethods(): array
    {
        $card = ['network' => 'visa', 'code6x4' => '424242XXXXXX4242'];
        $details = ['selectedBrand' => 'VISA', 'validityDate' => $this->expiry_year() . '-12'];

        return [
            'neither storedId nor id' => [['card' => $card, 'details' => $details]],
            'storedId and id both empty' => [['storedId' => '', 'id' => '', 'card' => $card, 'details' => $details]],
            'storedId null and no id' => [['storedId' => null, 'card' => $card, 'details' => $details]],
            'storedId not a scalar and no id' => [['storedId' => ['alias_stored_1'], 'card' => $card, 'details' => $details]],
            'storedId a boolean and no id' => [['storedId' => true, 'card' => $card, 'details' => $details]],
            'storedId an integer and no id' => [['storedId' => 12345, 'card' => $card, 'details' => $details]],
            'storedId a float and no id' => [['storedId' => 1.5, 'card' => $card, 'details' => $details]],
            'storedId empty and id an integer' => [['storedId' => '', 'id' => 12345, 'card' => $card, 'details' => $details]],
            'paymentMethod not an array' => ['alias_stored_1'],
        ];
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
