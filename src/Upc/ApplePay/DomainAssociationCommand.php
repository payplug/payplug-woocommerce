<?php

namespace Payplug\PayplugWoocommerce\Upc\ApplePay;

use PayplugUnifiedCore\Output\DomainAssociationRefreshResult;
use PayplugUnifiedCore\Services\DomainAssociationService;

/**
 * Manages the Apple Pay domain-association file.
 */
final class DomainAssociationCommand
{
    /**
     * @var callable(): DomainAssociationService
     */
    private $service_factory;

    /**
     * @var callable(string): void
     */
    private $on_error;

    /**
     * @var callable(string): void
     */
    private $on_success;

    public function __construct(callable $service_factory, callable $on_error, callable $on_success)
    {
        $this->service_factory = $service_factory;
        $this->on_error = $on_error;
        $this->on_success = $on_success;
    }

    /**
     * Downloads the Apple Pay domain-association file from Payplug again.
     *
     * ## EXAMPLES
     *
     *     wp payplug apple-pay resync
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function resync($args = [], $assoc_args = []): void
    {
        $result = call_user_func($this->service_factory)->refresh();
        if ($result->isFailed()) {
            call_user_func($this->on_error, (string) $result->error);

            return;
        }

        call_user_func(
            $this->on_success,
            DomainAssociationRefreshResult::STATUS_UPDATED === $result->status
                ? 'Apple Pay domain-association file synchronised.'
                : 'Apple Pay domain-association file already up to date.'
        );
    }
}
