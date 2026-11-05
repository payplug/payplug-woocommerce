<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ApmMigration;
use PHPUnit\Framework\TestCase;

class ApmMigration_test extends TestCase
{
    /**
     * @var array|null
     */
    private $stored;

    /**
     * @var int
     */
    private $refresh_calls = 0;

    /**
     * @var bool
     */
    private $retry_blocked = false;

    /**
     * @var int
     */
    private $retry_blocks = 0;

    /**
     * @var array[]
     */
    private $logs = [];

    /**
     * @dataProvider scalapay_amounts_provider
     */
    public function test_normalises_scalapay_custom_amounts(string $stored, string $expected): void
    {
        $options = ApmMigration::normalise_scalapay_custom_amounts($this->options(['custom_amounts' => $stored]));

        self::assertSame($expected, $options['payment_methods']['configuration']['scalapay']['custom_amounts']);
    }

    public function scalapay_amounts_provider(): array
    {
        return [
            'within bounds' => ['{"min":1000,"max":150000}', '{"min":1000,"max":150000}'],
            'min below the API min' => ['{"min":400,"max":150000}', '{"max":150000}'],
            'equal to the API bounds' => ['{"min":500,"max":200000}', '{}'],
            'max above the API max' => ['{"min":1000,"max":900000}', '{"min":1000}'],
            'min above the API max' => ['{"min":300000,"max":150000}', '{"max":150000}'],
            'max below the API min' => ['{"min":1000,"max":100}', '{"min":1000}'],
            'min not below max' => ['{"min":150000,"max":1000}', '{}'],
            'min equal to max' => ['{"min":1000,"max":1000}', '{}'],
            'already empty' => ['{}', '{}'],
            'only min' => ['{"min":1000}', '{"min":1000}'],
            'non numeric value' => ['{"min":"abc","max":150000}', '{"max":150000}'],
        ];
    }

    public function test_keeps_stored_values_without_limits_in_cache(): void
    {
        $options = $this->options(['custom_amounts' => '{"min":400,"max":900000}']);
        $options['payment_methods']['permissions']['scalapay'] = [];

        self::assertSame($options, ApmMigration::normalise_scalapay_custom_amounts($options));
    }

    public function test_keeps_malformed_value_untouched(): void
    {
        $options = $this->options(['custom_amounts' => 'nope']);

        self::assertSame($options, ApmMigration::normalise_scalapay_custom_amounts($options));
    }

    public function test_touches_nothing_else(): void
    {
        $options = $this->options(['custom_amounts' => '{"min":400,"max":150000}']);
        $expected = $options;
        $expected['payment_methods']['configuration']['scalapay']['custom_amounts'] = '{"max":150000}';

        self::assertSame($expected, ApmMigration::normalise_scalapay_custom_amounts($options));
    }

    public function test_never_writes_a_key_that_was_not_stored(): void
    {
        $options = $this->options([]);
        unset($options['payment_methods']['configuration']['scalapay']['custom_amounts']);

        self::assertSame($options, ApmMigration::normalise_scalapay_custom_amounts($options));
        self::assertSame([], ApmMigration::normalise_scalapay_custom_amounts([]));
    }

    public function test_run_refreshes_a_cache_written_before_the_marker_then_marks(): void
    {
        $this->stored = $this->options(['custom_amounts' => '{"min":400,"max":150000}']);

        self::assertTrue($this->migration(function (): bool {
            $this->stored['apm_cache_mode'] = 'live';

            return true;
        })->run());

        self::assertSame(1, $this->refresh_calls);
        self::assertSame(ApmMigration::VERSION, $this->stored['apm_restrictions_version']);
        self::assertSame('live', $this->stored['apm_cache_mode']);
        self::assertSame('{"max":150000}', $this->stored['payment_methods']['configuration']['scalapay']['custom_amounts']);
    }

    public function test_refresher_exception_does_not_block_the_update_and_is_retried_later(): void
    {
        $this->stored = $this->options([]);

        $result = $this->migration(function (): bool {
            throw new \RuntimeException('API unreachable');
        })->run();

        self::assertFalse($result);
        self::assertArrayNotHasKey('apm_restrictions_version', $this->stored);
        self::assertSame(1, $this->retry_blocks);
        self::assertSame('warning', $this->logs[0]['level']);
    }

    public function test_failed_refresh_leaves_the_marker_unset_and_blocks_the_retry_for_a_while(): void
    {
        $this->stored = $this->options([]);

        self::assertFalse($this->migration(fn (): bool => false)->run());

        self::assertSame(1, $this->refresh_calls);
        self::assertSame(1, $this->retry_blocks);
        self::assertArrayNotHasKey('apm_restrictions_version', $this->stored);
    }

    public function test_failed_refresh_still_saves_the_scalapay_normalisation(): void
    {
        $this->stored = $this->options(['custom_amounts' => '{"min":400,"max":150000}']);

        self::assertFalse($this->migration(fn (): bool => false)->run());

        self::assertSame('{"max":150000}', $this->stored['payment_methods']['configuration']['scalapay']['custom_amounts']);
        self::assertArrayNotHasKey('apm_restrictions_version', $this->stored);
    }

    public function test_does_not_call_the_api_while_the_retry_is_blocked(): void
    {
        $this->stored = $this->options([]);
        $this->retry_blocked = true;

        self::assertFalse($this->migration(fn (): bool => true)->run());

        self::assertSame(0, $this->refresh_calls);
        self::assertSame(0, $this->retry_blocks);
        self::assertArrayNotHasKey('apm_restrictions_version', $this->stored);
    }

    public function test_retries_once_the_block_has_expired_and_marks_on_success(): void
    {
        $this->stored = $this->options([]);
        $succeed = false;
        $migration = $this->migration(function () use (&$succeed): bool {
            if ($succeed) {
                $this->stored['apm_cache_mode'] = 'live';
            }

            return $succeed;
        });

        self::assertFalse($migration->run());
        self::assertSame(1, $this->retry_blocks);

        $this->retry_blocked = true;
        $succeed = true;
        self::assertFalse($migration->run());
        self::assertSame(1, $this->refresh_calls);

        $this->retry_blocked = false;
        self::assertTrue($migration->run());
        self::assertSame(2, $this->refresh_calls);
        self::assertSame(ApmMigration::VERSION, $this->stored['apm_restrictions_version']);
        self::assertSame(1, $this->retry_blocks);
    }

    public function test_runs_only_once(): void
    {
        $this->stored = $this->options([]);
        $migration = $this->migration(function (): bool {
            $this->stored['apm_cache_mode'] = 'live';

            return true;
        });

        self::assertTrue($migration->run());
        self::assertFalse($migration->run());
        self::assertSame(1, $this->refresh_calls);
    }

    public function test_cache_fetched_with_the_mode_is_not_refreshed_again(): void
    {
        $this->stored = $this->options([]);
        $this->stored['apm_cache_mode'] = 'test';

        self::assertTrue($this->migration(fn (): bool => true)->run());

        self::assertSame(0, $this->refresh_calls);
        self::assertSame(ApmMigration::VERSION, $this->stored['apm_restrictions_version']);
    }

    public function test_does_nothing_when_not_logged_in(): void
    {
        $this->stored = [];

        self::assertFalse($this->migration(fn (): bool => true)->run());

        self::assertSame(0, $this->refresh_calls);
        self::assertSame([], $this->stored);
    }

    public function test_save_failure_is_caught_and_leaves_the_marker_unset(): void
    {
        $this->stored = $this->options([]);
        $migration = new ApmMigration(
            fn () => $this->stored,
            function (): void {
                throw new \RuntimeException('db down');
            },
            fn (): bool => true,
            function (string $message, string $level): void {
                $this->logs[] = ['message' => $message, 'level' => $level];
            },
            fn (): bool => false,
            function (): void {
            }
        );

        self::assertFalse($migration->run());
        self::assertArrayNotHasKey('apm_restrictions_version', $this->stored);
        self::assertSame('error', $this->logs[0]['level']);
    }

    private function migration(callable $refresh): ApmMigration
    {
        return new ApmMigration(
            fn () => $this->stored,
            function (array $options): void {
                $this->stored = $options;
            },
            function () use ($refresh): bool {
                $this->refresh_calls++;

                return $refresh();
            },
            function (string $message, string $level): void {
                $this->logs[] = ['message' => $message, 'level' => $level];
            },
            fn (): bool => $this->retry_blocked,
            function (): void {
                $this->retry_blocks++;
            }
        );
    }

    private function options(array $scalapay): array
    {
        return [
            'email' => 'merchant@example.test',
            'mode' => true,
            'payment_methods' => [
                'permissions' => [
                    'scalapay' => [
                        'enabled' => true,
                        'countries' => '["FR","IT"]',
                        'amounts' => '{"min":{"EUR":500},"max":{"EUR":200000}}',
                    ],
                ],
                'configuration' => [
                    'scalapay' => array_merge([
                        'active' => true,
                        'custom_amounts' => '{}',
                    ], $scalapay),
                    'bancontact' => ['active' => true],
                ],
            ],
        ];
    }
}
