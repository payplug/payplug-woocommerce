<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Payplug\PayplugWoocommerce\Admin\Ajax;
use PHPUnit\Framework\TestCase;

class Ajax_test extends TestCase
{
    use MockeryPHPUnitIntegration;

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
        self::assertSame($settings, get_option('woocommerce_payplug_settings'));
    }

    public function empty_bodies(): array
    {
        return [
            'empty json object' => [[]],
            'malformed json' => [null],
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
