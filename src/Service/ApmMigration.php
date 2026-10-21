<?php

namespace Payplug\PayplugWoocommerce\Service;

use Payplug\PayplugWoocommerce\Gateway\AccountGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

/**
 * One-off update step of the PPRO display restrictions, gated by apm_restrictions_version:
 * refresh of a cache written without its mode, then normalisation of the stored Scalapay range
 * against the cached API bounds. The marker is only set once the cache is filled; a failed
 * refresh is retried at most once per RETRY_DELAY.
 */
class ApmMigration
{
    public const VERSION = '1';

    public const RETRY_TRANSIENT = 'payplug_apm_migration_retry';

    public const RETRY_DELAY = 3600;

    /**
     * @var callable(): mixed
     */
    private $load;

    /**
     * @var callable(array): void
     */
    private $save;

    /**
     * @var callable(): bool
     */
    private $refresh;

    /**
     * @var callable(string, string): void
     */
    private $log;

    /**
     * @var callable(): bool
     */
    private $retry_blocked;

    /**
     * @var callable(): void
     */
    private $block_retry;

    public function __construct(callable $load, callable $save, callable $refresh, callable $log, callable $retry_blocked, callable $block_retry)
    {
        $this->load = $load;
        $this->save = $save;
        $this->refresh = $refresh;
        $this->log = $log;
        $this->retry_blocked = $retry_blocked;
        $this->block_retry = $block_retry;
    }

    public static function create(): self
    {
        return new self(
            static fn () => (new Configuration())->get_options(),
            static function (array $options): void {
                (new Configuration())->update_options($options);
            },
            static fn (): bool => (new AccountGateway())->refresh_payment_permissions(),
            static function (string $message, string $level): void {
                PayplugGateway::log($message, $level);
            },
            static fn (): bool => false !== get_transient(self::RETRY_TRANSIENT),
            static function (): void {
                set_transient(self::RETRY_TRANSIENT, 1, self::RETRY_DELAY);
            }
        );
    }

    /**
     * Never throws: a failure is logged and the step is retried on the next load. A refresh that
     * fails or returns false leaves the marker unset and is retried after RETRY_DELAY.
     *
     * @return bool whether the step completed (marker set)
     */
    public function run(): bool
    {
        try {
            $options = ($this->load)();
            if (!is_array($options) || empty($options['email'])) {
                return false;
            }
            if (self::VERSION === ($options['apm_restrictions_version'] ?? '')) {
                return false;
            }

            if ('' === ($options['apm_cache_mode'] ?? '')) {
                if (($this->retry_blocked)()) {
                    return false;
                }

                try {
                    ($this->refresh)();
                } catch (\Throwable $e) {
                    ($this->log)(sprintf('APM limits refresh failed during update: %s', $e->getMessage()), 'warning');
                }
                $options = ($this->load)();
                if (!is_array($options)) {
                    return false;
                }
            }

            $options = self::normalise_scalapay_custom_amounts($options);

            if ('' === ($options['apm_cache_mode'] ?? '')) {
                ($this->block_retry)();
                ($this->save)($options);

                return false;
            }

            $options['apm_restrictions_version'] = self::VERSION;
            ($this->save)($options);

            return true;
        } catch (\Throwable $e) {
            ($this->log)(sprintf('APM restrictions update failed: %s', $e->getMessage()), 'error');

            return false;
        }
    }

    /**
     * Keeps the stored Scalapay min/max only when strictly inside the cached API bounds, and drops
     * both when min is not below max. Unchanged when the API bounds are not cached.
     */
    public static function normalise_scalapay_custom_amounts(array $options): array
    {
        $stored = $options['payment_methods']['configuration']['scalapay']['custom_amounts'] ?? null;
        $limits = ApmSettings::config_for($options, 'scalapay')['limits'];
        if (!is_string($stored) || null === $limits) {
            return $options;
        }

        $amounts = json_decode($stored, true);
        if (!is_array($amounts)) {
            return $options;
        }

        $kept = [];
        foreach (['min', 'max'] as $bound) {
            if (!isset($amounts[$bound]) || !is_numeric($amounts[$bound])) {
                continue;
            }
            $value = (int) $amounts[$bound];
            if ($value > $limits['min'] && $value < $limits['max']) {
                $kept[$bound] = $value;
            }
        }
        if (isset($kept['min'], $kept['max']) && $kept['min'] >= $kept['max']) {
            $kept = [];
        }

        $options['payment_methods']['configuration']['scalapay']['custom_amounts'] = empty($kept) ? '{}' : json_encode($kept);

        return $options;
    }
}
