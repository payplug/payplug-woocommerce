<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\ITokenCache;

class WooCommerceTokenCache implements ITokenCache
{
    public function get(string $key): ?string
    {
        $value = get_transient('upc_token_' . $key);

        return false === $value ? null : (string) $value;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        set_transient('upc_token_' . $key, $value, $ttlSeconds);
    }

    public function delete(string $key): void
    {
        delete_transient('upc_token_' . $key);
    }
}
