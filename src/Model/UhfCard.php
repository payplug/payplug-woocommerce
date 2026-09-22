<?php

namespace Payplug\PayplugWoocommerce\Model;

class UhfCard
{
    private const TABLE = 'woocommerce_payplug_uhf_cards';

    public static function create_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . self::TABLE;

        $sql = 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` (';
        $sql .= ' `id` INT NOT NULL AUTO_INCREMENT,';
        $sql .= ' `customer_id` BIGINT UNSIGNED NOT NULL,';
        $sql .= ' `alias_id` VARCHAR(64) NOT NULL,';
        $sql .= ' `brand` VARCHAR(20) NOT NULL,';
        $sql .= ' `last4` VARCHAR(4) NOT NULL,';
        $sql .= ' `exp_month` TINYINT UNSIGNED NOT NULL,';
        $sql .= ' `exp_year` SMALLINT UNSIGNED NOT NULL,';
        $sql .= ' `mode` VARCHAR(4) NOT NULL,';
        $sql .= ' `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,';
        $sql .= ' PRIMARY KEY (`id`),';
        $sql .= ' UNIQUE KEY `alias_id_mode` (`customer_id`, `alias_id`, `mode`),';
        $sql .= ' KEY `customer_id` (`customer_id`));';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        maybe_create_table($table_name, $sql);

        self::maybe_migrate_unique_key();
    }

    /**
     * The unique key originally covered only (alias_id, mode): if PayPlug ever returns the same
     * alias for the same physical card used by two different customers (a shared household
     * card, or one person with two store accounts), the second customer's insert() would report
     * success while silently returning the FIRST customer's row id instead of storing anything
     * of its own. maybe_create_table() above only creates a missing table, it never alters an
     * existing one (unlike dbDelta()) - so an install that already has the table from before
     * this fix needs an explicit migration, mirroring Model\Lock::create_lock_table()'s own
     * INFORMATION_SCHEMA-check-then-ALTER pattern. Runs unconditionally on every create_table()
     * call (activation/upgrade, per payplug.php) rather than being folded into the CREATE TABLE
     * IF NOT EXISTS above, precisely so it still runs for a table that already existed.
     */
    private static function maybe_migrate_unique_key(): void
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . self::TABLE;

        $is_customer_scoped = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = 'alias_id_mode' AND COLUMN_NAME = 'customer_id'",
            $table_name
        ));

        if (!$is_customer_scoped) {
            $wpdb->query("ALTER TABLE `{$table_name}` DROP INDEX `alias_id_mode`, ADD UNIQUE KEY `alias_id_mode` (`customer_id`, `alias_id`, `mode`)");
        }
    }

    public static function delete_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . self::TABLE;
        $wpdb->query("DROP TABLE IF EXISTS {$table_name};");
    }

    public static function check_table_exists(): bool
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . self::TABLE;
        // esc_like(): the table name is full of underscores, each of which is a LIKE
        // single-character wildcard - without escaping them, a differently-named table that
        // happens to match the pattern and sorts first could make this return the wrong name
        // (get_var() only ever returns the first row), which would make check_table_exists()
        // return false for a table that does exist and cards would silently disappear.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));

        return $exists === $table_name;
    }

    /**
     * @return int|false the row id (new, or the pre-existing one on a unique-key collision),
     *                   or false if the insert failed for any other reason
     */
    public static function insert(int $customer_id, string $alias_id, string $brand, string $last4, int $exp_month, int $exp_year, string $mode)
    {
        global $wpdb;
        // Restored in finally below - hide_errors() otherwise suppresses error display for the
        // rest of the request, not just the queries in this method.
        $previous_show_errors = $wpdb->hide_errors();

        try {
            if (!self::check_table_exists()) {
                self::create_table();
            }

            $existing = self::find_by_alias_for_customer($alias_id, $customer_id, $mode);

            if (null !== $existing) {
                return (int) $existing->id;
            }

            $table_name = $wpdb->base_prefix . self::TABLE;
            $result = $wpdb->insert($table_name, [
                'customer_id' => $customer_id,
                'alias_id' => $alias_id,
                'brand' => $brand,
                'last4' => $last4,
                'exp_month' => $exp_month,
                'exp_year' => $exp_year,
                'mode' => $mode,
            ]);

            if (false !== $result) {
                return (int) $wpdb->insert_id;
            }

            // Race: another request inserted the same (customer_id, alias_id, mode) between the
            // check above and this insert - the unique key rejected it. Look the row up again
            // rather than treat this as an error.
            $existing = self::find_by_alias_for_customer($alias_id, $customer_id, $mode);

            return null !== $existing ? (int) $existing->id : false;
        } finally {
            $wpdb->show_errors($previous_show_errors);
        }
    }

    public static function find_by_alias(string $alias_id, string $mode): ?object
    {
        global $wpdb;

        if (!self::check_table_exists()) {
            return null;
        }

        $table_name = $wpdb->base_prefix . self::TABLE;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE alias_id = %s AND mode = %s",
            [$alias_id, $mode]
        ));

        return $row ?: null;
    }

    /**
     * Customer-scoped variant of find_by_alias(), used by insert()'s own dedup check so that
     * two different customers sharing the same alias never collide - the unscoped find_by_alias()
     * would otherwise return the FIRST customer's row id to the SECOND customer's insert() call,
     * which then reports success without ever storing that second customer's card.
     */
    public static function find_by_alias_for_customer(string $alias_id, int $customer_id, string $mode): ?object
    {
        global $wpdb;

        if (!self::check_table_exists()) {
            return null;
        }

        $table_name = $wpdb->base_prefix . self::TABLE;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE alias_id = %s AND customer_id = %d AND mode = %s",
            [$alias_id, $customer_id, $mode]
        ));

        return $row ?: null;
    }

    /**
     * @return object[]
     */
    public static function get_customer_cards(int $customer_id, string $mode): array
    {
        global $wpdb;

        if (!self::check_table_exists()) {
            return [];
        }

        $table_name = $wpdb->base_prefix . self::TABLE;
        $now_year = (int) gmdate('Y');
        $now_month = (int) gmdate('n');

        // Expiry filtered in SQL rather than fetching every row and filtering in PHP: expired
        // cards would otherwise accumulate forever (nothing ever prunes them) and be re-fetched
        // in full on every checkout render.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE customer_id = %d AND mode = %s AND (exp_year > %d OR (exp_year = %d AND exp_month >= %d)) ORDER BY id DESC",
            [$customer_id, $mode, $now_year, $now_year, $now_month]
        ));

        return is_array($rows) ? $rows : [];
    }

    public static function find_for_customer(int $id, int $customer_id, string $mode): ?object
    {
        global $wpdb;

        if (!self::check_table_exists()) {
            return null;
        }

        $table_name = $wpdb->base_prefix . self::TABLE;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND customer_id = %d AND mode = %s",
            [$id, $customer_id, $mode]
        ));

        return $row ?: null;
    }

    // Local removal only - there is no alias-deletion endpoint on the Unified API today (the
    // UHF spec's endpoint list only covers payment creation/read/refund/operations), so unlike
    // e.g. a hypothetical server-side revocation this can't invalidate the alias on PayPlug's
    // side. This mirrors the existing Retail/Integrated Payment precedent in this codebase:
    // WC_Payment_Tokens::delete() (used for those) is also a local-only deletion, with no API
    // call to revoke the card.
    //
    // Scoped by $customer_id (not just $id): the only current caller already re-verifies
    // ownership via find_for_customer() before calling this, so it isn't exploitable today, but
    // a model method named delete() invites reuse - safe by construction beats safe by caller
    // discipline.
    public static function delete(int $id, int $customer_id): bool
    {
        global $wpdb;

        if (!self::check_table_exists()) {
            return false;
        }

        $table_name = $wpdb->base_prefix . self::TABLE;
        $deleted = $wpdb->delete($table_name, ['id' => $id, 'customer_id' => $customer_id], ['%d', '%d']);

        // $wpdb->delete() returns the row count (0 included) on success, or false on a genuine
        // DB error - not just whether anything matched. Scoping by customer_id above made the
        // "matched zero rows" case reachable (a wrong customer_id for an existing id), where
        // `false !== $deleted` would otherwise be true (0 !== false) and silently report success
        // for a delete that removed nothing.
        return is_int($deleted) && $deleted > 0;
    }
}
