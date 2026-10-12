<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Payplug\PayplugWoocommerce\Admin\SetupCallback;
use PHPUnit\Framework\TestCase;

class SetupCallback_test extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SETTINGS = [
        'enabled' => true,
        'api_key' => '{"live":"sk_live_x","test":"sk_test_x"}',
        'email' => 'merchant@example.com',
        'company_id' => 'C1',
    ];

    private array $user_ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        set_current_screen('woocommerce_page_wc-settings');
        update_option('woocommerce_payplug_settings', self::SETTINGS);
        $_GET = [
            'page' => 'wc-settings',
            'tab' => 'checkout',
            'section' => 'payplug',
            'client_id' => 'cid',
            'company_id' => 'coid',
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->user_ids as $user_id) {
            wp_delete_user($user_id);
        }
        $this->user_ids = [];
        wp_set_current_user(0);
        $_GET = [];
        $GLOBALS['current_screen'] = null;
        remove_filter('wp_doing_ajax', '__return_true');
        remove_all_actions('admin_notices');
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    public function test_anonymous_request_does_not_modify_settings(): void
    {
        wp_set_current_user(0);

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_subscriber_does_not_modify_settings(): void
    {
        $this->login_as('subscriber');

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_editor_does_not_modify_settings(): void
    {
        $this->login_as('editor');

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_ajax_request_does_not_modify_settings(): void
    {
        $this->login_as('administrator');
        $_GET[SetupCallback::SETUP_NONCE_PARAM] = wp_create_nonce(SetupCallback::SETUP_NONCE_ACTION);
        add_filter('wp_doing_ajax', '__return_true');

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_admin_without_nonce_does_not_start_oauth_nor_modify_settings(): void
    {
        $this->login_as('administrator');

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_admin_with_invalid_nonce_does_not_start_oauth_nor_modify_settings(): void
    {
        $this->login_as('administrator');
        $_GET[SetupCallback::SETUP_NONCE_PARAM] = 'bad';

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_admin_with_nonce_of_another_user_does_not_start_oauth(): void
    {
        $this->login_as('administrator');
        $_GET[SetupCallback::SETUP_NONCE_PARAM] = wp_create_nonce(SetupCallback::SETUP_NONCE_ACTION);
        $this->login_as('administrator');

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_admin_with_nonce_for_another_action_does_not_start_oauth(): void
    {
        $this->login_as('administrator');
        $_GET[SetupCallback::SETUP_NONCE_PARAM] = wp_create_nonce('wp_rest');

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_admin_with_invalid_nonce_queues_an_error_notice(): void
    {
        $this->login_as('administrator');
        $_GET[SetupCallback::SETUP_NONCE_PARAM] = 'bad';

        $this->sut_never_starting_oauth()->handle_setup_callback();

        ob_start();
        do_action('admin_notices');
        $notices = ob_get_clean();
        self::assertStringContainsString('notice-error', $notices);
        self::assertStringContainsString('The PayPlug setup link is invalid or has expired.', $notices);
    }

    public function test_admin_with_valid_nonce_starts_oauth_once(): void
    {
        $this->login_as('administrator');
        $_GET[SetupCallback::SETUP_NONCE_PARAM] = wp_create_nonce(SetupCallback::SETUP_NONCE_ACTION);

        $sut = $this->sut();
        $sut->shouldReceive('start_oauth')->once()->with('cid', 'coid');

        $sut->handle_setup_callback();
    }

    public function test_shop_manager_with_valid_nonce_starts_oauth(): void
    {
        $this->login_as('shop_manager');
        $_GET[SetupCallback::SETUP_NONCE_PARAM] = wp_create_nonce(SetupCallback::SETUP_NONCE_ACTION);

        $sut = $this->sut();
        $sut->shouldReceive('start_oauth')->once()->with('cid', 'coid');

        $sut->handle_setup_callback();
    }

    public function test_admin_without_setup_params_does_not_start_oauth(): void
    {
        $this->login_as('administrator');
        unset($_GET['client_id'], $_GET['company_id']);

        $this->sut_never_starting_oauth()->handle_setup_callback();

        self::assertSame(self::SETTINGS, get_option('woocommerce_payplug_settings'));
    }

    public function test_oauth_callback_uri_has_no_nonce_and_matches_legacy_value(): void
    {
        $this->login_as('administrator');

        $uri = SetupCallback::oauth_callback_uri();

        self::assertSame(get_admin_url(null, '/admin.php?page=wc-settings&tab=checkout&section=payplug'), $uri);
        self::assertSame(admin_url('admin.php?page=wc-settings&tab=checkout&section=payplug'), $uri);
        self::assertStringNotContainsString(SetupCallback::SETUP_NONCE_PARAM, $uri);
    }

    public function test_setup_redirection_uri_carries_a_valid_payplug_setup_nonce(): void
    {
        $this->login_as('administrator');

        $uri = SetupCallback::setup_redirection_uri();
        parse_str((string) wp_parse_url($uri, PHP_URL_QUERY), $query);
        $nonce = $query[SetupCallback::SETUP_NONCE_PARAM] ?? '';
        unset($query[SetupCallback::SETUP_NONCE_PARAM]);

        self::assertNotFalse(wp_verify_nonce($nonce, SetupCallback::SETUP_NONCE_ACTION));
        self::assertSame(['page' => 'wc-settings', 'tab' => 'checkout', 'section' => 'payplug'], $query);
        self::assertStringStartsWith(SetupCallback::oauth_callback_uri(), $uri);
    }

    /**
     * @return SetupCallback&\Mockery\MockInterface
     */
    private function sut(): SetupCallback
    {
        return \Mockery::mock(SetupCallback::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
    }

    private function sut_never_starting_oauth(): SetupCallback
    {
        $sut = $this->sut();
        $sut->shouldNotReceive('start_oauth');

        return $sut;
    }

    private function login_as(string $role): int
    {
        $user_id = wp_insert_user([
            'user_login' => uniqid('payplug_' . $role . '_'),
            'user_pass' => wp_generate_password(),
            'role' => $role,
        ]);
        self::assertIsInt($user_id);
        $this->user_ids[] = $user_id;
        wp_set_current_user($user_id);

        return $user_id;
    }
}
