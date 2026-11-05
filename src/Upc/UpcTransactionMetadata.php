<?php

namespace Payplug\PayplugWoocommerce\Upc;

/**
 * Payment details of a UHF order, in the array shape PayplugWoocommerceHelper::extract_transaction_metadata()
 * builds for a Retail payment, so Admin\Metabox renders both the same way. Amounts are in the
 * minor units of the order currency.
 *
 * The `3ds` key means "the payment creation answered with a 3-D Secure challenge redirect", not
 * "3-D Secure was used": it is inferred locally from the creation branch, because the payment
 * resource cannot be read back (getPayment() answers 404) and the creation response is not proven
 * to carry a 3DS field. A frictionless 3-D Secure payment, authenticated without a challenge,
 * therefore records `false` and the metabox shows "No", unlike a Retail payment whose `3ds` comes
 * from the API.
 */
final class UpcTransactionMetadata
{
    /**
     * @return array<string, mixed>
     */
    public static function initial(string $transaction_id, int $amount, bool $live, bool $three_ds): array
    {
        return [
            'transaction_id' => $transaction_id,
            'paid' => false,
            'refunded' => false,
            'amount' => $amount,
            'amount_refunded' => 0,
            '3ds' => $three_ds,
            'live' => $live,
            'paid_at' => 0,
            'card_last4' => '',
            'card_exp_month' => '',
            'card_exp_year' => '',
            'card_brand' => '',
            'card_country' => '',
        ];
    }

    /**
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed>
     */
    public static function mark_paid(array $meta, int $paid_at): array
    {
        $meta['paid'] = true;
        $meta['paid_at'] = $paid_at;

        return $meta;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $card CardDataExtractor::extract() shape
     *
     * @return array<string, mixed>
     */
    public static function with_card(array $meta, array $card): array
    {
        if (!empty($card['brand'])) {
            $meta['card_brand'] = (string) $card['brand'];
        }

        if (!empty($card['last4'])) {
            $meta['card_last4'] = (string) $card['last4'];
        }

        if (!empty($card['exp_month']) && !empty($card['exp_year'])) {
            $meta['card_exp_month'] = (int) $card['exp_month'];
            $meta['card_exp_year'] = (int) $card['exp_year'];
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $meta
     * @param int|null $refunded_cents null refunds everything not refunded yet
     *
     * @return array<string, mixed>
     */
    public static function with_refund(array $meta, ?int $refunded_cents): array
    {
        $amount = (int) $meta['amount'];
        $total = null === $refunded_cents
            ? $amount
            : min($amount, (int) $meta['amount_refunded'] + max(0, $refunded_cents));

        $meta['amount_refunded'] = $total;
        $meta['refunded'] = $amount > 0 && $total >= $amount;

        return $meta;
    }
}
