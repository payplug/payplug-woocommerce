<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\ApplePay;

use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ArrayLogger;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\DomainAssociationFixtures;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScriptedHttpClient;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationDiagnostics;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationResync;
use PayplugUnifiedCore\Services\DomainAssociationService;
use PHPUnit\Framework\TestCase;

class DomainAssociationResync_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION);
        delete_option(WooCommerceDomainAssociationStorage::STATUS_OPTION);
        parent::tearDown();
    }

    public function testUpdatedResponse(): void
    {
        $result = $this->resync(new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]))->handle();

        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('synced', $result['diagnostics']['state']);
        $this->assertIsInt($result['diagnostics']['last_success_at']);
        $this->assertNull($result['diagnostics']['last_error']);
        $this->assertSame(['success', 'status', 'diagnostics'], array_keys($result));
    }

    public function testUnchangedResponse(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::valid());

        $result = $this->resync(new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]))->handle();

        $this->assertTrue($result['success']);
        $this->assertSame('unchanged', $result['status']);
    }

    public function testANewerFileReplacesTheStoredOne(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::valid());

        $result = $this->resync(new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid(1800000000)]]))->handle();

        $this->assertSame('updated', $result['status']);
        $this->assertSame(DomainAssociationFixtures::valid(1800000000), get_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION));
    }

    public function testAFailureKeepsTheStoredFileAndEscapesTheError(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::valid());

        $result = $this->resync(new ScriptedHttpClient([new \RuntimeException('<script>x</script>')]))->handle();

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('synced', $result['diagnostics']['state']);
        $this->assertStringContainsString('Source unreachable', $result['diagnostics']['last_error']);
        $this->assertStringContainsString('&lt;script&gt;', $result['diagnostics']['last_error']);
        $this->assertStringNotContainsString('<script>', $result['diagnostics']['last_error']);
        $this->assertSame(DomainAssociationFixtures::valid(), get_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION));
    }

    public function testAnHtmlPageIsRefusedWhenNothingIsStored(): void
    {
        $result = $this->resync(new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::htmlChallengePage()]]))->handle();

        $this->assertFalse($result['success']);
        $this->assertSame('not_synced', $result['diagnostics']['state']);
        $this->assertStringContainsString('Invalid domain-association content', $result['diagnostics']['last_error']);
        $this->assertFalse(get_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION));
    }

    public function testAMalformedResponseIsReported(): void
    {
        $result = $this->resync(new ScriptedHttpClient([['status' => 200]]))->handle();

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('malformed', $result['diagnostics']['last_error']);
    }

    private function resync(ScriptedHttpClient $http): DomainAssociationResync
    {
        $storage = new WooCommerceDomainAssociationStorage();
        $service = new DomainAssociationService($http, $storage, new ArrayLogger());
        $factory = static fn () => $service;

        return new DomainAssociationResync($factory, new DomainAssociationDiagnostics($factory, $storage, sys_get_temp_dir() . '/payplug_no_docroot'));
    }
}
