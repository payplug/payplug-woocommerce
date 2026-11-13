<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\UpcTransactionMetadata;
use PHPUnit\Framework\TestCase;

class UpcTransactionMetadata_test extends TestCase
{
    public function test_initial_builds_the_retail_shaped_unpaid_snapshot(): void
    {
        self::assertSame(
            [
                'transaction_id' => 'pay_1',
                'paid' => false,
                'refunded' => false,
                'amount' => 5000,
                'amount_refunded' => 0,
                '3ds' => false,
                'live' => true,
                'paid_at' => 0,
                'card_last4' => '',
                'card_exp_month' => '',
                'card_exp_year' => '',
                'card_brand' => '',
                'card_country' => '',
            ],
            UpcTransactionMetadata::initial('pay_1', 5000, true, false)
        );
    }

    public function test_initial_keeps_the_three_ds_and_test_mode_flags(): void
    {
        $meta = UpcTransactionMetadata::initial('pay_1', 5000, false, true);

        self::assertTrue($meta['3ds']);
        self::assertFalse($meta['live']);
    }

    public function test_mark_paid_sets_paid_and_date_only(): void
    {
        $meta = UpcTransactionMetadata::initial('pay_1', 5000, true, true);

        $paid = UpcTransactionMetadata::mark_paid($meta, 1700000000);

        self::assertTrue($paid['paid']);
        self::assertSame(1700000000, $paid['paid_at']);
        self::assertSame(
            array_diff_key($meta, ['paid' => null, 'paid_at' => null]),
            array_diff_key($paid, ['paid' => null, 'paid_at' => null])
        );
    }

    /**
     * @dataProvider with_card_provider
     */
    public function test_with_card_merges_non_empty_values_and_keeps_existing_ones(array $existing, array $card, array $expected): void
    {
        $meta = array_merge(UpcTransactionMetadata::initial('pay_1', 5000, true, false), $existing);

        $result = UpcTransactionMetadata::with_card($meta, $card);

        foreach ($expected as $key => $value) {
            self::assertSame($value, $result[$key], $key);
        }
        self::assertSame('pay_1', $result['transaction_id']);
        self::assertSame('', $result['card_country']);
    }

    public function with_card_provider(): array
    {
        return [
            'full card' => [
                [],
                ['brand' => 'VISA', 'last4' => '4242', 'exp_month' => 7, 'exp_year' => 2030],
                ['card_brand' => 'VISA', 'card_last4' => '4242', 'card_exp_month' => 7, 'card_exp_year' => 2030],
            ],
            'empty brand keeps the previous brand' => [
                ['card_brand' => 'MASTERCARD'],
                ['brand' => '', 'last4' => '1111', 'exp_month' => 1, 'exp_year' => 2031],
                ['card_brand' => 'MASTERCARD', 'card_last4' => '1111', 'card_exp_month' => 1, 'card_exp_year' => 2031],
            ],
            'zero expiry keeps the previous expiry' => [
                ['card_exp_month' => 7, 'card_exp_year' => 2030, 'card_last4' => '4242'],
                ['brand' => 'VISA', 'last4' => '', 'exp_month' => 0, 'exp_year' => 0],
                ['card_brand' => 'VISA', 'card_last4' => '4242', 'card_exp_month' => 7, 'card_exp_year' => 2030],
            ],
            'missing keys change nothing' => [
                ['card_brand' => 'VISA'],
                [],
                ['card_brand' => 'VISA', 'card_last4' => '', 'card_exp_month' => '', 'card_exp_year' => ''],
            ],
        ];
    }

    /**
     * @dataProvider with_refund_provider
     *
     * @param array<int, int|null> $refunds
     */
    public function test_with_refund_partial_then_total_flags(array $refunds, bool $refunded, int $amount_refunded): void
    {
        $meta = UpcTransactionMetadata::mark_paid(UpcTransactionMetadata::initial('pay_1', 5000, true, false), 1700000000);

        foreach ($refunds as $cents) {
            $meta = UpcTransactionMetadata::with_refund($meta, $cents);
        }

        self::assertSame($refunded, $meta['refunded']);
        self::assertSame($amount_refunded, $meta['amount_refunded']);
        self::assertTrue($meta['paid']);
        self::assertSame(5000, $meta['amount']);
    }

    public function with_refund_provider(): array
    {
        return [
            'partial' => [[1000], false, 1000],
            'partial then the rest' => [[1000, 4000], true, 5000],
            'two partials' => [[1000, 1500], false, 2500],
            'no amount refunds everything left' => [[null], true, 5000],
            'partial then no amount' => [[1000, null], true, 5000],
            'over refund is capped at the amount' => [[6000], true, 5000],
            'negative amount is ignored' => [[-100], false, 0],
        ];
    }

    public function test_with_refund_on_a_zero_amount_snapshot_is_never_flagged_refunded(): void
    {
        $meta = UpcTransactionMetadata::with_refund(UpcTransactionMetadata::initial('pay_1', 0, true, false), null);

        self::assertFalse($meta['refunded']);
        self::assertSame(0, $meta['amount_refunded']);
    }
}
