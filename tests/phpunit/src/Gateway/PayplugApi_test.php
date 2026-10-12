<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Payplug\PayplugWoocommerce\Gateway\PayplugApi;
use PHPUnit\Framework\TestCase;

class PayplugApi_test extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SETUP_URI = 'https://shop.test/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payplug&payplug_setup_nonce=abc';
    private const CALLBACK_URI = 'https://shop.test/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payplug';

    public function test_retrieve_register_url_uses_distinct_setup_and_callback_uris(): void
    {
        $api = $this->api_expecting_register_url_params([self::SETUP_URI, self::CALLBACK_URI]);

        self::assertSame('https://portal.test/register', $api->retrieve_register_url(self::SETUP_URI, self::CALLBACK_URI));
    }

    public function test_retrieve_register_url_with_one_argument_reuses_it_for_both(): void
    {
        $api = $this->api_expecting_register_url_params([self::CALLBACK_URI, self::CALLBACK_URI]);

        self::assertSame('https://portal.test/register', $api->retrieve_register_url(self::CALLBACK_URI));
    }

    private function api_expecting_register_url_params(array $params): PayplugApi
    {
        $api = \Mockery::mock(PayplugApi::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $api->shouldReceive('do_request_with_fallback')
            ->once()
            ->with('\Payplug\Authentication::getRegisterUrl', $params)
            ->andReturn('https://portal.test/register');

        return $api;
    }
}
