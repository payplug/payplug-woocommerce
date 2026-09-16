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
        $sql .= ' UNIQUE KEY `alias_id_mode` (`alias_id`, `mode`),';
        $sql .= ' KEY `customer_id` (`customer_id`));';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        maybe_create_table($table_name, $sql);
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
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        return $exists === $table_name;
    }

    /**
     * @return int|false the row id (new, or the pre-existing one on a unique-key collision),
     *                    or false if the insert failed for any other reason
     */
    public static function insert(int $customer_id, string $alias_id, string $brand, string $last4, int $exp_month, int $exp_year, string $mode)
    {
        global $wpdb;
        $wpdb->hide_errors();

        if (!self::check_table_exists()) {
            self::create_table();
        }

        $existing = self::find_by_alias($alias_id, $mode);

        if (null !== $existing) {
            return (int) $existing->id;
        }

        $table_name = $wpdb->base_prefix . self::TABLE;
        $wpdb->insert($table_name, [
            'customer_id' => $customer_id,
            'alias_id' => $alias_id,
            'brand' => $brand,
            'last4' => $last4,
            'exp_month' => $exp_month,
            'exp_year' => $exp_year,
            'mode' => $mode,
        ]);

        if ($wpdb->insert_id) {
            return (int) $wpdb->insert_id;
        }

        // Race: another request inserted the same (alias_id, mode) between the check above
        // and this insert - the unique key rejected it. Look the row up again rather than
        // treat this as an error.
        $existing = self::find_by_alias($alias_id, $mode);

        return null !== $existing ? (int) $existing->id : false;
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
     * @return object[]
     */
    public static function get_customer_cards(int $customer_id, string $mode): array
    {
        global $wpdb;

        if (!self::check_table_exists()) {
            return [];
        }

        $table_name = $wpdb->base_prefix . self::TABLE;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE customer_id = %d AND mode = %s ORDER BY id DESC",
            [$customer_id, $mode]
        ));

        if (!is_array($rows)) {
            return [];
        }

        $now_year = (int) gmdate('Y');
        $now_month = (int) gmdate('n');

        return array_values(array_filter($rows, static function ($row) use ($now_year, $now_month) {
            $exp_year = (int) $row->exp_year;
            $exp_month = (int) $row->exp_month;

            return ($exp_year > $now_year) || ($exp_year === $now_year && $exp_month >= $now_month);
        }));
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
}
