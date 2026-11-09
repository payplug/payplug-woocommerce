<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Payplug\PayplugWoocommerce\Admin\Ajax;
use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\DomainAssociationFixtures;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationBootstrap;
use PHPUnit\Framework\TestCase;

class Ajax_test extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const LIVE_ONLY_OAUTH = '{"test":[],"live":{"client_id":"id","client_secret":"secret"}}';
    private const TEST_ONLY_OAUTH = '{"test":{"client_id":"id","client_secret":"secret"},"live":[]}';

    private const ROUTES = [
        'save',
        'init',
        'login',
        'logout',
        'refresh_keys',
        'check_requirements',
        'bancontact_permissions',
        'applepay_permissions',
        'american_express_permissions',
        'oney_permissions',
        'one_click_permissions',
        'satispay_permissions',
        'mybank_permissions',
        'ideal_permissions',
        'integrated_permissions',
        'wero_permissions',
        'bizum_permissions',
        'scalapay_permissions',
        'apple_pay_resync',
    ];

    private array $user_ids = [];

    private $rest_api_init_hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rest_api_init_hook = clone $GLOBALS['wp_filter']['rest_api_init'];
    }

    protected function tearDown(): void
    {
        $GLOBALS['wp_filter']['rest_api_init'] = $this->rest_api_init_hook;
        $GLOBALS['wp_rest_server'] = null;
        foreach ($this->user_ids as $user_id) {
            wp_delete_user($user_id);
        }
        $this->user_ids = [];
        wp_set_current_user(0);
        remove_filter('wp_doing_ajax', '__return_true');
        remove_all_filters('wp_die_ajax_handler');
        delete_option('woocommerce_payplug_settings');
        delete_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION);
        delete_option(WooCommerceDomainAssociationStorage::STATUS_OPTION);
        DomainAssociationBootstrap::reset();
        remove_all_filters('pre_http_request');
        unset($GLOBALS['wp_rest_auth_cookie'], $_REQUEST['_wpnonce']);
        parent::tearDown();
    }

    /**
     * @dataProvider empty_bodies
     */
    public function test_save_action_with_empty_or_malformed_body_changes_nothing(?array $body): void
    {
        $settings = [
            'enabled' => true,
            'mode' => true,
            'api_key' => '{"live":"sk_live_x","test":"sk_test_x"}',
            'payment_methods' => ['configuration' => ['payplug' => ['active' => true]]],
        ];
        update_option('woocommerce_payplug_settings', $settings);

        $account = \Mockery::mock();
        $account->shouldReceive('is_logged')->andReturn(true);
        $ajax = \Mockery::mock(Ajax::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $ajax->shouldReceive('get_gateway')->with('account')->andReturn($account);
        $ajax->shouldReceive('get_json_body')->andReturn($body);

        $output = $this->capture_json_response(function () use ($ajax): void {
            $ajax->save_action(new \WP_REST_Request('POST', '/payplug_api/save'));
        });

        self::assertFalse($output['success']);
        self::assertSame(400, http_response_code());
        self::assertSame($settings, get_option('woocommerce_payplug_settings'));
    }

    public function empty_bodies(): array
    {
        return [
            'empty json object' => [[]],
            'malformed json' => [null],
            'unrelated keys only' => [['foo' => 1]],
            'only some of the global keys' => [['payplug_enable' => true]],
        ];
    }

    private function capture_json_response(callable $callback): array
    {
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', static function (): callable {
            return static function (): void {
                throw new \RuntimeException('wp_die');
            };
        });

        ob_start();
        try {
            $callback();
            self::fail('wp_send_json was not called');
        } catch (\RuntimeException $e) {
            self::assertSame('wp_die', $e->getMessage());
        } finally {
            $output = ob_get_clean();
        }

        return json_decode($output, true);
    }

    public function test_permission_is_evaluated_per_request_not_at_construction(): void
    {
        $this->boot_routes_as(0);

        $this->login_as('administrator');

        $this->assert_permission_for_every_route(true);
    }

    public function test_permission_granted_at_construction_is_not_reused_for_an_anonymous_request(): void
    {
        $this->boot_routes_as($this->login_as('administrator'));

        wp_set_current_user(0);

        $this->assert_permission_for_every_route(false);
    }

    public function test_shop_manager_is_allowed(): void
    {
        $this->boot_routes_as(0);

        $this->login_as('shop_manager');

        $this->assert_permission_for_every_route(true);
    }

    public function test_editor_is_forbidden(): void
    {
        $this->boot_routes_as($this->login_as('editor'));

        $response = rest_do_request(new \WP_REST_Request('POST', '/payplug_api/logout'));

        self::assertSame(403, $response->get_status());
        self::assertSame('rest_forbidden', $response->get_data()['code']);
        $this->assert_permission_for_every_route(false);
    }

    public function test_subscriber_is_forbidden(): void
    {
        $this->boot_routes_as($this->login_as('subscriber'));

        $response = rest_do_request(new \WP_REST_Request('POST', '/payplug_api/logout'));

        self::assertSame(403, $response->get_status());
        $this->assert_permission_for_every_route(false);
    }

    public function test_anonymous_is_forbidden(): void
    {
        $this->boot_routes_as(0);

        $response = rest_do_request(new \WP_REST_Request('POST', '/payplug_api/logout'));

        self::assertSame(401, $response->get_status());
        self::assertSame('rest_forbidden', $response->get_data()['code']);
    }

    /**
     * @dataProvider routes
     */
    public function test_get_is_not_routed(string $route): void
    {
        $this->boot_routes_as(0);

        $response = rest_do_request(new \WP_REST_Request('GET', '/payplug_api/' . $route));

        self::assertSame(404, $response->get_status());
        self::assertSame('rest_no_route', $response->get_data()['code']);
    }

    /**
     * @dataProvider routes
     */
    public function test_post_is_the_only_method_routed(string $route): void
    {
        $this->boot_routes_as(0);

        $endpoints = rest_get_server()->get_routes('payplug_api')['/payplug_api/' . $route];

        foreach ($endpoints as $endpoint) {
            self::assertSame(['POST' => true], $endpoint['methods']);
        }
    }

    public function test_apple_pay_resync_answers_the_documented_shape_to_a_shop_manager(): void
    {
        $this->boot_routes_as($this->login_as('shop_manager'));
        $this->answer_http_with(200, DomainAssociationFixtures::valid());

        $response = rest_do_request(new \WP_REST_Request('POST', '/payplug_api/apple_pay_resync'));

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertTrue($data['success']);
        self::assertSame('updated', $data['status']);
        self::assertSame(['state', 'last_attempt_at', 'last_success_at', 'last_error', 'root_file_present'], array_keys($data['diagnostics']));
        self::assertSame('synced', $data['diagnostics']['state']);
        self::assertSame(DomainAssociationFixtures::valid(), get_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION));
    }

    public function test_apple_pay_resync_failure_still_answers_200_with_the_error(): void
    {
        $this->boot_routes_as($this->login_as('administrator'));
        $this->answer_http_with(503, '<html>maintenance</html>');

        $response = rest_do_request(new \WP_REST_Request('POST', '/payplug_api/apple_pay_resync'));

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();
        self::assertFalse($data['success']);
        self::assertSame('failed', $data['status']);
        self::assertSame('not_synced', $data['diagnostics']['state']);
        self::assertStringContainsString('Unexpected HTTP status 503', $data['diagnostics']['last_error']);
    }

    /**
     * @dataProvider refused_roles
     */
    public function test_apple_pay_resync_is_refused_without_manage_woocommerce(string $role, int $status): void
    {
        $this->boot_routes_as('' === $role ? 0 : $this->login_as($role));
        $this->answer_http_with(200, DomainAssociationFixtures::valid());

        $response = rest_do_request(new \WP_REST_Request('POST', '/payplug_api/apple_pay_resync'));

        self::assertSame($status, $response->get_status());
        self::assertFalse(get_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION));
        self::assertFalse(get_option(WooCommerceDomainAssociationStorage::STATUS_OPTION));
    }

    public function refused_roles(): array
    {
        return [
            'anonymous' => ['', 401],
            'customer' => ['customer', 403],
            'subscriber' => ['subscriber', 403],
            'editor' => ['editor', 403],
        ];
    }

    public function test_cookie_authenticated_rest_calls_need_the_wp_rest_nonce(): void
    {
        $user_id = $this->login_as('administrator');
        $GLOBALS['wp_rest_auth_cookie'] = true;

        self::assertTrue(apply_filters('rest_authentication_errors', null));
        self::assertSame(0, get_current_user_id());

        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce'] = 'not-a-nonce';
        $error = apply_filters('rest_authentication_errors', null);
        self::assertInstanceOf(\WP_Error::class, $error);
        self::assertSame('rest_cookie_invalid_nonce', $error->get_error_code());

        $_REQUEST['_wpnonce'] = wp_create_nonce('wp_rest');
        self::assertTrue(apply_filters('rest_authentication_errors', null));
        self::assertSame($user_id, get_current_user_id());
    }

    public function routes(): array
    {
        return array_combine(self::ROUTES, array_map(static fn (string $route): array => [$route], self::ROUTES));
    }

    private function boot_routes_as(int $user_id): void
    {
        wp_set_current_user($user_id);
        new Ajax();
        $GLOBALS['wp_rest_server'] = new \WP_REST_Server();
        do_action('rest_api_init', $GLOBALS['wp_rest_server']);
    }

    private function assert_permission_for_every_route(bool $expected): void
    {
        $routes = rest_get_server()->get_routes('payplug_api');
        foreach (self::ROUTES as $route) {
            self::assertArrayHasKey('/payplug_api/' . $route, $routes);
            foreach ($routes['/payplug_api/' . $route] as $endpoint) {
                $request = new \WP_REST_Request('POST', '/payplug_api/' . $route);
                self::assertSame($expected, call_user_func($endpoint['permission_callback'], $request), $route);
            }
        }
    }

    private function answer_http_with(int $status, string $body): void
    {
        add_filter('pre_http_request', static fn () => [
            'headers' => [],
            'body' => $body,
            'response' => ['code' => $status, 'message' => ''],
            'cookies' => [],
            'filename' => null,
        ]);
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

    private function stored_options(string $oauth_client_data, bool $live, string $identifier): array
    {
        $options = (new Configuration())->extract_options_from_fields();
        $options['mode'] = $live;
        $options['oauth_client_data'] = $oauth_client_data;
        $options['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $options['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = $identifier;
        update_option('woocommerce_payplug_settings', $options);

        return $options;
    }

    /**
     * wp_send_json_error() only goes through wp_die() during an AJAX request (a plain die
     * otherwise), so an unexpected validation error fails the test instead of exiting.
     */
    private function resolve_identifier(array $data, string $embedded_mode, array $stored): string
    {
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', static function (): callable {
            return static function (): void {
                throw new \RuntimeException('wp_die');
            };
        });

        return (new Ajax())->resolve_hosted_fields_identifier($data, $embedded_mode, $stored);
    }

    private function assertIdentifierRequired(callable $save): void
    {
        $response = $this->capture_json_response($save);

        self::assertFalse($response['success']);
        self::assertSame('hosted_fields_identifier_required', $response['data']['msg']);
    }

    public function test_switching_to_a_mode_with_oauth2_does_not_require_the_hidden_identifier(): void
    {
        $stored = $this->stored_options(self::LIVE_ONLY_OAUTH, false, '');

        $identifier = $this->resolve_identifier(['payplug_sandbox' => false], 'hosted_fields', $stored);

        self::assertSame('', $identifier);
    }

    public function test_switching_away_from_the_displayed_mode_still_requires_the_shown_identifier(): void
    {
        $stored = $this->stored_options(self::LIVE_ONLY_OAUTH, true, '');

        $this->assertIdentifierRequired(static function () use ($stored): void {
            (new Ajax())->resolve_hosted_fields_identifier(['payplug_sandbox' => true, 'identifier' => ''], 'hosted_fields', $stored);
        });
    }

    public function test_shown_identifier_left_empty_is_rejected(): void
    {
        $stored = $this->stored_options(self::TEST_ONLY_OAUTH, false, 'ACC-1');

        $this->assertIdentifierRequired(static function () use ($stored): void {
            (new Ajax())->resolve_hosted_fields_identifier(['identifier' => '   '], 'hosted_fields', $stored);
        });
    }

    public function test_submitted_identifier_is_sanitized_and_saved(): void
    {
        $stored = $this->stored_options(self::TEST_ONLY_OAUTH, false, 'ACC-1');

        $identifier = $this->resolve_identifier(['identifier' => " ACC-2\n"], 'hosted_fields', $stored);

        self::assertSame('ACC-2', $identifier);
    }

    public function test_stored_identifier_is_kept_when_the_field_was_not_rendered(): void
    {
        $stored = $this->stored_options('{}', false, 'ACC-1');

        $identifier = $this->resolve_identifier([], 'hosted_fields', $stored);

        self::assertSame('ACC-1', $identifier);
    }

    public function test_identifier_is_not_required_for_another_embedded_mode(): void
    {
        $stored = $this->stored_options(self::TEST_ONLY_OAUTH, false, '');

        self::assertSame('', $this->resolve_identifier(['identifier' => ''], 'redirect', $stored));
    }
}
