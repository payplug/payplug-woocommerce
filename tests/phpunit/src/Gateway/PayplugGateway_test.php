<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use PHPUnit\Framework\TestCase;

class PayplugGateway_test extends TestCase
{
    private int $user_id = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user_id = wp_insert_user([
            'user_login' => uniqid('payplug_admin_'),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
        ]);
        wp_set_current_user($this->user_id);
    }

    protected function tearDown(): void
    {
        wp_deregister_script('app.js');
        wp_deregister_script('chunk-vendors.js');
        wp_deregister_style('app.css');
        wp_set_current_user(0);
        wp_delete_user($this->user_id);
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    public function test_admin_options_localizes_rest_url_with_wp_rest_nonce(): void
    {
        ob_start();
        (new PayplugGateway())->admin_options();
        ob_end_clean();

        $rest_url = $this->localized_admin_config()['rest_url'];
        parse_str((string) wp_parse_url($rest_url, PHP_URL_QUERY), $query);

        self::assertNotFalse(wp_verify_nonce($query['_wpnonce'] ?? '', 'wp_rest'));
        self::assertStringStartsWith(get_home_url() . '/?', $rest_url);
        self::assertStringEndsWith('&rest_route=/payplug_api/', $rest_url);
    }

    private function localized_admin_config(): array
    {
        $data = (string) wp_scripts()->get_data('app.js', 'data');
        self::assertSame(1, preg_match('/var payplug_admin_config = (\{.*\});/s', $data, $matches));

        return json_decode($matches[1], true);
    }
}
