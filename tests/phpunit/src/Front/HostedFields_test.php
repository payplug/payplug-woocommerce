<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\HostedFields;

class HostedFields_test extends \WP_Ajax_UnitTestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
        $_REQUEST = [];
        parent::tearDown();
    }

    public function testReceiveTokenSucceedsWithHfToken(): void
    {
        $_POST['nonce'] = wp_create_nonce('woocommerce-process_checkout');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['hfToken'] = 'hf_token_123';
        $_POST['selectedBrand'] = 'visa';
        $_POST['save_card'] = '1';

        $controller = new HostedFields();

        ob_start();

        try {
            $controller->receive_token();
            $this->fail('Expected wp_send_json_success() to halt execution.');
        } catch (\WPDieException $e) {
            // expected: wp_send_json_success() halts via wp_die()
        }

        $this->assertStringContainsString('"success":true', $this->_last_response);
        $this->assertStringContainsString('"hfToken":"hf_token_123"', $this->_last_response);
        $this->assertStringContainsString('"selectedBrand":"visa"', $this->_last_response);
        $this->assertStringContainsString('"save_card":true', $this->_last_response);
    }

    public function testReceiveTokenFailsWithoutHfToken(): void
    {
        $_POST['nonce'] = wp_create_nonce('woocommerce-process_checkout');
        $_REQUEST['nonce'] = $_POST['nonce'];

        $controller = new HostedFields();

        ob_start();

        try {
            $controller->receive_token();
            $this->fail('Expected wp_send_json_error() to halt execution.');
        } catch (\WPDieException $e) {
            // expected: wp_send_json_error() halts via wp_die()
        }

        $this->assertStringContainsString('"success":false', $this->_last_response);
    }
}
