<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\IDomainAssociationStorage;

class WooCommerceDomainAssociationStorage implements IDomainAssociationStorage
{
    public const CONTENT_OPTION = 'payplug_apple_pay_well_known';
    public const STATUS_OPTION = 'payplug_apple_pay_well_known_status';

    public function readContent(): ?string
    {
        $content = get_option(self::CONTENT_OPTION, '');

        return is_string($content) && '' !== $content ? $content : null;
    }

    /**
     * Stored byte for byte (no unslash, trim or escaping), not autoloaded: it is only read on the
     * Apple Pay route and in the back office.
     */
    public function writeContent(string $content): void
    {
        update_option(self::CONTENT_OPTION, $content, false);
    }

    public function readStatus(): array
    {
        $stored = get_option(self::STATUS_OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        $error = $stored['lastError'] ?? null;

        return [
            'lastAttemptAt' => self::timestamp($stored['lastAttemptAt'] ?? null),
            'lastSuccessAt' => self::timestamp($stored['lastSuccessAt'] ?? null),
            'lastError' => is_string($error) && '' !== $error ? $error : null,
        ];
    }

    public function writeStatus(array $status): void
    {
        update_option(self::STATUS_OPTION, $status, false);
    }

    /**
     * @param mixed $value
     */
    private static function timestamp($value): ?int
    {
        if (is_string($value) && 1 === preg_match('/^\d+$/D', $value)) {
            return (int) $value;
        }

        return is_int($value) ? $value : null;
    }
}
