<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\ApplePay;

use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\DomainAssociationFixtures;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use PHPUnit\Framework\TestCase;

class DomainAssociationUninstall_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION);
        delete_option(WooCommerceDomainAssociationStorage::STATUS_OPTION);
        parent::tearDown();
    }

    public function testUninstallDeletesBothOptionsEvenWithoutPayplugSettings(): void
    {
        delete_option('woocommerce_payplug_settings');
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::valid(), false);
        update_option(WooCommerceDomainAssociationStorage::STATUS_OPTION, ['lastAttemptAt' => 1, 'lastSuccessAt' => 1, 'lastError' => null], false);

        if (!defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', true);
        }
        require PAYPLUG_GATEWAY_PLUGIN_DIR . 'uninstall.php';

        $this->assertSame('gone', get_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, 'gone'));
        $this->assertSame('gone', get_option(WooCommerceDomainAssociationStorage::STATUS_OPTION, 'gone'));
    }
}
