<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Upc\Adapters\WpUnifiedApiHttpClient;
use PHPUnit\Framework\TestCase;

class WpUnifiedApiHttpClient_test extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    public function testGetReturnsTheHttpStatusAndBody(): void
    {
        add_filter('pre_http_request', function () {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => '{"id":"pay_123"}',
                'headers' => [],
                'cookies' => [],
            ];
        });

        $result = (new WpUnifiedApiHttpClient())->get('https://example.test/api/payment-gateway/payments/pay_123');

        $this->assertSame(200, $result['status']);
        $this->assertSame('{"id":"pay_123"}', $result['body']);
    }

    public function testGetReturnsZeroStatusOnTransportFailure(): void
    {
        add_filter('pre_http_request', fn () => new \WP_Error('http_request_failed', 'Connection timed out.'));

        $result = (new WpUnifiedApiHttpClient())->get('https://example.test/api/payment-gateway/payments/pay_123');

        $this->assertSame(0, $result['status']);
        $this->assertSame('Connection timed out.', $result['body']);
    }

    public function testPostJsonSendsAJsonEncodedBodyAndReturnsTheHttpStatusAndBody(): void
    {
        $captured_args = null;
        add_filter('pre_http_request', function ($preempt, $args) use (&$captured_args) {
            $captured_args = $args;

            return [
                'response' => ['code' => 201, 'message' => 'Created'],
                'body' => '{"id":"pay_123"}',
                'headers' => [],
                'cookies' => [],
            ];
        }, 10, 2);

        $result = (new WpUnifiedApiHttpClient())->postJson('https://example.test/api/payment-gateway/payments', ['amount' => 1000]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('{"id":"pay_123"}', $result['body']);
        $this->assertSame('{"amount":1000}', $captured_args['body']);
        $this->assertSame('application/json', $captured_args['headers']['Content-Type']);
    }
}
