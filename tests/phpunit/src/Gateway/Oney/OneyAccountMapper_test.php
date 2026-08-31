<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway\Oney;

use Payplug\PayplugWoocommerce\Gateway\Oney\OneyAccountMapper;
use PHPUnit\Framework\TestCase;

class OneyAccountMapper_test extends TestCase
{
    private function base_account(array $oney_overrides = []): array
    {
        return [
            'configuration' => [
                'oney' => array_merge([
                    'enabled' => true,
                    'allowed_countries' => ['FR', 'BE'],
                    'min_amounts' => ['EUR' => 10000],
                    'max_amounts' => ['EUR' => 300000],
                    'show_legal_notices' => true,
                    'countries_metadata' => [
                        'FR' => [
                            'merchant_guid' => 'd5104abda4e74c45a78c08901107bb08',
                            'oney_business_codes' => [
                                'x3_with_fees' => 'W3135',
                                'x4_with_fees' => 'W4144',
                                'x3_without_fees' => 'DLN04',
                                'x4_without_fees' => 'DLN05',
                            ],
                            'psp_guid' => '0699c7373e544d038d930072e10cd575',
                        ],
                    ],
                ], $oney_overrides),
            ],
        ];
    }

    private function base_settings(array $overrides = []): array
    {
        return array_merge([
            'active' => true,
            'with_fees' => true,
            'custom_amounts' => '{"min":10000,"max":300000}',
        ], $overrides);
    }

    public function test_business_transaction_codes_with_fees(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertSame(['W3135', 'W4144'], $mapper->business_transaction_codes('with_fees'));
    }

    public function test_business_transaction_codes_without_fees(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertSame(['DLN04', 'DLN05'], $mapper->business_transaction_codes('without_fees'));
    }

    public function test_business_transaction_codes_drops_missing_codes(): void
    {
        $account = $this->base_account(['countries_metadata' => ['FR' => ['oney_business_codes' => ['x3_with_fees' => 'W3135']]]]);
        $mapper = OneyAccountMapper::map($account, $this->base_settings(), 'FR');

        self::assertSame(['W3135'], $mapper->business_transaction_codes('with_fees'));
    }

    /**
     * @dataProvider gateway_business_code_provider
     */
    public function test_business_transaction_code_for_gateway(string $gateway_id, string $expected_code): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertSame($expected_code, $mapper->business_transaction_code_for_gateway($gateway_id));
    }

    public function gateway_business_code_provider(): array
    {
        return [
            'x3 with fees' => ['oney_x3_with_fees', 'W3135'],
            'x4 with fees' => ['oney_x4_with_fees', 'W4144'],
            'x3 without fees' => ['oney_x3_without_fees', 'DLN04'],
            'x4 without fees' => ['oney_x4_without_fees', 'DLN05'],
        ];
    }

    public function test_business_transaction_code_for_gateway_returns_null_for_unknown_gateway(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertNull($mapper->business_transaction_code_for_gateway('unknown_gateway'));
    }

    public function test_min_max_uses_account_bounds_when_no_custom_override(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(['custom_amounts' => '{}']), 'FR');

        self::assertSame(10000, $mapper->get_min_amount());
        self::assertSame(300000, $mapper->get_max_amount());
    }

    public function test_min_max_tightens_with_custom_override_inside_account_bounds(): void
    {
        $mapper = OneyAccountMapper::map(
            $this->base_account(),
            $this->base_settings(['custom_amounts' => '{"min":15000,"max":200000}']),
            'FR'
        );

        self::assertSame(15000, $mapper->get_min_amount());
        self::assertSame(200000, $mapper->get_max_amount());
    }

    public function test_min_max_never_widens_beyond_account_bounds(): void
    {
        $mapper = OneyAccountMapper::map(
            $this->base_account(),
            $this->base_settings(['custom_amounts' => '{"min":100,"max":900000}']),
            'FR'
        );

        self::assertSame(10000, $mapper->get_min_amount());
        self::assertSame(300000, $mapper->get_max_amount());
    }

    /**
     * effective_bounds() is public so PayplugGatewayOney3x::do_check_oney_is_available() can
     * share it for checkout-time validation instead of re-deriving min/max on its own
     * (PRE-3457 review) - covered directly here since that call site passes account/settings
     * data that may not go through map()'s enabled-gating (e.g. account data unavailable).
     */
    public function test_effective_bounds_uses_account_when_no_custom_override(): void
    {
        $oney = $this->base_account()['configuration']['oney'];

        self::assertSame([10000, 300000], OneyAccountMapper::effective_bounds($oney, ['custom_amounts' => '{}']));
    }

    public function test_effective_bounds_never_widens_beyond_account_bounds(): void
    {
        $oney = $this->base_account()['configuration']['oney'];

        self::assertSame(
            [10000, 300000],
            OneyAccountMapper::effective_bounds($oney, ['custom_amounts' => '{"min":100,"max":900000}'])
        );
    }

    public function test_effective_bounds_defaults_to_zero_when_account_data_missing(): void
    {
        self::assertSame([0, 0], OneyAccountMapper::effective_bounds([], ['custom_amounts' => '{}']));
    }

    public function test_show_legal_notices_defaults_true_when_absent(): void
    {
        $account = $this->base_account();
        unset($account['configuration']['oney']['show_legal_notices']);
        $mapper = OneyAccountMapper::map($account, $this->base_settings(), 'FR');

        self::assertTrue($mapper->get_show_legal_notices());
    }

    public function test_show_legal_notices_respects_explicit_false(): void
    {
        $mapper = OneyAccountMapper::map(
            $this->base_account(['show_legal_notices' => false]),
            $this->base_settings(),
            'FR'
        );

        self::assertFalse($mapper->get_show_legal_notices());
    }

    public function test_allowed_countries_defaults_to_all_when_absent(): void
    {
        $account = $this->base_account();
        unset($account['configuration']['oney']['allowed_countries']);
        $mapper = OneyAccountMapper::map($account, $this->base_settings(), 'FR');

        self::assertSame(['ALL'], $mapper->get_allowed_countries());
    }

    public function test_is_eligible_true_within_bounds_and_allowed_country(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertTrue($mapper->is_eligible('FR', 50000));
    }

    public function test_is_eligible_false_when_country_not_allowed(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertFalse($mapper->is_eligible('DE', 50000));
    }

    public function test_is_eligible_true_when_allowed_countries_is_all_sentinel(): void
    {
        $account = $this->base_account();
        unset($account['configuration']['oney']['allowed_countries']);
        $mapper = OneyAccountMapper::map($account, $this->base_settings(), 'FR');

        self::assertTrue($mapper->is_eligible('DE', 50000));
    }

    public function test_is_eligible_false_below_min(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertFalse($mapper->is_eligible('FR', 9999));
    }

    public function test_is_eligible_false_above_max(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertFalse($mapper->is_eligible('FR', 300001));
    }

    public function test_map_returns_disabled_when_no_oney_block(): void
    {
        $mapper = OneyAccountMapper::map(['configuration' => []], $this->base_settings(), 'FR');

        self::assertFalse($mapper->is_enabled());
        self::assertFalse($mapper->is_eligible('FR', 50000));
        self::assertSame([], $mapper->business_transaction_codes('with_fees'));
    }

    public function test_map_returns_disabled_when_admin_oney_inactive(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(['active' => false]), 'FR');

        self::assertFalse($mapper->is_enabled());
    }

    public function test_get_merchant_guid(): void
    {
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'FR');

        self::assertSame('d5104abda4e74c45a78c08901107bb08', $mapper->get_merchant_guid());
    }

    public function test_merchant_guid_and_codes_are_null_empty_for_country_absent_from_metadata(): void
    {
        // Real accounts can allow a country (e.g. a DOM-TOM in allowed_countries) without yet
        // having a countries_metadata entry for it - is_enabled() stays true (the oney block
        // itself is active), but there is nothing to feed the widget for that country.
        $mapper = OneyAccountMapper::map($this->base_account(), $this->base_settings(), 'BE');

        self::assertTrue($mapper->is_enabled());
        self::assertNull($mapper->get_merchant_guid());
        self::assertSame([], $mapper->business_transaction_codes('with_fees'));
        self::assertNull($mapper->business_transaction_code_for_gateway('oney_x3_with_fees'));
    }

    public function test_falls_back_to_legacy_flat_shape_when_countries_metadata_absent(): void
    {
        // Backward compatibility with the shape documented in the original PBR/spec, in case
        // some accounts (or a future API revision) return merchant_guid/oney_business_codes
        // flat at the top level instead of nested under countries_metadata.
        $account = $this->base_account();
        unset($account['configuration']['oney']['countries_metadata']);
        $account['configuration']['oney']['merchant_guid'] = 'legacy-guid';
        $account['configuration']['oney']['oney_business_codes'] = ['x3_with_fees' => 'LEGACY3'];

        $mapper = OneyAccountMapper::map($account, $this->base_settings(), 'FR');

        self::assertSame('legacy-guid', $mapper->get_merchant_guid());
        self::assertSame(['LEGACY3'], $mapper->business_transaction_codes('with_fees'));
    }
}
