<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\IUnifiedApiHttpClient;

class WpUnifiedApiHttpClient implements IUnifiedApiHttpClient
{
    public function get(string $url, array $headers = []): array
    {
        $response = wp_remote_get($url, [
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

    public function postJson(string $url, array $body, array $headers = []): array
    {
        $response = wp_remote_post($url, [
            'body' => wp_json_encode($body),
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
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
