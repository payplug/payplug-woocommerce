<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\ApplePayRateLimiter;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\GatewayLogSpy;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class ApplePayRateLimiter_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    private const DEFAULT_LIMITS = [
        'subject' => ['limit' => 10, 'window' => 600],
        'ip' => ['limit' => 100, 'window' => 600],
    ];

    /**
     * @var GatewayLogSpy|null
     */
    private $log_spy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    }

    protected function tearDown(): void
    {
        if ($this->log_spy) {
            $this->log_spy->stop();
            $this->log_spy = null;
        }
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    public function test_defaults_are_10_per_600_s_for_the_subject_and_100_per_600_s_for_the_ip(): void
    {
        self::assertSame(self::DEFAULT_LIMITS, ApplePayRateLimiter::limits());
    }

    public function test_allows_up_to_the_limit_then_refuses(): void
    {
        $this->limit(3, 0);

        self::assertSame([true, true, true, false], $this->attempts(4));
    }

    public function test_a_refused_attempt_is_not_counted_and_writes_nothing(): void
    {
        $this->limit(3, 0);
        $this->attempts(3);
        $key = $this->session_key();
        $timeout = get_option('_transient_timeout_' . $key);

        self::assertFalse(ApplePayRateLimiter::allow());
        self::assertFalse(ApplePayRateLimiter::allow());

        self::assertSame(3, get_transient($key)['count']);
        self::assertSame($timeout, get_option('_transient_timeout_' . $key));
    }

    public function test_the_window_is_fixed_from_the_first_attempt(): void
    {
        $this->limit(5, 0);
        ApplePayRateLimiter::allow();
        $key = $this->session_key();
        $started = get_transient($key)['started'];

        ApplePayRateLimiter::allow();

        self::assertSame($started, get_transient($key)['started']);
        self::assertSame(2, get_transient($key)['count']);
        // set_transient() reads the clock again, hence one second of tolerance.
        self::assertEqualsWithDelta($started + 600, (int) get_option('_transient_timeout_' . $key), 1);
    }

    public function test_the_window_restarts_once_elapsed(): void
    {
        $this->limit(3, 0);
        $key = $this->session_key();
        set_transient($key, ['count' => 3, 'started' => time() - 601], 600);

        self::assertTrue(ApplePayRateLimiter::allow());

        self::assertSame(1, get_transient($key)['count']);
        self::assertGreaterThanOrEqual(time() - 1, get_transient($key)['started']);
    }

    public function stale_bucket_provider(): array
    {
        return [
            'not an array' => ['junk'],
            'count missing' => [['started' => time()]],
            'started in the future' => [['count' => 3, 'started' => time() + 3600]],
        ];
    }

    /**
     * @dataProvider stale_bucket_provider
     *
     * @param mixed $bucket
     */
    public function test_an_unusable_stored_bucket_restarts_the_count($bucket): void
    {
        $this->limit(3, 0);
        $key = $this->session_key();
        set_transient($key, $bucket, 600);

        self::assertTrue(ApplePayRateLimiter::allow());

        self::assertSame(1, get_transient($key)['count']);
    }

    public function test_a_logged_in_user_is_counted_by_user_id_not_by_session(): void
    {
        $this->limit(1, 0);
        $user_id = $this->make_customer();
        wp_set_current_user($user_id);

        self::assertTrue(ApplePayRateLimiter::allow());
        $this->start_new_guest_session();
        wp_set_current_user($user_id);

        self::assertFalse(ApplePayRateLimiter::allow());
        self::assertSame(1, get_transient(ApplePayRateLimiter::transient_key('subject', 'u:' . $user_id))['count']);
        self::assertFalse(get_transient($this->session_key()));
    }

    public function test_two_sessions_have_their_own_counter(): void
    {
        $this->limit(1, 0);
        self::assertTrue(ApplePayRateLimiter::allow());
        self::assertFalse(ApplePayRateLimiter::allow());

        $this->start_new_guest_session();

        self::assertTrue(ApplePayRateLimiter::allow());
    }

    public function test_the_ip_cap_applies_across_sessions(): void
    {
        $this->limit(0, 2);

        self::assertTrue(ApplePayRateLimiter::allow());
        $this->start_new_guest_session();
        self::assertTrue(ApplePayRateLimiter::allow());
        $this->start_new_guest_session();

        self::assertFalse(ApplePayRateLimiter::allow());
    }

    public function test_another_ip_has_its_own_counter(): void
    {
        $this->limit(0, 1);
        self::assertTrue(ApplePayRateLimiter::allow());

        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';

        self::assertTrue(ApplePayRateLimiter::allow());
    }

    public function test_forwarded_headers_are_never_read(): void
    {
        $this->limit(0, 2);
        $results = [];
        foreach (['198.51.100.1', '198.51.100.2', '198.51.100.3'] as $forwarded) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded;
            $_SERVER['HTTP_X_REAL_IP'] = $forwarded;
            $_SERVER['HTTP_CLIENT_IP'] = $forwarded;
            $this->start_new_guest_session();
            $results[] = ApplePayRateLimiter::allow();
        }
        unset($_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_CLIENT_IP']);

        self::assertSame([true, true, false], $results);
    }

    public function unusable_remote_addr_provider(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'not an ip' => ['not-an-ip'],
            'list of ips' => ['203.0.113.7, 198.51.100.1'],
            'array' => [['203.0.113.7']],
        ];
    }

    /**
     * @dataProvider unusable_remote_addr_provider
     *
     * @param mixed $remote_addr
     */
    public function test_an_unusable_remote_addr_skips_the_ip_cap($remote_addr): void
    {
        $this->limit(0, 1);
        if (null === $remote_addr) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $remote_addr;
        }

        self::assertSame([true, true, true, true, true], $this->attempts(5));
    }

    public function test_without_wc_session_the_subject_cap_is_skipped(): void
    {
        $this->limit(1, 0);
        WC()->session = null;

        self::assertSame([true, true, true], $this->attempts(3));
    }

    public function disabling_filter_provider(): array
    {
        return [
            'subject limit 0' => [['subject' => ['limit' => 0]], 'subject'],
            'subject limit false' => [['subject' => ['limit' => false]], 'subject'],
            'subject window 0' => [['subject' => ['window' => 0]], 'subject'],
            'ip limit 0' => [['ip' => ['limit' => 0]], 'ip'],
            'ip window false' => [['ip' => ['window' => false]], 'ip'],
            'false for both' => [false, 'both'],
            'null for both' => [null, 'both'],
            'string for both' => ['off', 'both'],
        ];
    }

    /**
     * @dataProvider disabling_filter_provider
     *
     * @param mixed $filtered
     */
    public function test_the_filter_can_disable_each_scope_and_both($filtered, string $disabled): void
    {
        add_filter(ApplePayRateLimiter::FILTER, static fn () => $filtered);

        $limits = ApplePayRateLimiter::limits();

        foreach (['subject', 'ip'] as $scope) {
            $is_disabled = 0 === $limits[$scope]['limit'] || 0 === $limits[$scope]['window'];
            self::assertSame('both' === $disabled || $scope === $disabled, $is_disabled, $scope);
        }
    }

    public function test_a_disabled_subject_scope_never_refuses(): void
    {
        add_filter(ApplePayRateLimiter::FILTER, static fn (): array => ['subject' => ['limit' => 0], 'ip' => ['limit' => 1000]]);

        self::assertNotContains(false, $this->attempts(12));
    }

    public function test_limits_disabled_by_the_filter_never_refuse_nor_write(): void
    {
        add_filter(ApplePayRateLimiter::FILTER, '__return_false');

        self::assertNotContains(false, $this->attempts(120));
        self::assertFalse(get_transient($this->session_key()));
        self::assertFalse(get_transient(ApplePayRateLimiter::transient_key('ip', '203.0.113.7')));
    }

    public function test_the_filter_receives_the_defaults_and_can_lower_a_limit(): void
    {
        $received = null;
        add_filter(ApplePayRateLimiter::FILTER, static function ($limits) use (&$received) {
            $received = $limits;
            $limits['subject']['limit'] = 2;

            return $limits;
        });

        self::assertSame([true, true, false], $this->attempts(3));
        self::assertSame(self::DEFAULT_LIMITS, $received);
    }

    public function malformed_filter_provider(): array
    {
        return [
            'scope not an array' => [['subject' => 'x']],
            'limit not numeric' => [['subject' => ['limit' => 'abc']]],
            'window an array' => [['subject' => ['window' => []]]],
            'limit null' => [['subject' => ['limit' => null]]],
            'scope missing' => [['ip' => self::DEFAULT_LIMITS['ip']]],
            'empty array' => [[]],
        ];
    }

    /**
     * @dataProvider malformed_filter_provider
     */
    public function test_malformed_filter_values_fall_back_to_the_defaults(array $filtered): void
    {
        add_filter(ApplePayRateLimiter::FILTER, static fn (): array => $filtered);

        self::assertSame(self::DEFAULT_LIMITS, ApplePayRateLimiter::limits());
    }

    public function test_numeric_strings_from_the_filter_are_accepted(): void
    {
        add_filter(ApplePayRateLimiter::FILTER, static fn (): array => ['subject' => ['limit' => '3', 'window' => '60']]);

        self::assertSame(['limit' => 3, 'window' => 60], ApplePayRateLimiter::limits()['subject']);
    }

    public function test_an_ip_refusal_is_logged_as_a_warning_with_a_hashed_ip_only(): void
    {
        $this->limit(0, 1);
        $this->log_spy = GatewayLogSpy::start();
        ApplePayRateLimiter::allow();

        self::assertFalse(ApplePayRateLimiter::allow());

        self::assertCount(1, $this->log_spy->entries);
        $entry = $this->log_spy->entries[0];
        self::assertSame('warning', $entry['level']);
        self::assertStringContainsString('scope=ip', $entry['message']);
        self::assertStringContainsString('limit=1/600s', $entry['message']);
        self::assertStringContainsString('id=' . substr(wp_hash('203.0.113.7'), 0, 12), $entry['message']);
        self::assertStringNotContainsString('203.0.113.7', $entry['message']);
    }

    public function test_a_subject_refusal_is_logged_as_a_warning_without_the_session_id(): void
    {
        $this->limit(1, 0);
        $this->log_spy = GatewayLogSpy::start();
        ApplePayRateLimiter::allow();

        self::assertFalse(ApplePayRateLimiter::allow());

        self::assertCount(1, $this->log_spy->entries);
        $entry = $this->log_spy->entries[0];
        self::assertSame('warning', $entry['level']);
        self::assertStringContainsString('scope=subject', $entry['message']);
        self::assertStringNotContainsString((string) WC()->session->get_customer_id(), $entry['message']);
        self::assertStringNotContainsString('203.0.113.7', $entry['message']);
    }

    public function test_the_ip_bucket_is_checked_first_so_a_blocked_ip_creates_no_subject_row(): void
    {
        $this->limit(5, 1);
        ApplePayRateLimiter::allow();
        $this->start_new_guest_session();

        self::assertFalse(ApplePayRateLimiter::allow());

        self::assertFalse(get_transient($this->session_key()));
    }

    public function test_transient_keys_hash_the_identity_and_fit_the_option_name(): void
    {
        $key = ApplePayRateLimiter::transient_key('ip', '203.0.113.7');

        self::assertStringStartsWith('payplug_ard_i_', $key);
        self::assertStringNotContainsString('203.0.113.7', $key);
        self::assertLessThanOrEqual(172, strlen($key));
        self::assertNotSame($key, ApplePayRateLimiter::transient_key('subject', '203.0.113.7'));
    }

    private function limit(int $subject_limit, int $ip_limit): void
    {
        add_filter(ApplePayRateLimiter::FILTER, static fn (): array => [
            'subject' => ['limit' => $subject_limit, 'window' => 600],
            'ip' => ['limit' => $ip_limit, 'window' => 600],
        ]);
    }

    /**
     * @return bool[] result of each allow() call
     */
    private function attempts(int $count): array
    {
        $results = [];
        for ($i = 0; $i < $count; ++$i) {
            $results[] = ApplePayRateLimiter::allow();
        }

        return $results;
    }

    private function session_key(): string
    {
        return ApplePayRateLimiter::transient_key('subject', 's:' . WC()->session->get_customer_id());
    }
}
