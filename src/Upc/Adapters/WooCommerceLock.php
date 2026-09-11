<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\ILock;

class WooCommerceLock implements ILock
{
    private const OPTION_PREFIX = 'payplug_upc_lock_';

    public function acquire(string $key, int $ttlSeconds): bool
    {
        global $wpdb;

        $option_name = self::OPTION_PREFIX . $key;
        $expires_at = (string) (time() + $ttlSeconds);

        // add_option() is NOT a usable atomic create-if-not-exists primitive, despite an earlier
        // version of this class assuming it was: its own existence check is a plain PHP-level
        // get_option() call, and the INSERT it issues is actually "... ON DUPLICATE KEY UPDATE" -
        // an upsert that always succeeds, never rejected by the UNIQUE index. Both are racy under
        // two truly concurrent callers. A raw INSERT IGNORE is the real atomic primitive: the
        // UNIQUE index on option_name makes the database itself silently drop a second insert for
        // the same key, and checking the affected-row count (not $wpdb->query()'s merely truthy
        // return, which is also true for "0 rows affected, no error") tells us which caller,
        // if any, actually created the row.
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')",
            $option_name,
            $expires_at
        ));

        if (1 === $inserted) {
            wp_cache_delete($option_name, 'options');

            return true;
        }

        // The row already exists - either genuinely held, or left behind by a request that
        // crashed before release(). Only the second case is safe to take over, and the UPDATE's
        // own WHERE clause makes that takeover atomic too: it only affects a row MySQL still finds
        // expired at the instant it evaluates the condition, so two callers racing this same
        // branch can't both "win" it. "<=", not "<": a 0-second TTL must count as already expired.
        $taken_over = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value <= %s",
            $expires_at,
            $option_name,
            (string) time()
        ));

        wp_cache_delete($option_name, 'options');

        return 1 === $taken_over;
    }

    public function release(string $key): void
    {
        delete_option(self::OPTION_PREFIX . $key);
    }
}
