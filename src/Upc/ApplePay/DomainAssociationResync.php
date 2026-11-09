<?php

namespace Payplug\PayplugWoocommerce\Upc\ApplePay;

use PayplugUnifiedCore\Services\DomainAssociationService;

/**
 * Back-office Resync: downloads the Apple Pay domain-association file again and returns the
 * refreshed diagnostics.
 */
final class DomainAssociationResync
{
    /**
     * @var callable(): DomainAssociationService
     */
    private $service_factory;

    /**
     * @var DomainAssociationDiagnostics
     */
    private $diagnostics;

    public function __construct(callable $service_factory, DomainAssociationDiagnostics $diagnostics)
    {
        $this->service_factory = $service_factory;
        $this->diagnostics = $diagnostics;
    }

    /**
     * @return array{success: bool, status: string, diagnostics: array}
     */
    public function handle(): array
    {
        $result = call_user_func($this->service_factory)->refresh();

        return [
            'success' => !$result->isFailed(),
            'status' => $result->status,
            'diagnostics' => $this->diagnostics->diagnostics(),
        ];
    }
}
