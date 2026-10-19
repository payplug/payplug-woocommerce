<?php

namespace Payplug\PayplugWoocommerce\Front;

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

/**
 * Limits the orders a visitor can create with the Apple Pay cart and product buttons, per logged-in user
 * or WC session, and per REMOTE_ADDR. Client-supplied headers such as X-Forwarded-For are never read.
 */
final class ApplePayRateLimiter
{
    public const FILTER = 'payplug_applepay_dummy_order_rate_limit';

    private const DEFAULT_LIMITS = [
        'subject' => ['limit' => 10, 'window' => 600],
        'ip' => ['limit' => 100, 'window' => 600],
    ];

    private const KEY_PREFIX = 'payplug_ard_';

    /**
     * Counts the attempt when it is allowed. A refused attempt is not counted.
     */
    public static function allow(): bool
    {
        $limits = self::limits();

        $ip = self::remote_addr();
        if ('' !== $ip && !self::hit('ip', $ip, $limits['ip'])) {
            return false;
        }

        $subject = self::subject();

        return '' === $subject || self::hit('subject', $subject, $limits['subject']);
    }

    /**
     * Limits after the payplug_applepay_dummy_order_rate_limit filter. A limit or a window of 0 disables
     * the scope, a filter result that is not an array disables both scopes.
     *
     * @return array{subject: array{limit: int, window: int}, ip: array{limit: int, window: int}}
     */
    public static function limits(): array
    {
        $filtered = apply_filters(self::FILTER, self::DEFAULT_LIMITS);

        $limits = [];
        foreach (self::DEFAULT_LIMITS as $scope => $default) {
            if (!is_array($filtered)) {
                $limits[$scope] = ['limit' => 0, 'window' => $default['window']];
                continue;
            }

            $entry = isset($filtered[$scope]) && is_array($filtered[$scope]) ? $filtered[$scope] : [];
            $limits[$scope] = [
                'limit' => self::normalize($entry, 'limit', $default['limit']),
                'window' => self::normalize($entry, 'window', $default['window']),
            ];
        }

        return $limits;
    }

    /**
     * @param string $scope 'subject' or 'ip'
     */
    public static function transient_key(string $scope, string $identity): string
    {
        return self::KEY_PREFIX . substr($scope, 0, 1) . '_' . wp_hash($identity);
    }

    private static function normalize(array $entry, string $name, int $default): int
    {
        if (!array_key_exists($name, $entry)) {
            return $default;
        }

        if (false === $entry[$name]) {
            return 0;
        }

        return is_numeric($entry[$name]) ? max(0, (int) $entry[$name]) : $default;
    }

    /**
     * Fixed window anchored at the first counted attempt: the transient expires at the end of the window,
     * each write keeps that expiry, and a refusal writes nothing.
     *
     * @param array{limit: int, window: int} $limit
     */
    private static function hit(string $scope, string $identity, array $limit): bool
    {
        if ($limit['limit'] <= 0 || $limit['window'] <= 0) {
            return true;
        }

        $key = self::transient_key($scope, $identity);
        $now = time();
        $bucket = get_transient($key);
        if (
            !is_array($bucket)
            || !isset($bucket['count'], $bucket['started'])
            || (int) $bucket['started'] > $now
            || $now - (int) $bucket['started'] >= $limit['window']
        ) {
            $bucket = ['count' => 0, 'started' => $now];
        }

        if ((int) $bucket['count'] >= $limit['limit']) {
            PayplugGateway::log(sprintf(
                'Apple Pay: place_order_with_dummy_data rate limited (scope=%s, id=%s, limit=%d/%ds)',
                $scope,
                substr(wp_hash($identity), 0, 12),
                $limit['limit'],
                $limit['window']
            ), 'warning');

            return false;
        }

        $bucket = ['count' => (int) $bucket['count'] + 1, 'started' => (int) $bucket['started']];
        set_transient($key, $bucket, max(1, $limit['window'] - ($now - $bucket['started'])));

        return true;
    }

    private static function subject(): string
    {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            return 'u:' . $user_id;
        }

        $session = function_exists('WC') ? WC()->session : null;
        $customer_id = $session ? (string) $session->get_customer_id() : '';

        return '' !== $customer_id ? 's:' . $customer_id : '';
    }

    private static function remote_addr(): string
    {
        $addr = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? wp_unslash($_SERVER['REMOTE_ADDR']) : '';

        return false !== filter_var($addr, FILTER_VALIDATE_IP) ? $addr : '';
    }
}
