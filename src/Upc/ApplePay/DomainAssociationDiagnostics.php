<?php

namespace Payplug\PayplugWoocommerce\Upc\ApplePay;

use PayplugUnifiedCore\Contracts\IDomainAssociationStorage;
use PayplugUnifiedCore\Services\DomainAssociationService;

/**
 * State of the Apple Pay domain-association file for the back office (payment method
 * "applepay", key "domain_association").
 */
final class DomainAssociationDiagnostics
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
     * @var string|null
     */
    private $document_root;

    public function __construct(callable $service_factory, IDomainAssociationStorage $storage, ?string $document_root = null)
    {
        $this->service_factory = $service_factory;
        $this->storage = $storage;
        $this->document_root = $document_root;
    }

    /**
     * The fields the Resync button refreshes in place. last_error is HTML-escaped.
     *
     * @return array{state: string, last_attempt_at: ?int, last_success_at: ?int, last_error: ?string, root_file_present: bool}
     */
    public function diagnostics(): array
    {
        $status = $this->storage->readStatus();
        $error = $status['lastError'] ?? null;

        return [
            'state' => null !== call_user_func($this->service_factory)->resolve() ? 'synced' : 'not_synced',
            'last_attempt_at' => $status['lastAttemptAt'] ?? null,
            'last_success_at' => $status['lastSuccessAt'] ?? null,
            'last_error' => is_string($error) && '' !== $error ? esc_html($error) : null,
            'root_file_present' => DomainAssociationServer::root_file_present($this->document_root),
        ];
    }

    public function payload(): array
    {
        return $this->diagnostics() + [
            'texts' => [
                'title' => __('payplug_applepay_domain_title', 'payplug'),
                'state_synced' => __('payplug_applepay_domain_state_synced', 'payplug'),
                'state_not_synced' => __('payplug_applepay_domain_state_not_synced', 'payplug'),
                'last_success' => __('payplug_applepay_domain_last_success', 'payplug'),
                'last_error' => __('payplug_applepay_domain_last_error', 'payplug'),
                'never' => __('payplug_applepay_domain_never', 'payplug'),
                'resync' => __('payplug_applepay_domain_resync', 'payplug'),
                'resync_error' => __('payplug_applepay_domain_resync_error', 'payplug'),
                'root_file' => __('payplug_applepay_domain_root_file', 'payplug'),
            ],
        ];
    }
}
