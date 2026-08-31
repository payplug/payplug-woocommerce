<?php

namespace Payplug\PayplugWoocommerce\Gateway\Oney;

/**
 * Normalizes the `configuration.oney` block of GET /account plus the plugin's admin Oney
 * settings into config the official Oney widget (loader.min.js / oneyMerchantApp.*) expects.
 *
 * Fee-mode combinations are NOT filtered by a per-x3/x4 admin checkbox - there isn't one.
 * The single admin toggle (`payment_methods.configuration.oney.with_fees`) picks between the
 * "with_fees" pair (x3_with_fees + x4_with_fees) and the "without_fees" pair, matching the
 * PDP/cart pop-in ("3x by default, switch to 4x"). Checkout resolves a single code per
 * gateway id instead, since each of the 4 WooCommerce Oney gateways already encodes its own
 * fee mode in its id.
 */
class OneyAccountMapper
{
    private const GATEWAY_TO_BUSINESS_CODE_KEY = [
        'oney_x3_with_fees' => 'x3_with_fees',
        'oney_x3_without_fees' => 'x3_without_fees',
        'oney_x4_with_fees' => 'x4_with_fees',
        'oney_x4_without_fees' => 'x4_without_fees',
    ];

    private bool $enabled = false;

    private ?string $merchant_guid = null;

    /** @var array<string, string> */
    private array $business_codes = [];

    /** @var string[] */
    private array $allowed_countries = ['ALL'];

    private int $min_amount = 0;

    private int $max_amount = 0;

    private bool $show_legal_notices = true;

    /**
     * @param array $account The raw GET /account response (or the relevant subset -
     *                       only `configuration.oney` is read).
     * @param array $oney_settings `payment_methods.configuration.oney` from the plugin's own
     *                             options (active, with_fees, custom_amounts, ...).
     * @param string $country Merchant/shop country (as resolved elsewhere in this plugin
     *                        by `PayplugWoocommerceHelper::getISOCountryCode()`), used to
     *                        pick the right `countries_metadata` entry - the live account
     *                        API nests `merchant_guid`/`oney_business_codes` per country
     *                        (a merchant can have distinct Oney registrations across its
     *                        `allowed_countries`), not flat at the top level as an earlier
     *                        revision of this class assumed.
     */
    public static function map(array $account, array $oney_settings, string $country = ''): self
    {
        $mapper = new self();

        $oney = $account['configuration']['oney'] ?? null;
        if (empty($oney) || empty($oney_settings['active'])) {
            return $mapper;
        }

        $mapper->enabled = true;

        $country_metadata = $oney['countries_metadata'][$country] ?? [];
        $mapper->merchant_guid = $country_metadata['merchant_guid'] ?? $oney['merchant_guid'] ?? null;
        $mapper->business_codes = $country_metadata['oney_business_codes'] ?? $oney['oney_business_codes'] ?? [];
        $mapper->allowed_countries = !empty($oney['allowed_countries']) ? $oney['allowed_countries'] : ['ALL'];
        $mapper->show_legal_notices = array_key_exists('show_legal_notices', $oney)
            ? (bool) $oney['show_legal_notices']
            : true;

        [$mapper->min_amount, $mapper->max_amount] = self::effective_bounds($oney, $oney_settings);

        return $mapper;
    }

    /**
     * Merges Oney's own min/max (account) with the merchant's optional local override
     * (custom_amounts), the effective range never widening beyond what Oney allows.
     *
     * Public so checkout-time eligibility (PayplugGatewayOney3x::do_check_oney_is_available())
     * can share this exact computation instead of re-deriving its own min/max from
     * custom_amounts alone - see PRE-3457 review discussion.
     *
     * @param array $oney `account.configuration.oney` (or the relevant subset -
     *                    `min_amounts`/`max_amounts` are read)
     * @param array $oney_settings `payment_methods.configuration.oney` from the plugin's own
     *                             options (`custom_amounts` is read)
     *
     * @return array{0: int, 1: int} [min_cents, max_cents]
     */
    public static function effective_bounds(array $oney, array $oney_settings): array
    {
        $account_min = (int) ($oney['min_amounts']['EUR'] ?? 0);
        $account_max = (int) ($oney['max_amounts']['EUR'] ?? 0);

        $custom = json_decode($oney_settings['custom_amounts'] ?? '{}', true);
        $custom = is_array($custom) ? $custom : [];

        $min = isset($custom['min']) ? max($account_min, (int) $custom['min']) : $account_min;
        $max = isset($custom['max']) ? min($account_max, (int) $custom['max']) : $account_max;

        return [$min, $max];
    }

    public function is_enabled(): bool
    {
        return $this->enabled;
    }

    public function get_merchant_guid(): ?string
    {
        return $this->merchant_guid;
    }

    /**
     * @return string[]
     */
    public function get_allowed_countries(): array
    {
        return $this->allowed_countries;
    }

    public function get_min_amount(): int
    {
        return $this->min_amount;
    }

    public function get_max_amount(): int
    {
        return $this->max_amount;
    }

    public function get_show_legal_notices(): bool
    {
        return $this->show_legal_notices;
    }

    /**
     * @param string $fee_mode 'with_fees' or 'without_fees'
     *
     * @return string[]
     */
    public function business_transaction_codes(string $fee_mode): array
    {
        $keys = 'without_fees' === $fee_mode
            ? ['x3_without_fees', 'x4_without_fees']
            : ['x3_with_fees', 'x4_with_fees'];

        $codes = [];
        foreach ($keys as $key) {
            if (!empty($this->business_codes[$key])) {
                $codes[] = $this->business_codes[$key];
            }
        }

        return $codes;
    }

    public function business_transaction_code_for_gateway(string $gateway_id): ?string
    {
        $key = self::GATEWAY_TO_BUSINESS_CODE_KEY[$gateway_id] ?? null;

        return $key ? ($this->business_codes[$key] ?? null) : null;
    }

    public function is_eligible(string $country, int $amount_in_cents): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (!in_array('ALL', $this->allowed_countries, true) && !in_array($country, $this->allowed_countries, true)) {
            return false;
        }

        return $amount_in_cents >= $this->min_amount && $amount_in_cents <= $this->max_amount;
    }
}
