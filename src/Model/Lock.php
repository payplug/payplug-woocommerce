<?php

namespace Payplug\PayplugWoocommerce\Model;

use Payplug\PayplugWoocommerce\Exception\LockUnavailableException;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

class Lock
{
    public const REPAIR_LOCK_NAME = 'payplug_lock_table_repair';

    public const REPAIR_LOCK_TIMEOUT = 5;

    /**
     * create Lock table
     */
    public static function create_lock_table(): void
    {
        global $wpdb;

        $sql = 'CREATE TABLE  IF NOT EXISTS `' . $wpdb->base_prefix . 'woocommerce_payplug_lock` (';
        $sql .= ' `payment_id` VARCHAR(50) NOT NULL,';
        $sql .= ' `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,';
        $sql .= ' PRIMARY KEY (`payment_id`));';

        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        maybe_create_table($table_name, $sql);

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT count(column_name) as column_exists FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$wpdb->base_prefix}woocommerce_payplug_lock' AND column_name = %s ",
                ['id']
            )
        );

        if (!empty($result) && !$result->column_exists) {
            static::update_lock_table();
        }
    }

    /**
     * The lock table is usable only when its `id` column is auto-incremented.
     */
    public static function is_lock_table_healthy(): bool
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';
        $column = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'id'));

        if (!empty($wpdb->last_error) || empty($column)) {
            return false;
        }

        return false !== stripos((string) $column->Extra, 'auto_increment');
    }

    /**
     * Make the `id` column auto-incremented, purging the rows of the unusable table.
     * Serialised with a MySQL named lock so a concurrent repair cannot purge live locks.
     *
     * @throws LockUnavailableException
     */
    public static function repair_lock_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';
        $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', self::REPAIR_LOCK_NAME, static::REPAIR_LOCK_TIMEOUT));
        if ('1' !== (string) $acquired) {
            throw new LockUnavailableException(sprintf('Unable to acquire the repair lock of table %s. %s', $table_name, $wpdb->last_error));
        }

        try {
            if (static::is_lock_table_healthy()) {
                return;
            }

            if (false === $wpdb->query("DELETE FROM `{$table_name}`")) {
                throw new LockUnavailableException(sprintf('Unable to purge table %s: %s', $table_name, $wpdb->last_error));
            }

            $id_column = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'id'));
            $altered = empty($id_column)
                ? static::update_lock_table()
                : false !== $wpdb->query("ALTER TABLE `{$table_name}` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
            $error = $wpdb->last_error;

            if (!$altered || !static::is_lock_table_healthy()) {
                throw new LockUnavailableException(sprintf('Unable to repair table %s: %s', $table_name, $error));
            }

            PayplugGateway::log(sprintf('Table %s repaired: auto-increment id restored.', $table_name));
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::REPAIR_LOCK_NAME));
        }
    }

    /**
     * update table
     */
    public static function update_lock_table(): bool
    {
        global $wpdb;

        $sql = "ALTER TABLE `{$wpdb->base_prefix}woocommerce_payplug_lock` ";
        $sql .= ' ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,';
        $sql .= ' DROP PRIMARY KEY,';
        $sql .= ' ADD PRIMARY KEY (`id`);';

        return false !== $wpdb->query($sql);
    }

    /**
     * @param $payment_id
     *
     * @throws LockUnavailableException when no lock id can be obtained
     *
     * @return int the id of the inserted lock
     */
    public static function insert_lock($payment_id): int
    {
        global $wpdb;
        $wpdb->hide_errors();
        $lock_table_exists = self::check_table_exists();
        if (!$lock_table_exists) {
            self::create_lock_table();
        }

        $lock_id = self::try_insert($payment_id);
        if ($lock_id > 0) {
            return $lock_id;
        }

        $error = $wpdb->last_error;
        if (static::is_lock_table_healthy()) {
            throw new LockUnavailableException(sprintf('Unable to insert a lock for payment %s: %s', $payment_id, $error));
        }

        static::repair_lock_table();

        $lock_id = self::try_insert($payment_id);
        if ($lock_id > 0) {
            return $lock_id;
        }

        throw new LockUnavailableException(sprintf('Unable to insert a lock for payment %s after repairing the table: %s', $payment_id, $wpdb->last_error));
    }

    private static function try_insert($payment_id): int
    {
        global $wpdb;

        if (false === $wpdb->insert($wpdb->base_prefix . 'woocommerce_payplug_lock', ['payment_id' => $payment_id])) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public static function delete_lock($id)
    {
        global $wpdb;
        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id = %s OR created < NOW() - INTERVAL 1 DAY;", [$id]));

        return true;
    }

    /**
     * @param $payment_id
     *
     * @return bool
     */
    public static function delete_lock_by_payment_id($payment_id)
    {
        global $wpdb;
        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE payment_id = %s OR created < NOW() - INTERVAL 1 DAY;", [$payment_id]));

        return true;
    }

    /**
     * @param $payment_id
     * @param $id
     *
     * @return bool
     */
    public static function locked($payment_id, $id)
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table_name} WHERE `payment_id` = %s AND `id` <> %s ", [$payment_id, $id]));

        if (empty($result)) {
            return false;
        }

        return true;
    }

    /**
     * @param $id
     *
     * @return bool|int
     */
    public static function get_lock_by_payment_id($payment_id)
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table_name} WHERE payment_id = %s", [$payment_id]));
        if (!$result) {
            return false;
        }

        return $result;
    }

    public static function delete_lock_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';
        $sql = "DROP TABLE IF EXISTS $table_name;";
        $wpdb->query($sql);
    }

    public static function check_table_exists()
    {
        global $wpdb;
        $table_name = $wpdb->base_prefix . 'woocommerce_payplug_lock';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_name
        ));

        return $table_exists === $table_name;
    }
}
