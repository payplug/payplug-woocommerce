<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\Mocks;

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Model\Lock;

/**
 * Lock table schema states and log capture for tests that run real DDL,
 * which commits implicitly and cannot be rolled back.
 */
trait LockTableFixtures
{
    /** @var array<int, array{0: string, 1: string}> */
    private $logged = [];

    private $previous_log;

    private $previous_log_enabled;

    private function reset_lock_table(): void
    {
        global $wpdb;

        $wpdb->query('DROP TRIGGER IF EXISTS ' . $this->lock_table() . '_refuse_insert');
        Lock::delete_lock_table();
        Lock::create_lock_table();
    }

    private function lock_table(): string
    {
        global $wpdb;

        return $wpdb->base_prefix . 'woocommerce_payplug_lock';
    }

    private function alter_lock_table(string $alterations): void
    {
        global $wpdb;

        $wpdb->query('ALTER TABLE `' . $this->lock_table() . '` ' . $alterations);
        self::assertSame('', $wpdb->last_error);
    }

    /**
     * Schema created before the id column existed: no id, payment_id is the primary key.
     */
    private function break_lock_table_without_id_column(): void
    {
        $this->alter_lock_table('DROP COLUMN `id`, ADD PRIMARY KEY (`payment_id`)');
    }

    private function break_lock_table_without_auto_increment(): void
    {
        $this->alter_lock_table('MODIFY `id` INT NOT NULL');
    }

    /**
     * The id column is neither auto-incremented nor a key, so it cannot be made auto-increment in place.
     */
    private function break_lock_table_beyond_repair(): void
    {
        $this->alter_lock_table('MODIFY `id` INT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (`payment_id`)');
    }

    private function refuse_lock_inserts(string $message): void
    {
        global $wpdb;

        $table = $this->lock_table();
        $wpdb->query("CREATE TRIGGER `{$table}_refuse_insert` BEFORE INSERT ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
        self::assertSame('', $wpdb->last_error);
    }

    private function insert_raw_lock_row(string $payment_id): void
    {
        global $wpdb;

        $wpdb->insert($this->lock_table(), ['payment_id' => $payment_id]);
        self::assertSame('', $wpdb->last_error);
    }

    private function count_lock_rows(?string $payment_id = null): int
    {
        global $wpdb;

        $sql = 'SELECT COUNT(*) FROM `' . $this->lock_table() . '`';
        if (null !== $payment_id) {
            $sql = $wpdb->prepare($sql . ' WHERE payment_id = %s', $payment_id);
        }

        return (int) $wpdb->get_var($sql);
    }

    private function capture_logs(): void
    {
        $test = $this;
        $spy = new class ($test) extends \WC_Logger {
            private $test;

            public function __construct($test)
            {
                $this->test = $test;
            }

            public function log($level, $message, $context = []): void
            {
                $this->test->record_log($level, $message);
            }
        };

        $this->previous_log = $this->swap_gateway_static('log', $spy);
        $this->previous_log_enabled = $this->swap_gateway_static('log_enabled', true);
    }

    private function restore_logs(): void
    {
        $this->swap_gateway_static('log', $this->previous_log);
        $this->swap_gateway_static('log_enabled', $this->previous_log_enabled);
        $this->logged = [];
    }

    public function record_log(string $level, string $message): void
    {
        $this->logged[] = [$level, $message];
    }

    /**
     * @return string[]
     */
    private function logged_messages(string $level): array
    {
        $messages = [];
        foreach ($this->logged as $entry) {
            if ($entry[0] === $level) {
                $messages[] = $entry[1];
            }
        }

        return $messages;
    }

    private function swap_gateway_static(string $property, $value)
    {
        $reflection = new \ReflectionProperty(PayplugGateway::class, $property);
        $reflection->setAccessible(true);
        $previous = $reflection->getValue();
        $reflection->setValue(null, $value);

        return $previous;
    }
}
