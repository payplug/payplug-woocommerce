<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ScalapayTokenSync;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScalapayWidgetFixtures;
use PHPUnit\Framework\TestCase;

class ScalapayTokenSync_test extends TestCase
{
    /**
     * @var mixed
     */
    private $stored;

    /**
     * @var int
     */
    private $refresh_calls = 0;

    /**
     * @var bool|\Throwable
     */
    private $refresh_result = true;

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

    public function test_admin_load_refreshes_an_active_scalapay_without_a_token(): void
    {
        $this->stored = $this->options('');

        self::assertTrue($this->sync()->run());
        self::assertSame(1, $this->refresh_calls);
        self::assertSame(1, $this->retry_blocks);
    }

    public function test_a_blank_token_is_a_missing_token(): void
    {
        $this->stored = $this->options('  ');

        self::assertTrue($this->sync()->run());
        self::assertSame(1, $this->refresh_calls);
    }

    public function test_admin_load_refreshes_at_most_once_per_retry_delay(): void
    {
        $this->stored = $this->options('');
        $sync = $this->sync();

        self::assertTrue($sync->run());
        self::assertFalse($sync->run());
        self::assertSame(1, $this->refresh_calls);
    }

    public function test_a_failed_refresh_also_waits_for_the_retry_delay(): void
    {
        $this->stored = $this->options('');
        $this->refresh_result = false;
        $sync = $this->sync();

        self::assertFalse($sync->run());
        self::assertFalse($sync->run());
        self::assertSame(1, $this->refresh_calls);
        self::assertSame(1, $this->retry_blocks);
    }

    public function test_a_refresh_exception_is_logged_and_not_thrown(): void
    {
        $this->stored = $this->options('');
        $this->refresh_result = new \RuntimeException('timeout');

        self::assertFalse($this->sync()->run());
        self::assertSame([['Scalapay merchant token sync failed: timeout', 'warning']], $this->logs);
        self::assertSame(1, $this->retry_blocks);
    }

    /**
     * @dataProvider nothing_to_sync_provider
     *
     * @param mixed $stored
     */
    public function test_nothing_to_sync($stored): void
    {
        $this->stored = $stored;

        self::assertFalse($this->sync()->run());
        self::assertSame(0, $this->refresh_calls);
        self::assertSame(0, $this->retry_blocks);
    }

    public function nothing_to_sync_provider(): array
    {
        $inactive = $this->options('');
        $inactive['payment_methods']['configuration']['scalapay']['active'] = false;
        $logged_out = $this->options('');
        unset($logged_out['email']);
        $no_scalapay = $this->options('');
        unset($no_scalapay['payment_methods']);

        return [
            'token known' => [$this->options(ScalapayWidgetFixtures::TOKEN)],
            'scalapay inactive' => [$inactive],
            'no scalapay configuration' => [$no_scalapay],
            'not logged in' => [$logged_out],
            'no settings' => [false],
        ];
    }

    public function test_settings_save_claims_the_refresh_once_per_retry_delay(): void
    {
        $sync = $this->sync();
        $options = $this->options('');

        self::assertTrue($sync->claim($options));
        self::assertFalse($sync->claim($options));
        self::assertSame(1, $this->retry_blocks);
        self::assertSame(0, $this->refresh_calls);
    }

    public function test_settings_save_claims_nothing_once_the_token_is_known(): void
    {
        self::assertFalse($this->sync()->claim($this->options(ScalapayWidgetFixtures::TOKEN)));
        self::assertSame(0, $this->retry_blocks);
    }

    private function sync(): ScalapayTokenSync
    {
        return new ScalapayTokenSync(
            fn () => $this->stored,
            function (): bool {
                ++$this->refresh_calls;
                if ($this->refresh_result instanceof \Throwable) {
                    throw $this->refresh_result;
                }

                return $this->refresh_result;
            },
            function (string $message, string $level): void {
                $this->logs[] = [$message, $level];
            },
            fn (): bool => $this->retry_blocked,
            function (): void {
                ++$this->retry_blocks;
                $this->retry_blocked = true;
            }
        );
    }

    private function options(string $token): array
    {
        $options = ScalapayWidgetFixtures::options([], $token);
        $options['email'] = 'merchant@example.test';

        return $options;
    }
}
