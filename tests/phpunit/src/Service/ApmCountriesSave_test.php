<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ApmCountriesSave;
use PHPUnit\Framework\TestCase;

class ApmCountriesSave_test extends TestCase
{
    private const SHOP = ['BE', 'DE', 'ES', 'FR', 'IT', 'US'];

    public function test_unrestricted_merchant_saving_every_country_stores_nothing(): void
    {
        self::assertSame($this->value(null), $this->normalise(null, ['FR', 'IT', 'BE'], ['BE', 'FR', 'IT']));
    }

    public function test_unrestricted_merchant_saving_a_subset_stores_it(): void
    {
        self::assertSame($this->value(['IT']), $this->normalise(null, ['it'], ['BE', 'FR', 'IT']));
    }

    public function test_restriction_removed_when_the_whole_api_list_is_ticked(): void
    {
        self::assertSame($this->value(null), $this->normalise(['IT'], ['BE', 'FR', 'IT'], ['BE', 'FR', 'IT']));
    }

    public function test_restriction_removed_when_every_shop_country_is_ticked_with_api_all(): void
    {
        $result = ApmCountriesSave::normalise(['IT'], self::SHOP, ApmCountriesSave::proposable(null, self::SHOP), null, self::SHOP);

        self::assertSame($this->value(null), $result);
    }

    public function test_restriction_replaced_by_another_selection(): void
    {
        self::assertSame($this->value(['BE', 'FR']), $this->normalise(['IT'], ['BE', 'FR'], ['BE', 'FR', 'IT']));
    }

    public function test_empty_restriction_with_everything_ticked_again_stores_the_list(): void
    {
        self::assertSame($this->value(['BE', 'FR', 'IT']), $this->normalise([], ['BE', 'FR', 'IT'], ['BE', 'FR', 'IT']));
    }

    public function test_no_proposable_country_keeps_the_stored_value(): void
    {
        $result = ApmCountriesSave::normalise(['NL'], [], [], ['NL'], self::SHOP);

        self::assertTrue($result['skip']);
        self::assertNull($result['error']);
    }

    public function test_country_outside_the_proposable_list_is_refused(): void
    {
        $result = $this->normalise(null, ['FR', 'NL'], ['BE', 'FR', 'IT']);

        self::assertSame('country_invalid', $result['error']);
    }

    public function test_malformed_country_is_refused(): void
    {
        self::assertSame('country_invalid', $this->normalise(null, [['FR']], ['BE', 'FR', 'IT'])['error']);
        self::assertSame('country_invalid', $this->normalise(null, [''], ['BE', 'FR', 'IT'])['error']);
    }

    public function test_exclusion_is_never_widened_when_a_country_comes_back(): void
    {
        // API FR + IT, merchant keeps IT, then FR is disabled in the shop: only IT is proposed and sent back.
        self::assertSame($this->value(['IT']), $this->normalise(['IT'], ['IT'], ['FR', 'IT'], ['IT']));
    }

    public function test_empty_selection_hides_for_everybody(): void
    {
        self::assertSame($this->value([]), $this->normalise(null, [], ['BE', 'FR', 'IT']));
        self::assertSame($this->value([]), $this->normalise(['IT'], [], ['BE', 'FR', 'IT']));
    }

    public function test_duplicates_are_removed(): void
    {
        self::assertSame($this->value(['FR']), $this->normalise(null, ['FR', 'fr'], ['BE', 'FR', 'IT']));
    }

    public function test_proposable_countries(): void
    {
        self::assertSame(self::SHOP, ApmCountriesSave::proposable(null, self::SHOP));
        self::assertSame(['ES', 'FR'], ApmCountriesSave::proposable(['NL', 'FR', 'ES'], self::SHOP));
        self::assertSame([], ApmCountriesSave::proposable(['NL'], self::SHOP));
    }

    public function test_removed_countries(): void
    {
        self::assertSame(['NL'], ApmCountriesSave::removed(['FR', 'NL'], ['BE', 'FR']));
        self::assertSame([], ApmCountriesSave::removed(null, ['BE', 'FR']));
        self::assertSame([], ApmCountriesSave::removed([], ['BE', 'FR']));
    }

    /**
     * @param string[]|null $api API list, null = ALL
     * @param string[]|null $shop shop countries, self::SHOP by default
     */
    private function normalise(?array $stored, array $submitted, ?array $api, ?array $shop = null): array
    {
        $shop = $shop ?? self::SHOP;

        return ApmCountriesSave::normalise($stored, $submitted, ApmCountriesSave::proposable($api, $shop), $api, $shop);
    }

    private function value(?array $value): array
    {
        return ['skip' => false, 'error' => null, 'value' => $value];
    }
}
