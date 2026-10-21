<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Payplug\PayplugWoocommerce\Gateway\AccountGateway;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\GatewayLogSpy;
use PHPUnit\Framework\TestCase;

class AccountGatewayRefresh_test extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const CACHED_PERMISSIONS = [
        'bancontact' => [
            'enabled' => true,
            'countries' => '["BE"]',
            'amounts' => '{"min":{"EUR":30},"max":{"EUR":2000000}}',
        ],
    ];

    /**
     * @var GatewayLogSpy
     */
    private $log;

    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_payplug_settings', [
            'mode' => true,
            'company_id' => 'acc_test_1',
            'apm_cache_mode' => 'live',
            'payment_methods' => [
                'permissions' => self::CACHED_PERMISSIONS,
                'configuration' => ['bancontact' => ['active' => true]],
            ],
        ]);
        $this->log = GatewayLogSpy::start();
    }

    protected function tearDown(): void
    {
        $this->log->stop();
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    /**
     * @dataProvider failed_responses
     *
     * @param mixed $response
     */
    public function test_failed_refresh_keeps_the_cache_and_the_cache_mode($response): void
    {
        $before = get_option('woocommerce_payplug_settings');
        $account = $this->account_gateway(function ($mock) use ($response): void {
            $mock->shouldReceive('fetch_account')->with('test')->andReturn($response);
        });

        self::assertFalse($account->refresh_payment_permissions('test'));
        self::assertSame($before, get_option('woocommerce_payplug_settings'));
        self::assertSame('warning', $this->log->entries[0]['level']);
    }

    public function failed_responses(): array
    {
        return [
            'null (API error)' => [null],
            'empty response' => [[]],
            'no payment_methods' => [['id' => 'acc_1', 'configuration' => ['currencies' => ['EUR']]]],
        ];
    }

    public function test_exception_from_the_api_is_caught(): void
    {
        $before = get_option('woocommerce_payplug_settings');
        $account = $this->account_gateway(function ($mock): void {
            $mock->shouldReceive('fetch_account')->andThrow(new \RuntimeException('timeout'));
        });

        self::assertFalse($account->refresh_payment_permissions('live'));
        self::assertSame($before, get_option('woocommerce_payplug_settings'));
    }

    public function test_successful_refresh_stores_the_permissions_with_the_mode_used(): void
    {
        $account = $this->account_gateway(function ($mock): void {
            $mock->shouldReceive('fetch_account')->with('test')->andReturn($this->account_response());
        });

        self::assertTrue($account->refresh_payment_permissions('test'));

        $options = get_option('woocommerce_payplug_settings');
        self::assertSame('test', $options['apm_cache_mode']);
        self::assertSame('["IT"]', $options['payment_methods']['permissions']['satispay']['countries']);
        self::assertSame('{"min":{"EUR":30},"max":{"EUR":2000000}}', $options['payment_methods']['permissions']['satispay']['amounts']);
        self::assertSame('acc_test_1', $options['company_id']);
        self::assertSame(['bancontact' => ['active' => true]], $options['payment_methods']['configuration']);
    }

    public function test_empty_mode_uses_the_current_mode(): void
    {
        $account = $this->account_gateway(function ($mock): void {
            $mock->shouldReceive('fetch_account')->with('live')->once()->andReturn($this->account_response());
        });

        self::assertTrue($account->refresh_payment_permissions());
        self::assertSame('live', get_option('woocommerce_payplug_settings')['apm_cache_mode']);
    }

    public function test_initialize_permission_does_not_wipe_the_cache_on_an_empty_response(): void
    {
        $api = \Mockery::mock();
        $api->shouldReceive('get_account')->andReturn(['result' => true, 'response' => ['id' => 'acc_test_1']]);
        $account = $this->account_gateway(function ($mock) use ($api): void {
            $mock->shouldReceive('fetch_account')->andReturn(null);
            $mock->shouldReceive('get_api')->andReturn($api);
        });

        $account->initialize_permisssion();

        $options = get_option('woocommerce_payplug_settings');
        self::assertSame(self::CACHED_PERMISSIONS, $options['payment_methods']['permissions']);
        self::assertSame('live', $options['apm_cache_mode']);
    }

    private function account_gateway(callable $configure): AccountGateway
    {
        $mock = \Mockery::mock(AccountGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $configure($mock);

        return $mock;
    }

    private function account_response(): array
    {
        return [
            'id' => 'acc_live_1',
            'country' => 'FR',
            'configuration' => [
                'currencies' => ['EUR'],
                'min_amounts' => ['EUR' => 99],
                'max_amounts' => ['EUR' => 2000000],
            ],
            'permissions' => [
                'can_create_installment_plan' => true,
                'can_create_deferred_payment' => true,
                'can_save_cards' => true,
            ],
            'payment_methods' => [
                'satispay' => [
                    'enabled' => true,
                    'allowed_countries' => ['IT'],
                    'min_amounts' => ['EUR' => 30],
                    'max_amounts' => ['EUR' => 2000000],
                ],
            ],
        ];
    }
}
