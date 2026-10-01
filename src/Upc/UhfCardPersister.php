<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Model\UhfCard;

class UhfCardPersister
{
    private const ALLOWED_BRANDS = ['CB', 'VISA', 'MASTERCARD'];

    /**
     * @param array{brand?: string, last4?: string, exp_month?: int, exp_year?: int} $fallback client-submitted values, sanitized here again defensively
     * @param array{brand?: string, last4?: string, exp_month?: int, exp_year?: int} $fetched values read back from the Unified API (operation fetch or webhook body) - authoritative when present
     */
    public function persist(int $customer_id, string $alias_id, string $mode, array $fallback, array $fetched): bool
    {
        $brand = $this->sanitize_brand($fetched['brand'] ?? '') ?: $this->sanitize_brand($fallback['brand'] ?? '');
        $last4 = $this->sanitize_last4($fetched['last4'] ?? '') ?: $this->sanitize_last4($fallback['last4'] ?? '');
        $exp_month = $this->sanitize_month($fetched['exp_month'] ?? 0) ?: $this->sanitize_month($fallback['exp_month'] ?? 0);
        $exp_year = $this->sanitize_year($fetched['exp_year'] ?? 0) ?: $this->sanitize_year($fallback['exp_year'] ?? 0);

        if ('' === $brand || '' === $last4 || 0 === $exp_month || 0 === $exp_year) {
            return false;
        }

        return false !== UhfCard::insert($customer_id, $alias_id, $brand, $last4, $exp_month, $exp_year, $mode);
    }

    private function sanitize_brand($value): string
    {
        $value = strtoupper((string) $value);

        return in_array($value, self::ALLOWED_BRANDS, true) ? $value : '';
    }

    private function sanitize_last4($value): string
    {
        $value = (string) $value;

        return 1 === preg_match('/^\d{4}$/', $value) ? $value : '';
    }

    private function sanitize_month($value): int
    {
        $value = (int) $value;

        return ($value >= 1 && $value <= 12) ? $value : 0;
    }

    private function sanitize_year($value): int
    {
        $value = (int) $value;
        $current_year = (int) gmdate('Y');

        return ($value >= $current_year && $value <= $current_year + 30) ? $value : 0;
    }
}
