<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use Payplug\PayplugWoocommerce\Upc\Card\CardDataExtractor;

/**
 * Keeps the UpcTransactionMetadata snapshot of a UHF order up to date. Each write starts from a
 * freshly loaded order, so a caller holding an older instance never overwrites what another path
 * recorded in between, and runs under a per-order lock, so two paths writing at the same time
 * (the synchronous payment and the webhook, which gets LOCKED from the processor but still records
 * the card) cannot overwrite each other. A failure is logged and never interrupts the payment or
 * refund flow.
 */
class UpcTransactionMetadataRecorder
{
    public const META_KEY = '_payplug_upc_metadata';

    // The critical section is one meta read and write: a few milliseconds. The TTL only frees the
    // lock if a request dies while holding it; the wait covers the usual overlap of two paths.
    private const LOCK_TTL_SECONDS = 5;

    private const LOCK_ATTEMPTS = 5;

    private const LOCK_WAIT_MICROSECONDS = 100000;

    /**
     * Starts a new snapshot only for a new operation: a webhook for this operation may already
     * have recorded the payment and card, and they are kept.
     */
    public function record_created(\WC_Order $order, string $operation_id, bool $three_ds): void
    {
        $this->update($order, static function (\WC_Order $current) use ($operation_id, $three_ds): array {
            $meta = $current->get_meta(self::META_KEY);

            if (is_array($meta) && $operation_id === ($meta['transaction_id'] ?? null)) {
                return $meta;
            }

            $amount = UpcOrderAmount::total($current);

            if (null === $amount) {
                // Logged by update() as a skipped write, like a lock contention.
                throw new \RuntimeException('the order total could not be converted.');
            }

            return UpcTransactionMetadata::initial(
                $operation_id,
                $amount,
                (bool) PayplugWoocommerceHelper::check_mode(),
                $three_ds
            );
        });
    }

    public function record_paid(\WC_Order $order): void
    {
        $this->update($order, fn (\WC_Order $current): array => UpcTransactionMetadata::mark_paid($this->load($current), time()));
    }

    /**
     * @param array<string, mixed> $decoded json_decode()'d webhook or operation body
     */
    public function record_card(\WC_Order $order, array $decoded): void
    {
        $this->update($order, fn (\WC_Order $current): array => UpcTransactionMetadata::with_card($this->load($current), CardDataExtractor::extract($decoded)));
    }

    /**
     * @param int|null $refunded_cents the amount sent to createRefund(), null for a full refund
     */
    public function record_refund(\WC_Order $order, ?int $refunded_cents): void
    {
        $this->update($order, fn (\WC_Order $current): array => UpcTransactionMetadata::with_refund($this->load($current), $refunded_cents));
    }

    /**
     * @param callable(\WC_Order): array<string, mixed> $build
     */
    private function update(\WC_Order $order, callable $build): void
    {
        $lock = new WooCommerceLock();
        $lock_key = 'payplug_upc_metadata_' . $order->get_id();
        $locked = false;

        try {
            $locked = $this->acquire_lock($lock, $lock_key);

            if (!$locked) {
                throw new \RuntimeException('another write is in progress.');
            }

            $current = wc_get_order($order->get_id());
            $current = $current instanceof \WC_Order ? $current : $order;

            $current->update_meta_data(self::META_KEY, $build($current));
            $current->save_meta_data();
        } catch (\Throwable $e) {
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment details could not be recorded for order #%s: %s',
                $order->get_id(),
                $e->getMessage()
            ));
        } finally {
            // Only a lock this call took: a skipped write must not free another writer's lock.
            if ($locked) {
                $lock->release($lock_key);
            }
        }
    }

    private function acquire_lock(WooCommerceLock $lock, string $lock_key): bool
    {
        for ($attempt = 1; $attempt <= self::LOCK_ATTEMPTS; ++$attempt) {
            if ($lock->acquire($lock_key, self::LOCK_TTL_SECONDS)) {
                return true;
            }

            if (self::LOCK_ATTEMPTS > $attempt) {
                $this->wait_for_lock();
            }
        }

        return false;
    }

    protected function wait_for_lock(): void
    {
        usleep(self::LOCK_WAIT_MICROSECONDS);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(\WC_Order $order): array
    {
        $meta = $order->get_meta(self::META_KEY);

        if (is_array($meta) && [] !== $meta) {
            return $meta;
        }

        return UpcTransactionMetadata::initial(
            (string) $order->get_meta('_payplug_upc_operation_id'),
            (int) $order->get_meta('_payplug_upc_amount'),
            (bool) PayplugWoocommerceHelper::check_mode(),
            false
        );
    }
}
