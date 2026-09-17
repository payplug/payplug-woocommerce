<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\IOAuthHttpClient;

class WpOAuthHttpClient implements IOAuthHttpClient
{
    public function post(string $url, array $formParams, array $headers = []): array
    {
        $response = wp_remote_post($url, [
            'body' => $formParams,
            'headers' => $headers,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return ['status' => 0, 'body' => $response->get_error_message()];
        }

        return [
            'status' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
        ];
    }
}
