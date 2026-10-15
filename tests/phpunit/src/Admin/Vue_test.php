<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Payplug\PayplugWoocommerce\Admin\SetupCallback;
use Payplug\PayplugWoocommerce\Admin\Vue;
use PHPUnit\Framework\TestCase;

class Vue_test extends TestCase
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
        wp_set_current_user(0);
        wp_delete_user($this->user_id);
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    public function test_register_url_sends_nonce_only_in_setup_redirection_uri(): void
    {
        $section = (new Vue())->payplug_section_oauth_login();
        parse_str((string) wp_parse_url($section['descriptions']['live']['sso']['button_url'], PHP_URL_QUERY), $register_query);

        parse_str((string) wp_parse_url($register_query['setup_redirection_uri'], PHP_URL_QUERY), $setup_query);
        self::assertNotFalse(wp_verify_nonce($setup_query[SetupCallback::SETUP_NONCE_PARAM] ?? '', SetupCallback::SETUP_NONCE_ACTION));
        self::assertSame(SetupCallback::oauth_callback_uri(), $register_query['oauth_callback_uri']);
    }
}
