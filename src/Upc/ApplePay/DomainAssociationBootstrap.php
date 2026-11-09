<?php

namespace Payplug\PayplugWoocommerce\Upc\ApplePay;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceUnifiedApiHttpClient;
use PayplugUnifiedCore\DataValues\DomainAssociationEnvironment;
use PayplugUnifiedCore\Services\DomainAssociationService;

/**
 * Builds the Apple Pay domain-association objects on first use, so a request that is not for
 * the Apple Pay file pays nothing for them.
 */
final class DomainAssociationBootstrap
{
    /**
     * @var WooCommerceDomainAssociationStorage|null
     */
    private static $storage;

    /**
     * @var DomainAssociationService|null
     */
    private static $service;

    /**
     * Runs from the first plugins_loaded callback of payplug.php. The Apple Pay request is answered
     * in place: a plugins_loaded callback added from there with a lower priority would never run.
     */
    public static function boot(): void
    {
        (new DomainAssociationServer(self::service_factory(), [self::class, 'account_allows_apple_pay']))->maybe_serve();
        (new DomainAssociationFirstSync(self::service_factory(), self::storage(), [self::class, 'account_allows_apple_pay']))->register();

        if (defined('WP_CLI') && WP_CLI) {
            $cli = '\WP_CLI';
            $cli::add_command('payplug', '\WP_CLI\Dispatcher\CommandNamespace');
            $cli::add_command('payplug apple-pay', new DomainAssociationCommand(
                self::service_factory(),
                static function (string $message) use ($cli): void {
                    $cli::error($message);
                },
                static function (string $message) use ($cli): void {
                    $cli::success($message);
                }
            ));
        }
    }

    /**
     * Reads the account permissions cached in the plugin settings, without any network call.
     */
    public static function account_allows_apple_pay(): bool
    {
        $settings = get_option('woocommerce_payplug_settings', []);
        $enabled = is_array($settings) ? ($settings['payment_methods']['permissions']['apple_pay']['enabled'] ?? null) : null;

        return in_array($enabled, [true, 1, '1'], true);
    }

    public static function environment_for(string $secure_domain): string
    {
        return false !== strpos($secure_domain, 'secure-qa.payplug.com')
            ? DomainAssociationEnvironment::QA
            : DomainAssociationEnvironment::PRODUCTION;
    }

    public static function storage(): WooCommerceDomainAssociationStorage
    {
        if (null === self::$storage) {
            self::$storage = new WooCommerceDomainAssociationStorage();
        }

        return self::$storage;
    }

    public static function service(): DomainAssociationService
    {
        if (null === self::$service) {
            PayplugWoocommerceHelper::load_config();
            self::$service = new DomainAssociationService(
                new WooCommerceUnifiedApiHttpClient(),
                self::storage(),
                new WooCommerceLogger(),
                self::environment_for(defined('SECURE_DOMAIN') ? (string) SECURE_DOMAIN : '')
            );
        }

        return self::$service;
    }

    public static function service_factory(): callable
    {
        return [self::class, 'service'];
    }

    public static function diagnostics(): DomainAssociationDiagnostics
    {
        return new DomainAssociationDiagnostics(self::service_factory(), self::storage());
    }

    public static function resync(): DomainAssociationResync
    {
        return new DomainAssociationResync(self::service_factory(), self::diagnostics());
    }

    /**
     * Drops the instances built so far.
     */
    public static function reset(): void
    {
        self::$storage = null;
        self::$service = null;
    }
}
