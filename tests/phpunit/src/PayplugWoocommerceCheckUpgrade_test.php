<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src;

use Payplug\PayplugWoocommerce\PayplugWoocommerce;
use Payplug\PayplugWoocommerce\Service\ApmMigration;
use PHPUnit\Framework\TestCase;

class PayplugWoocommerceCheckUpgrade_test extends TestCase
{
    /**
     * @var mixed
     */
    private $original_settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original_settings = get_option('woocommerce_payplug_settings', null);
        delete_transient(ApmMigration::RETRY_TRANSIENT);
        // A migration to run: logged-in merchant, cache written without its mode.
        update_option('woocommerce_payplug_settings', [
            'email' => 'merchant@example.test',
            'version' => PAYPLUG_GATEWAY_VERSION,
            'apm_cache_mode' => '',
            'apm_restrictions_version' => '',
        ]);
    }

    protected function tearDown(): void
    {
        remove_filter('wp_doing_ajax', '__return_true');
        set_current_screen('front');
        delete_transient(ApmMigration::RETRY_TRANSIENT);
        if (null === $this->original_settings) {
            delete_option('woocommerce_payplug_settings');
        } else {
            update_option('woocommerce_payplug_settings', $this->original_settings);
        }
        parent::tearDown();
    }

    public function test_update_step_does_not_run_on_an_admin_ajax_request(): void
    {
        set_current_screen('dashboard');
        add_filter('wp_doing_ajax', '__return_true');

        $this->check_upgrade();

        self::assertFalse(get_transient(ApmMigration::RETRY_TRANSIENT));
    }

    private function check_upgrade(): void
    {
        $plugin = (new \ReflectionClass(PayplugWoocommerce::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($plugin, 'check_upgrade');
        $method->setAccessible(true);
        $method->invoke($plugin);
    }
}
