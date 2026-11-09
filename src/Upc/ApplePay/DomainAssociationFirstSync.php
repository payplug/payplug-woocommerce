<?php

namespace Payplug\PayplugWoocommerce\Upc\ApplePay;

use PayplugUnifiedCore\Contracts\IDomainAssociationStorage;
use PayplugUnifiedCore\Services\DomainAssociationService;

/**
 * Downloads the Apple Pay domain-association file on the first back-office page a shop manager
 * opens after the plugin is installed or updated. A single attempt: UPC records lastAttemptAt
 * whether it succeeds or fails, and later attempts go through Resync. Until the PayPlug account
 * has Apple Pay, nothing is attempted.
 */
final class DomainAssociationFirstSync
{
    /**
     * @var callable(): DomainAssociationService
     */
    private $service_factory;

    /**
     * @var IDomainAssociationStorage
     */
    private $storage;

    /**
     * @var callable(): bool
     */
    private $account_allows_apple_pay;

    public function __construct(callable $service_factory, IDomainAssociationStorage $storage, callable $account_allows_apple_pay)
    {
        $this->service_factory = $service_factory;
        $this->storage = $storage;
        $this->account_allows_apple_pay = $account_allows_apple_pay;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybe_sync']);
    }

    public function maybe_sync(): void
    {
        if (wp_doing_ajax() || !current_user_can('manage_woocommerce')) {
            return;
        }

        if (null !== ($this->storage->readStatus()['lastAttemptAt'] ?? null) || !call_user_func($this->account_allows_apple_pay)) {
            return;
        }

        call_user_func($this->service_factory)->ensureStored();
    }
}
