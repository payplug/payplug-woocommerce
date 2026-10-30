<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Payplug\PayplugWoocommerce\Admin\SetupCallback;
use Payplug\PayplugWoocommerce\Admin\Vue;
use Payplug\PayplugWoocommerce\Service\Configuration;
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
        add_filter('pre_http_request', static fn () => new \WP_Error('http_request_blocked', 'HTTP requests are blocked in tests'));
        // a logged-in fixture would otherwise fetch the permissions and the account from the
        // PayPlug API, and log out on failure
        foreach (['test', 'live'] as $mode) {
            set_transient('payplug_permission_' . $mode, ['use_live_mode' => false]);
            set_transient('payplug_config_' . $mode, ['company_ref' => 'cr_1', 'permissions' => []]);
        }
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_payplug_settings');
        delete_option('woocommerce_currency');
        foreach (['test', 'live'] as $mode) {
            delete_transient('payplug_permission_' . $mode);
            delete_transient('payplug_config_' . $mode);
        }
        wp_set_current_user(0);
        wp_delete_user($this->user_id);
        remove_all_filters('pre_http_request');
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

    private function seed(string $currency, array $overrides = []): void
    {
        update_option('woocommerce_currency', $currency);
        update_option('woocommerce_payplug_settings', array_merge((new Configuration())->extract_options_from_fields(), $overrides));
    }

    private function legacy_session(): array
    {
        return [
            'mode' => false,
            'api_key' => '{"test":"sk_test_x","live":""}',
        ];
    }

    private function oauth_session(string $oauth_client_data = '{"test":{"client_id":"id","client_secret":"secret"},"live":[]}'): array
    {
        return [
            'mode' => false,
            'api_key' => '{"test":"t","live":""}',
            'jwt' => json_encode(['test' => ['access_token' => 't', 'expires_date' => time() + 3600]]),
            'oauth_client_data' => $oauth_client_data,
        ];
    }

    private function expected_eur_oauth_login_mode(string $register_url): array
    {
        return [
            'description' => __('payplug_section_oauth_login_description', 'payplug'),
            'form' => [
                'email' => [
                    'label' => __('payplug_section_login_email_label', 'payplug'),
                    'placeholder' => __('payplug_section_login_email_label', 'payplug'),
                ],
                'password' => [
                    'label' => __('payplug_section_login_password_label', 'payplug'),
                    'placeholder' => __('payplug_section_login_password_label', 'payplug'),
                ],
                'connexion' => __('payplug_section_login_connect', 'payplug'),
                'create_account' => __('payplug_section_login_not_registered', 'payplug'),
                'forgot_password' => __('payplug_section_login_forgot_password', 'payplug'),
                'error' => __('payplug_section_login_error', 'payplug'),
                'create_account_url' => 'https://portal.payplug.com/auth/signup',
                'forgot_password_url' => 'https://portal.payplug.com/forgot_password',
            ],
            'sso' => [
                'title' => __('payplug_section_oauth_login_title', 'payplug'),
                'description' => __('payplug_section_oauth_login_description', 'payplug'),
                'information' => __('payplug_section_oauth_info', 'payplug'),
                'button' => __('payplug_section_oauth_login_btn_connect', 'payplug'),
                'button_url' => $register_url,
            ],
        ];
    }

    public function test_eur_oauth_login_keeps_the_legacy_form_and_the_classic_login_information(): void
    {
        $this->seed('EUR');

        $oauth_login = (new Vue())->payplug_section_oauth_login();

        $register_url = $oauth_login['descriptions']['live']['sso']['button_url'];
        self::assertNotEmpty($register_url);
        self::assertSame([
            'name' => 'oauthLogin',
            'title' => __('payplug_section_logged_title', 'payplug'),
            'descriptions' => [
                'live' => $this->expected_eur_oauth_login_mode($register_url),
                'sandbox' => $this->expected_eur_oauth_login_mode($register_url),
            ],
        ], $oauth_login);
    }

    public function test_non_eur_oauth_login_has_no_legacy_form(): void
    {
        $this->seed('USD');

        $oauth_login = (new Vue())->payplug_section_oauth_login();

        foreach (['live', 'sandbox'] as $mode) {
            self::assertArrayNotHasKey('form', $oauth_login['descriptions'][$mode]);
        }
    }

    public function test_non_eur_oauth_login_has_no_classic_login_information(): void
    {
        $this->seed('USD');

        $oauth_login = (new Vue())->payplug_section_oauth_login();

        foreach (['live', 'sandbox'] as $mode) {
            self::assertArrayNotHasKey('information', $oauth_login['descriptions'][$mode]['sso']);
        }
    }

    public function test_non_eur_oauth_login_keeps_the_sso_block(): void
    {
        $this->seed('USD');

        $oauth_login = (new Vue())->payplug_section_oauth_login();

        self::assertSame('oauthLogin', $oauth_login['name']);
        foreach (['live', 'sandbox'] as $mode) {
            $description = $oauth_login['descriptions'][$mode];
            self::assertSame(__('payplug_section_oauth_login_description', 'payplug'), $description['description']);
            self::assertSame(['title', 'description', 'button', 'button_url'], array_keys($description['sso']));
            self::assertNotEmpty($description['sso']['button_url']);
        }
    }

    public function test_logged_section_invites_a_legacy_session_to_reconnect_on_a_non_eur_shop(): void
    {
        $this->seed('USD', $this->legacy_session());

        $logged = (new Vue())->payplug_section_logged();

        self::assertTrue($logged['oauth_reconnect']['active']);
        self::assertSame(__('payplug_section_oauth_reconnect_title', 'payplug'), $logged['oauth_reconnect']['title']);
        self::assertSame(__('payplug_section_oauth_reconnect_description', 'payplug'), $logged['oauth_reconnect']['description']);
    }

    public function test_logged_section_keeps_its_content_for_a_legacy_session_on_a_non_eur_shop(): void
    {
        $this->seed('USD', $this->legacy_session());

        $logged = (new Vue())->payplug_section_logged();

        foreach (['title', 'descriptions', 'options', 'inactive_modal', 'inactive_account'] as $key) {
            self::assertArrayHasKey($key, $logged);
        }
    }

    public function test_logged_section_has_no_reconnect_notice_for_an_oauth_session(): void
    {
        $this->seed('USD', $this->oauth_session());

        self::assertArrayNotHasKey('oauth_reconnect', (new Vue())->payplug_section_logged());
    }

    public function test_logged_section_has_no_reconnect_notice_for_an_oauth_session_without_credentials_for_the_mode(): void
    {
        $this->seed('USD', $this->oauth_session('{"test":[],"live":{"client_id":"id","client_secret":"secret"}}'));

        self::assertArrayNotHasKey('oauth_reconnect', (new Vue())->payplug_section_logged());
    }

    public function test_logged_section_has_no_reconnect_notice_on_a_eur_shop(): void
    {
        $this->seed('EUR', $this->legacy_session());

        self::assertArrayNotHasKey('oauth_reconnect', (new Vue())->payplug_section_logged());
    }

    private function standard_payment_method(array $data): array
    {
        return $data['payment_methods']['options'][0];
    }

    public function test_init_not_connected_on_a_non_eur_shop_offers_oauth2_only(): void
    {
        $this->seed('USD');

        $data = (new Vue())->init();

        self::assertArrayNotHasKey('logged', $data);
        self::assertArrayNotHasKey('form', $data['oauth_login']['descriptions']['live']);
        self::assertArrayNotHasKey('form', $data['oauth_login']['descriptions']['sandbox']);
        self::assertArrayNotHasKey('advanced_settings', $this->standard_payment_method($data));
    }

    public function test_init_legacy_session_on_a_non_eur_shop(): void
    {
        $this->seed('USD', $this->legacy_session());

        $data = (new Vue())->init();

        self::assertTrue($data['logged']['oauth_reconnect']['active']);
        // shown by the UI once the merchant logs out, without a reload
        self::assertArrayNotHasKey('form', $data['oauth_login']['descriptions']['live']);
        self::assertArrayHasKey('payment_methods', $data);
        self::assertArrayHasKey('status', $data);
        self::assertArrayNotHasKey('advanced_settings', $this->standard_payment_method($data));
        self::assertArrayNotHasKey('api_key', $data['payplug_wooc_settings']);
        self::assertArrayNotHasKey('jwt', $data['payplug_wooc_settings']);
        self::assertArrayNotHasKey('oauth_client_data', $data['payplug_wooc_settings']);
    }

    public function test_init_oauth_session_on_a_non_eur_shop_shows_the_identifier(): void
    {
        $this->seed('USD', $this->oauth_session());

        $data = (new Vue())->init();

        self::assertArrayNotHasKey('oauth_reconnect', $data['logged']);
        self::assertArrayHasKey('advanced_settings', $this->standard_payment_method($data));
    }

    public function test_init_oauth_session_without_credentials_for_the_mode_on_a_non_eur_shop(): void
    {
        $this->seed('USD', $this->oauth_session('{"test":[],"live":{"client_id":"id","client_secret":"secret"}}'));

        $data = (new Vue())->init();

        self::assertArrayNotHasKey('oauth_reconnect', $data['logged']);
        self::assertArrayNotHasKey('advanced_settings', $this->standard_payment_method($data));
    }

    public function test_init_legacy_session_on_a_eur_shop_is_unchanged(): void
    {
        $this->seed('EUR', $this->legacy_session());

        $data = (new Vue())->init();

        self::assertArrayNotHasKey('oauth_reconnect', $data['logged']);
        self::assertArrayHasKey('form', $data['oauth_login']['descriptions']['live']);
        self::assertArrayHasKey('information', $data['oauth_login']['descriptions']['live']['sso']);
        self::assertArrayNotHasKey('advanced_settings', $this->standard_payment_method($data));
    }

    public function test_init_follows_the_shop_currency_on_each_request(): void
    {
        $this->seed('EUR', $this->legacy_session());

        $eur = (new Vue())->init();
        self::assertArrayHasKey('form', $eur['oauth_login']['descriptions']['live']);
        self::assertArrayNotHasKey('oauth_reconnect', $eur['logged']);

        update_option('woocommerce_currency', 'USD');
        $usd = (new Vue())->init();
        self::assertArrayNotHasKey('form', $usd['oauth_login']['descriptions']['live']);
        self::assertTrue($usd['logged']['oauth_reconnect']['active']);

        update_option('woocommerce_currency', 'EUR');
        $back_to_eur = (new Vue())->init();
        self::assertArrayHasKey('form', $back_to_eur['oauth_login']['descriptions']['live']);
        self::assertArrayNotHasKey('oauth_reconnect', $back_to_eur['logged']);
    }
}
