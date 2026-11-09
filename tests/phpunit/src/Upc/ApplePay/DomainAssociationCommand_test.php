<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\ApplePay;

use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ArrayLogger;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\DomainAssociationFixtures;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScriptedHttpClient;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationCommand;
use PayplugUnifiedCore\Services\DomainAssociationService;
use PHPUnit\Framework\TestCase;

class DomainAssociationCommand_test extends TestCase
{
    /**
     * @var string[]
     */
    private $errors = [];

    /**
     * @var string[]
     */
    private $successes = [];

    protected function tearDown(): void
    {
        delete_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION);
        delete_option(WooCommerceDomainAssociationStorage::STATUS_OPTION);
        parent::tearDown();
    }

    public function testResyncReportsAnUpdatedFile(): void
    {
        $this->command(new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]))->resync();

        $this->assertSame(['Apple Pay domain-association file synchronised.'], $this->successes);
        $this->assertSame([], $this->errors);
    }

    public function testResyncReportsAnUnchangedFile(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::valid());

        $this->command(new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]))->resync();

        $this->assertSame(['Apple Pay domain-association file already up to date.'], $this->successes);
        $this->assertSame([], $this->errors);
    }

    public function testResyncReportsTheUpcErrorOnFailure(): void
    {
        $this->command(new ScriptedHttpClient([['status' => 500, 'body' => '']]))->resync();

        $this->assertSame([], $this->successes);
        $this->assertCount(1, $this->errors);
        $this->assertStringContainsString('Unexpected HTTP status 500', $this->errors[0]);
    }

    public function testResyncDownloadsEvenWhenAFileIsAlreadyStored(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::valid());
        $http = new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid(1800000000)]]);

        $this->command($http)->resync();

        $this->assertCount(1, $http->calls);
        $this->assertSame(DomainAssociationFixtures::valid(1800000000), get_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION));
    }

    private function command(ScriptedHttpClient $http): DomainAssociationCommand
    {
        $service = new DomainAssociationService($http, new WooCommerceDomainAssociationStorage(), new ArrayLogger());

        return new DomainAssociationCommand(
            static fn () => $service,
            function (string $message): void {
                $this->errors[] = $message;
            },
            function (string $message): void {
                $this->successes[] = $message;
            }
        );
    }
}
