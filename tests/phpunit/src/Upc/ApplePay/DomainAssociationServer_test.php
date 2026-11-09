<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\ApplePay;

use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ArrayLogger;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\DomainAssociationFixtures;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScriptedHttpClient;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationServer;
use PayplugUnifiedCore\Services\DomainAssociationService;
use PHPUnit\Framework\TestCase;

class DomainAssociationServer_test extends TestCase
{
    /**
     * @var ScriptedHttpClient
     */
    private $http;

    /**
     * @var string[]
     */
    private $temp_dirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new ScriptedHttpClient();
    }

    protected function tearDown(): void
    {
        delete_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION);
        delete_option(WooCommerceDomainAssociationStorage::STATUS_OPTION);
        foreach ($this->temp_dirs as $dir) {
            @unlink($dir . DomainAssociationServer::PATH);
            @rmdir($dir . '/.well-known');
            @rmdir($dir);
        }
        parent::tearDown();
    }

    public function testServesTheStoredFileByteForByte(): void
    {
        $this->store(DomainAssociationFixtures::valid());

        $response = $this->server()->respond(DomainAssociationServer::PATH, 'GET');

        $this->assertSame(200, $response['status']);
        $this->assertSame(DomainAssociationFixtures::valid(), $response['body']);
        $this->assertTrue($response['send_body']);
        $this->assertSame('text/plain', $response['headers']['Content-Type']);
        $this->assertSame('public, max-age=86400', $response['headers']['Cache-Control']);
        $this->assertSame('nosniff', $response['headers']['X-Content-Type-Options']);
    }

    public function testKeepsACrlfEnding(): void
    {
        $content = rtrim(DomainAssociationFixtures::valid(), "\n") . "\r\n";
        $this->store($content);

        $this->assertSame($content, $this->server()->respond(DomainAssociationServer::PATH, 'GET')['body']);
    }

    public function testAnswers404WithNoStoreWhenNothingIsStored(): void
    {
        $response = $this->server()->respond(DomainAssociationServer::PATH, 'GET');

        $this->assertSame(404, $response['status']);
        $this->assertSame('', $response['body']);
        $this->assertFalse($response['send_body']);
        $this->assertSame('text/plain', $response['headers']['Content-Type']);
        $this->assertSame('no-store', $response['headers']['Cache-Control']);
    }

    public function testAnswers404WhenTheStoredContentIsAnHtmlPage(): void
    {
        $this->store(DomainAssociationFixtures::htmlChallengePage());

        $this->assertSame(404, $this->server()->respond(DomainAssociationServer::PATH, 'GET')['status']);
    }

    public function testAnswers404WhenTheStoredFileBelongsToTheOtherEnvironment(): void
    {
        $this->store(DomainAssociationFixtures::validQa());

        $this->assertSame(404, $this->server()->respond(DomainAssociationServer::PATH, 'GET')['status']);
    }

    public function testAnswers404WhenTheStorageFails(): void
    {
        $logger = new ArrayLogger();
        $storage = new class () extends WooCommerceDomainAssociationStorage {
            public function readContent(): ?string
            {
                throw new \RuntimeException('database gone');
            }
        };
        $service = new DomainAssociationService($this->http, $storage, $logger);
        $server = new DomainAssociationServer(static fn () => $service, static fn (): bool => true, $this->empty_root());

        $this->assertSame(404, $server->respond(DomainAssociationServer::PATH, 'GET')['status']);
        $this->assertSame('apple_pay.well_known.resolve_failed', $logger->records[0][1]);
    }

    public function matchingUris(): array
    {
        return [
            'exact path' => ['/.well-known/apple-developer-merchantid-domain-association'],
            'trailing slash' => ['/.well-known/apple-developer-merchantid-domain-association/'],
            'query string' => ['/.well-known/apple-developer-merchantid-domain-association?x=1'],
            'empty query string' => ['/.well-known/apple-developer-merchantid-domain-association?'],
            'absolute form' => ['https://shop.example/.well-known/apple-developer-merchantid-domain-association'],
        ];
    }

    /**
     * @dataProvider matchingUris
     */
    public function testMatchesTheExactPathOnly(string $uri): void
    {
        $this->store(DomainAssociationFixtures::valid());

        $this->assertTrue(DomainAssociationServer::matches($uri));
        $this->assertSame(200, $this->server()->respond($uri, 'GET')['status']);
    }

    public function otherUris(): array
    {
        return [
            'well-known directory' => ['/.well-known/'],
            'other well-known file' => ['/.well-known/other'],
            'extension suffix' => ['/.well-known/apple-developer-merchantid-domain-association.txt'],
            'other case' => ['/.WELL-KNOWN/apple-developer-merchantid-domain-association'],
            'sub-directory prefix' => ['/shop/.well-known/apple-developer-merchantid-domain-association'],
            'double trailing slash' => ['/.well-known/apple-developer-merchantid-domain-association//x'],
            'root' => ['/'],
            'empty' => [''],
            'path in the query string only' => ['/?p=/.well-known/apple-developer-merchantid-domain-association'],
        ];
    }

    /**
     * @dataProvider otherUris
     */
    public function testLeavesEveryOtherUriToWordPress(string $uri): void
    {
        $this->store(DomainAssociationFixtures::valid());

        $this->assertFalse(DomainAssociationServer::matches($uri));
        $this->assertNull($this->server()->respond($uri, 'GET'));
    }

    public function testOtherMethodsAreLeftToWordPress(): void
    {
        $this->store(DomainAssociationFixtures::valid());

        $this->assertNull($this->server()->respond(DomainAssociationServer::PATH, 'POST'));
        $this->assertNull($this->server()->respond(DomainAssociationServer::PATH, 'PUT'));
    }

    public function testHeadAnswersWithoutABody(): void
    {
        $this->store(DomainAssociationFixtures::valid());

        $response = $this->server()->respond(DomainAssociationServer::PATH, 'HEAD');

        $this->assertSame(200, $response['status']);
        $this->assertFalse($response['send_body']);
    }

    public function testAManualFileAtTheDocumentRootIsNeitherServedNorOverwritten(): void
    {
        $this->store(DomainAssociationFixtures::valid());
        $root = $this->root_with_manual_file('MANUAL');

        $response = (new DomainAssociationServer($this->factory(), static fn (): bool => true, $root))->respond(DomainAssociationServer::PATH, 'GET');

        $this->assertNull($response);
        $this->assertSame('MANUAL', file_get_contents($root . DomainAssociationServer::PATH));
        $this->assertSame(DomainAssociationFixtures::valid(), get_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION));
    }

    public function testServingNeverCallsTheNetwork(): void
    {
        $this->server()->respond(DomainAssociationServer::PATH, 'GET');
        $this->store(DomainAssociationFixtures::valid());
        $this->server()->respond(DomainAssociationServer::PATH, 'GET');

        $this->assertSame([], $this->http->calls);
    }

    public function testTheServiceIsNotBuiltForAnotherUri(): void
    {
        $built = false;
        $server = new DomainAssociationServer(static function () use (&$built): void {
            $built = true;
        }, static fn (): bool => true, $this->empty_root());

        $server->respond('/shop/', 'GET');

        $this->assertFalse($built);
    }

    public function testRootFilePresent(): void
    {
        $this->assertTrue(DomainAssociationServer::root_file_present($this->root_with_manual_file('MANUAL')));
        $this->assertTrue(DomainAssociationServer::root_file_present($this->root_with_manual_file('MANUAL') . '/'));
        $this->assertFalse(DomainAssociationServer::root_file_present($this->empty_root()));
        $this->assertFalse(DomainAssociationServer::root_file_present(''));
    }

    public function testLeavesTheRouteToWordPressWhenTheAccountHasNoApplePay(): void
    {
        $this->store(DomainAssociationFixtures::valid());

        $this->assertNull($this->server(false)->respond(DomainAssociationServer::PATH, 'GET'));
        $this->assertNull($this->server(false)->respond(DomainAssociationServer::PATH, 'HEAD'));
    }

    public function testDoesNotAnswer404WhenTheAccountHasNoApplePayAndNothingIsStored(): void
    {
        $this->assertNull($this->server(false)->respond(DomainAssociationServer::PATH, 'GET'));
    }

    public function testTheServiceIsNotBuiltWhenTheAccountHasNoApplePay(): void
    {
        $built = false;
        $server = new DomainAssociationServer(static function () use (&$built): void {
            $built = true;
        }, static fn (): bool => false, $this->empty_root());

        $server->respond(DomainAssociationServer::PATH, 'GET');

        $this->assertFalse($built);
    }

    public function testThePermissionIsNotReadForAnotherUri(): void
    {
        $read = false;
        $server = new DomainAssociationServer($this->factory(), static function () use (&$read): bool {
            $read = true;

            return true;
        }, $this->empty_root());

        $server->respond('/shop/', 'GET');

        $this->assertFalse($read);
    }

    private function store(string $content): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, $content, false);
    }

    private function factory(): callable
    {
        $service = new DomainAssociationService($this->http, new WooCommerceDomainAssociationStorage(), new ArrayLogger());

        return static fn () => $service;
    }

    private function server(bool $account_has_apple_pay = true): DomainAssociationServer
    {
        return new DomainAssociationServer($this->factory(), static fn (): bool => $account_has_apple_pay, $this->empty_root());
    }

    private function empty_root(): string
    {
        $dir = sys_get_temp_dir() . '/' . uniqid('payplug_docroot_');
        mkdir($dir);
        $this->temp_dirs[] = $dir;

        return $dir;
    }

    private function root_with_manual_file(string $content): string
    {
        $dir = $this->empty_root();
        mkdir($dir . '/.well-known');
        file_put_contents($dir . DomainAssociationServer::PATH, $content);

        return $dir;
    }
}
