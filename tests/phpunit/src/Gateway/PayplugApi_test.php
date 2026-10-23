<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\Exception\ConfigurationNotSetException;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Gateway\PayplugApi;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Service\Api;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use PHPUnit\Framework\TestCase;

class PayplugApi_test extends TestCase
{
    private $log_enabled_property;

    private $previous_log_enabled;

    protected function setUp(): void
    {
        parent::setUp();
        Payplug::setDefaultConfiguration(null);
    }

    protected function tearDown(): void
    {
        $reset_logger = fn () => new \WC_Logger();
        add_filter('woocommerce_logging_class', $reset_logger);
        wc_get_logger();
        remove_filter('woocommerce_logging_class', $reset_logger);
        if (null !== $this->log_enabled_property) {
            $this->log_enabled_property->setValue(null, $this->previous_log_enabled);
        }
        delete_option('woocommerce_payplug_settings');
        Payplug::setDefaultConfiguration(null);
        parent::tearDown();
    }

    public function test_init_uses_the_token_of_the_current_mode(): void
    {
        update_option('woocommerce_payplug_settings', [
            'mode' => false,
            'api_key' => '{"test":"sk_test_x","live":"sk_live_x"}',
            'jwt' => '{}',
        ]);

        $api = new PayplugApi($this->gateway_stub());
        $api->init();

        $this->assertSame('sk_test_x', $this->default_token());
        $this->assertSame('sk_test_x', $api->get_current_bearer_token());
        $this->assertNotEmpty(get_option('woocommerce_payplug_settings'));
    }

    /**
     * @dataProvider modes
     */
    public function test_init_logs_the_merchant_out_without_throwing_when_the_current_mode_has_no_token(bool $live): void
    {
        update_option('woocommerce_payplug_settings', $this->oauth_settings_without_token($live));

        $api = new PayplugApi($this->gateway_stub());
        $api->init();

        $this->assertSame('', $api->get_current_bearer_token());
        $this->assertNull($this->default_token());
        $this->assertFalse(get_option('woocommerce_payplug_settings'));
    }

    /**
     * @dataProvider modes
     */
    public function test_init_logs_why_the_merchant_is_logged_out_even_when_debug_logging_is_off(bool $live): void
    {
        update_option('woocommerce_payplug_settings', $this->oauth_settings_without_token($live));
        $this->log_enabled_property = new \ReflectionProperty(PayplugGateway::class, 'log_enabled');
        $this->log_enabled_property->setAccessible(true);
        $this->previous_log_enabled = $this->log_enabled_property->getValue();
        $this->log_enabled_property->setValue(null, false);
        $logger = $this->capture_wc_logs();

        $api = new PayplugApi($this->gateway_stub());
        $api->init();

        $mode = $live ? 'live' : 'test';
        $logout_entries = array_values(array_filter($logger->entries, fn (array $entry) => false !== strpos($entry['message'], sprintf('No PayPlug token for the %s mode, logging the merchant out', $mode))));
        $this->assertCount(1, $logout_entries);
        $this->assertSame('error', $logout_entries[0]['level']);
        $this->assertSame('payplug_gateway', $logout_entries[0]['context']['source']);
    }

    public function modes(): array
    {
        return [
            'test mode' => [false],
            'live mode' => [true],
        ];
    }

    public function test_init_uses_the_refreshed_jwt_when_the_current_token_is_missing(): void
    {
        $settings = $this->oauth_settings_without_token(false);
        $settings['oauth_client_data'] = '{"test":{"client_id":"id_test","client_secret":"secret_test"}}';
        update_option('woocommerce_payplug_settings', $settings);

        $api = $this->payplug_api_with(['access_token' => 'fresh_test_jwt', 'expires_date' => time() + 300]);
        $api->init();

        $this->assertSame('fresh_test_jwt', $this->default_token());
        $this->assertSame('fresh_test_jwt', $api->get_current_bearer_token());
        $stored = get_option('woocommerce_payplug_settings');
        $this->assertSame('fresh_test_jwt', json_decode($stored['api_key'], true)['test']);
    }

    public function test_init_logs_the_merchant_out_without_throwing_when_the_jwt_refresh_fails(): void
    {
        $settings = $this->oauth_settings_without_token(false);
        $settings['oauth_client_data'] = '{"test":{"client_id":"id_test","client_secret":"secret_test"}}';
        update_option('woocommerce_payplug_settings', $settings);

        $api = $this->payplug_api_with([]);
        $api->init();

        $this->assertSame('', $api->get_current_bearer_token());
        $this->assertNull($this->default_token());
        $this->assertFalse(get_option('woocommerce_payplug_settings'));
    }

    public function test_a_gateway_is_built_without_throwing_when_the_current_mode_has_no_token(): void
    {
        update_option('woocommerce_payplug_settings', $this->oauth_settings_without_token(false));

        $gateway = new PayplugGateway();

        $this->assertInstanceOf(PayplugGateway::class, $gateway);
        $this->assertFalse(get_option('woocommerce_payplug_settings'));
    }

    /**
     * OAuth2 account in the given mode whose token and client data for that mode are gone:
     * the JWT entry still marks the merchant as logged in, but nothing resolves a token.
     */
    private function oauth_settings_without_token(bool $live): array
    {
        $other_mode = $live ? 'test' : 'live';

        return [
            'enabled' => true,
            'mode' => $live,
            'api_key' => sprintf('{"%1$s":"%1$s_jwt"}', $other_mode),
            'jwt' => '{"live":{"access_token":"live_jwt"},"test":{"access_token":"test_jwt"}}',
            'oauth_client_data' => sprintf('{"%1$s":{"client_id":"id_%1$s","client_secret":"secret_%1$s"}}', $other_mode),
        ];
    }

    private function capture_wc_logs(): \WC_Logger
    {
        $logger = new class () extends \WC_Logger {
            public $entries = [];

            public function log($level, $message, $context = []): void
            {
                $this->entries[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };
        $use_logger = fn () => $logger;
        add_filter('woocommerce_logging_class', $use_logger);
        wc_get_logger();
        remove_filter('woocommerce_logging_class', $use_logger);

        return $logger;
    }

    private function default_token(): ?string
    {
        try {
            return Payplug::getDefaultConfiguration()->getToken();
        } catch (ConfigurationNotSetException $e) {
            return null;
        }
    }

    private function gateway_stub()
    {
        return new class () {
            use ServiceGetter;
        };
    }

    private function payplug_api_with(array $generated_jwt): PayplugApi
    {
        $api_service = new class ($generated_jwt) extends Api {
            private $generated_jwt;

            public function __construct(array $generated_jwt)
            {
                $this->generated_jwt = $generated_jwt;
            }

            public function get_bearer_token($mode = true)
            {
                return '';
            }

            public function generate_jwt($client_id = '', $client_secret = '')
            {
                return $this->generated_jwt;
            }
        };

        $payplug_api = new class ($this->gateway_stub()) extends PayplugApi {
            public $api_service;

            public function get_api()
            {
                return $this->api_service;
            }
        };
        $payplug_api->api_service = $api_service;

        return $payplug_api;
    }
}
