<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\ApplePay;

use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\DomainAssociationFixtures;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationBootstrap;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationFirstSync;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationServer;
use PayplugUnifiedCore\DataValues\DomainAssociationEnvironment;
use PayplugUnifiedCore\Services\DomainAssociationService;
use PHPUnit\Framework\TestCase;

class DomainAssociationBootstrap_test extends TestCase
{
    private const SETTINGS = 'woocommerce_payplug_settings';

    /**
     * @var mixed
     */
    private $settings;

    /**
     * @var array<string, mixed>
     */
    private $server_globals;

    protected function setUp(): void
    {
        parent::setUp();
        DomainAssociationBootstrap::reset();
        $this->settings = get_option(self::SETTINGS, null);
        $this->server_globals = array_intersect_key($_SERVER, ['REQUEST_URI' => true, 'REQUEST_METHOD' => true]);
    }

    protected function tearDown(): void
    {
        delete_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION);
        delete_option(WooCommerceDomainAssociationStorage::STATUS_OPTION);
        if (null === $this->settings) {
            delete_option(self::SETTINGS);
        } else {
            update_option(self::SETTINGS, $this->settings);
        }
        unset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        $_SERVER = $this->server_globals + $_SERVER;
        DomainAssociationBootstrap::reset();
        parent::tearDown();
    }

    public function environments(): array
    {
        return [
            'production domain' => ['https://secure.payplug.com', DomainAssociationEnvironment::PRODUCTION],
            'empty build-time value' => ['', DomainAssociationEnvironment::PRODUCTION],
            'qa domain' => ['https://secure-qa.payplug.com', DomainAssociationEnvironment::QA],
            'qa domain with a trailing slash' => ['https://secure-qa.payplug.com/', DomainAssociationEnvironment::QA],
        ];
    }

    /**
     * @dataProvider environments
     */
    public function testEnvironmentFollowsTheSecureDomain(string $secure_domain, string $expected): void
    {
        $this->assertSame($expected, DomainAssociationBootstrap::environment_for($secure_domain));
    }

    public function testServiceIsBuiltOnce(): void
    {
        $service = DomainAssociationBootstrap::service();

        $this->assertInstanceOf(DomainAssociationService::class, $service);
        $this->assertSame($service, DomainAssociationBootstrap::service());
        $this->assertSame($service, call_user_func(DomainAssociationBootstrap::service_factory()));
    }

    public function testStorageIsTheOptionBackedAdapter(): void
    {
        $this->assertInstanceOf(WooCommerceDomainAssociationStorage::class, DomainAssociationBootstrap::storage());
        $this->assertSame(DomainAssociationBootstrap::storage(), DomainAssociationBootstrap::storage());
    }

    public function testServiceResolvesTheStoredFile(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::valid());

        $this->assertSame(DomainAssociationFixtures::valid(), DomainAssociationBootstrap::service()->resolve());
    }

    public function testThePluginBootsFromItsFirstPluginsLoadedCallback(): void
    {
        $closures = array_values(array_filter(
            $GLOBALS['wp_filter']['plugins_loaded']->callbacks[PHP_INT_MIN] ?? [],
            static fn (array $callback): bool => $callback['function'] instanceof \Closure
                && realpath(PAYPLUG_GATEWAY_PLUGIN_DIR . 'payplug.php') === realpath((string) (new \ReflectionFunction($callback['function']))->getFileName())
        ));
        $this->assertCount(1, $closures);

        $closure = new \ReflectionFunction($closures[0]['function']);
        $source = implode('', array_slice(file((string) $closure->getFileName()), $closure->getStartLine() - 1, $closure->getEndLine() - $closure->getStartLine() + 1));
        $guard = strpos($source, "if (file_exists(plugin_dir_path(__FILE__) . '/vendor/autoload.php'))");
        $this->assertNotFalse($guard);
        $this->assertGreaterThan($guard, strpos($source, "require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';"));
        $this->assertGreaterThan($guard, strpos($source, 'DomainAssociationBootstrap::boot();'));
    }

    public function testThePluginFileLoadsNeitherTheAutoloaderNorAPluginClassWhenIncluded(): void
    {
        $include_time = [];
        $braces = [];
        $function_seen = false;
        foreach (token_get_all((string) file_get_contents(PAYPLUG_GATEWAY_PLUGIN_DIR . 'payplug.php')) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) {
                continue;
            }
            if ('}' === $text) {
                array_pop($braces);
            }
            if (!in_array(true, $braces, true)) {
                $include_time[] = $token;
            }
            if (is_array($token) && T_FUNCTION === $token[0]) {
                $function_seen = true;
            }
            if ('{' === $text || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $braces[] = $function_seen;
                $function_seen = false;
            }
        }

        $this->assertNotEmpty($include_time);
        foreach ($include_time as $i => $token) {
            $id = is_array($token) ? $token[0] : null;
            $this->assertNotContains($id, [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE, T_DOUBLE_COLON], is_array($token) ? $token[1] . ' on line ' . $token[2] : '');
            if (T_NEW === $id) {
                $class = $include_time[$i + 1];
                $this->assertStringStartsWith('\\', is_array($class) ? $class[1] : $class, 'new on line ' . $token[2]);
            }
        }
    }

    public function testInitStillRequiresTheAutoloader(): void
    {
        $init = new \ReflectionFunction('Payplug\\PayplugWoocommerce\\init');
        $source = implode('', array_slice(file((string) $init->getFileName()), $init->getStartLine() - 1, $init->getEndLine() - $init->getStartLine() + 1));

        $this->assertStringContainsString("require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';", $source);
        $this->assertStringNotContainsString('DomainAssociationBootstrap', $source);
    }

    public function testBootAnswersTheRequestInPlaceInsteadOfHookingPluginsLoaded(): void
    {
        $read = 0;
        $count_reads = static function () use (&$read) {
            ++$read;

            return ['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => false]]]];
        };
        add_filter('pre_option_' . self::SETTINGS, $count_reads);
        $_SERVER['REQUEST_URI'] = DomainAssociationServer::PATH;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $syncs = count($this->callbacks('admin_init', 10, DomainAssociationFirstSync::class, 'maybe_sync'));

        DomainAssociationBootstrap::boot();
        remove_filter('pre_option_' . self::SETTINGS, $count_reads);
        $added = array_slice($this->callbacks('admin_init', 10, DomainAssociationFirstSync::class, 'maybe_sync'), $syncs);
        foreach ($added as $callback) {
            remove_action('admin_init', $callback['function']);
        }

        $this->assertSame(1, $read);
        $this->assertCount(1, $added);
        foreach ($GLOBALS['wp_filter']['plugins_loaded']->callbacks as $callbacks) {
            foreach ($callbacks as $callback) {
                $this->assertFalse(is_array($callback['function']) && $callback['function'][0] instanceof DomainAssociationServer);
            }
        }
    }

    public function permissions(): array
    {
        return [
            'no settings' => [null, false],
            'settings not an array' => ['corrupted', false],
            'no payment_methods' => [['email' => 'merchant@example.com'], false],
            'payment_methods not an array' => [['payment_methods' => 'corrupted'], false],
            'no permissions' => [['payment_methods' => ['configuration' => []]], false],
            'permissions not an array' => [['payment_methods' => ['permissions' => 'corrupted']], false],
            'no apple_pay permission' => [['payment_methods' => ['permissions' => ['payplug' => ['enabled' => true]]]], false],
            'apple_pay permission not an array' => [['payment_methods' => ['permissions' => ['apple_pay' => true]]], false],
            'enabled missing' => [['payment_methods' => ['permissions' => ['apple_pay' => ['allowed_domains' => '[]']]]], false],
            'enabled false' => [['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => false]]]], false],
            'enabled null' => [['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => null]]]], false],
            'enabled "0"' => [['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => '0']]]], false],
            'enabled ""' => [['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => '']]]], false],
            'enabled true' => [['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => true]]]], true],
            'enabled "1"' => [['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => '1']]]], true],
            'enabled 1' => [['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => 1]]]], true],
        ];
    }

    /**
     * @dataProvider permissions
     *
     * @param mixed $settings
     */
    public function testAccountAllowsApplePayReadsTheCachedAccountPermission($settings, bool $expected): void
    {
        if (null === $settings) {
            delete_option(self::SETTINGS);
        } else {
            update_option(self::SETTINGS, $settings);
        }

        $this->assertSame($expected, DomainAssociationBootstrap::account_allows_apple_pay());
    }

    public function testAccountAllowsApplePayNeverCallsTheNetwork(): void
    {
        $requests = 0;
        $count_requests = static function ($preempt) use (&$requests) {
            ++$requests;

            return $preempt;
        };
        add_filter('pre_http_request', $count_requests);
        update_option(self::SETTINGS, ['payment_methods' => ['permissions' => ['apple_pay' => ['enabled' => true]]]]);

        DomainAssociationBootstrap::account_allows_apple_pay();
        remove_filter('pre_http_request', $count_requests);

        $this->assertSame(0, $requests);
    }

    public function testThePluginRegistersTheFirstSyncOnAdminInit(): void
    {
        $this->assertCount(1, $this->callbacks('admin_init', 10, DomainAssociationFirstSync::class, 'maybe_sync'));
    }

    public function testNoScheduledTaskIsRegistered(): void
    {
        foreach ((array) _get_cron_array() as $hooks) {
            foreach (array_keys((array) $hooks) as $hook) {
                $this->assertStringNotContainsStringIgnoringCase('apple', (string) $hook);
            }
        }
        foreach (glob(PAYPLUG_GATEWAY_PLUGIN_DIR . 'src/Upc/ApplePay/*.php') as $file) {
            $this->assertStringNotContainsString('wp_schedule', (string) file_get_contents($file), $file);
        }
    }

    private function callbacks(string $hook, int $priority, string $class, string $method): array
    {
        $callbacks = $GLOBALS['wp_filter'][$hook]->callbacks[$priority] ?? [];

        return array_values(array_filter($callbacks, static fn (array $callback): bool => is_array($callback['function'])
            && $callback['function'][0] instanceof $class
            && $method === $callback['function'][1]));
    }
}
