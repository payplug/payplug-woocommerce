<?php

namespace Payplug\PayplugWoocommerce\Service;

use Payplug\PayplugWoocommerce\Gateway\AccountGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

/**
 * Refresh of the cached account permissions while Scalapay is active without the merchant token
 * of its widget, on a BO page load or a settings save, at most once per RETRY_DELAY.
 */
class ScalapayTokenSync
{
    public const RETRY_TRANSIENT = 'payplug_scalapay_token_retry';

    public const RETRY_DELAY = 21600;

    /**
     * @var callable(): mixed
     */
    private $load;

    /**
     * @var callable(): bool
     */
    private $refresh;

    /**
     * @var callable(string, string): void
     */
    private $log;

    /**
     * @var callable(): bool
     */
    private $retry_blocked;

    /**
     * @var callable(): void
     */
    private $block_retry;

    public function __construct(callable $load, callable $refresh, callable $log, callable $retry_blocked, callable $block_retry)
    {
        $this->load = $load;
        $this->refresh = $refresh;
        $this->log = $log;
        $this->retry_blocked = $retry_blocked;
        $this->block_retry = $block_retry;
    }

    public static function create(): self
    {
        return new self(
            static fn () => (new Configuration())->get_options(),
            static fn (): bool => (new AccountGateway())->refresh_payment_permissions(),
            static function (string $message, string $level): void {
                PayplugGateway::log($message, $level);
            },
            static fn (): bool => false !== get_transient(self::RETRY_TRANSIENT),
            static function (): void {
                set_transient(self::RETRY_TRANSIENT, 1, self::RETRY_DELAY);
            }
        );
    }

    /**
     * Never throws: a failure is logged, and like a success it blocks the next refresh for RETRY_DELAY.
     *
     * @return bool whether the permissions were refreshed
     */
    public function run(): bool
    {
        try {
            $options = ($this->load)();

            return is_array($options) && !empty($options['email']) && $this->claim($options) && ($this->refresh)();
        } catch (\Throwable $e) {
            ($this->log)(sprintf('Scalapay merchant token sync failed: %s', $e->getMessage()), 'warning');

            return false;
        }
    }

    /**
     * Whether a refresh is due for the missing token; a due refresh blocks the next one for RETRY_DELAY.
     *
     * @param array $options woocommerce_payplug_settings, read raw
     */
    public function claim(array $options): bool
    {
        if (!self::token_missing($options) || ($this->retry_blocked)()) {
            return false;
        }
        ($this->block_retry)();

        return true;
    }

    private static function token_missing(array $options): bool
    {
        return !empty($options['payment_methods']['configuration']['scalapay']['active'])
            && '' === (new ScalapayWidgetSettings($options))->merchant_token();
    }
}
