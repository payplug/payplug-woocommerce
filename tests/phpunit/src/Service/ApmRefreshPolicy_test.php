<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ApmEligibility;
use Payplug\PayplugWoocommerce\Service\ApmRefreshPolicy;
use PHPUnit\Framework\TestCase;

class ApmRefreshPolicy_test extends TestCase
{
    public function test_no_refresh_when_every_apm_is_cached_for_the_target_mode(): void
    {
        self::assertFalse(ApmRefreshPolicy::needs_refresh_on_save($this->options('live'), 'live'));
        self::assertFalse(ApmRefreshPolicy::needs_refresh_on_save($this->options('test'), 'test'));
    }

    public function test_refresh_on_mode_change(): void
    {
        self::assertTrue(ApmRefreshPolicy::needs_refresh_on_save($this->options('live'), 'test'));
        self::assertTrue(ApmRefreshPolicy::needs_refresh_on_save($this->options('test'), 'live'));
    }

    public function test_refresh_when_the_cache_mode_is_unknown(): void
    {
        self::assertTrue(ApmRefreshPolicy::needs_refresh_on_save($this->options(''), 'live'));

        $options = $this->options('live');
        unset($options['apm_cache_mode']);
        self::assertTrue(ApmRefreshPolicy::needs_refresh_on_save($options, 'live'));
    }

    /**
     * @dataProvider unusable_entries_provider
     *
     * @param mixed $entry
     */
    public function test_refresh_when_one_apm_has_no_usable_limits($entry): void
    {
        $options = $this->options('live');
        $options['payment_methods']['permissions']['wero'] = $entry;

        self::assertTrue(ApmRefreshPolicy::needs_refresh_on_save($options, 'live'));
    }

    public function unusable_entries_provider(): array
    {
        return [
            'empty' => [[]],
            'malformed json' => [['countries' => '{bad', 'amounts' => '{"min":{"EUR":30},"max":{"EUR":99}}']],
            'no EUR bound' => [['countries' => '["BE"]', 'amounts' => '{"min":{"USD":30},"max":{"USD":99}}']],
            'not an array' => ['x'],
        ];
    }

    public function test_refresh_when_one_apm_entry_is_missing(): void
    {
        $options = $this->options('live');
        unset($options['payment_methods']['permissions']['scalapay']);

        self::assertTrue(ApmRefreshPolicy::needs_refresh_on_save($options, 'live'));
    }

    public function test_other_payment_methods_have_no_effect(): void
    {
        $options = $this->options('live');
        $options['payment_methods']['permissions']['giropay'] = [];
        $options['payment_methods']['permissions']['payplug'] = [];

        self::assertFalse(ApmRefreshPolicy::needs_refresh_on_save($options, 'live'));
    }

    public function test_refresh_without_any_permissions(): void
    {
        self::assertTrue(ApmRefreshPolicy::needs_refresh_on_save(['apm_cache_mode' => 'live'], 'live'));
    }

    private function options(string $cache_mode): array
    {
        $permissions = [];
        foreach (ApmEligibility::APMS as $apm) {
            $permissions[$apm] = [
                'enabled' => true,
                'countries' => '["ALL"]',
                'amounts' => '{"min":{"EUR":30},"max":{"EUR":2000000}}',
            ];
        }

        return [
            'apm_cache_mode' => $cache_mode,
            'payment_methods' => ['permissions' => $permissions],
        ];
    }
}
