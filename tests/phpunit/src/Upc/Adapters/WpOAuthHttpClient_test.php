<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Upc\Adapters\WpOAuthHttpClient;
use PHPUnit\Framework\TestCase;

class WpOAuthHttpClient_test extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    public function testPostReturnsTheHttpStatusAndBody(): void
    {
        add_filter('pre_http_request', function () {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => '{"access_token":"abc"}',
                'headers' => [],
                'cookies' => [],
            ];
        });

        $result = (new WpOAuthHttpClient())->post('https://example.test/oauth2/token', ['grant_type' => 'client_credentials']);

        $this->assertSame(200, $result['status']);
        $this->assertSame('{"access_token":"abc"}', $result['body']);
    }

    public function testPostReturnsZeroStatusOnTransportFailure(): void
    {
        add_filter('pre_http_request', fn () => new \WP_Error('http_request_failed', 'Connection timed out.'));

        $result = (new WpOAuthHttpClient())->post('https://example.test/oauth2/token', []);

        $this->assertSame(0, $result['status']);
        $this->assertSame('Connection timed out.', $result['body']);
    }
}
