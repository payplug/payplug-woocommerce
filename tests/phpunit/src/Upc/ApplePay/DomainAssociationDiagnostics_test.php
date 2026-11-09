<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\ApplePay;

use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ArrayLogger;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\DomainAssociationFixtures;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScriptedHttpClient;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationDiagnostics;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationServer;
use PayplugUnifiedCore\Services\DomainAssociationService;
use PHPUnit\Framework\TestCase;

class DomainAssociationDiagnostics_test extends TestCase
{
    private const TEXT_KEYS = ['title', 'state_synced', 'state_not_synced', 'last_success', 'last_error', 'never', 'resync', 'resync_error', 'root_file'];

    /**
     * @var string[]
     */
    private $temp_dirs = [];

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

    public function testNeverSynchronisedPayload(): void
    {
        $payload = $this->diagnostics()->payload();

        $this->assertSame('not_synced', $payload['state']);
        $this->assertNull($payload['last_attempt_at']);
        $this->assertNull($payload['last_success_at']);
        $this->assertNull($payload['last_error']);
        $this->assertFalse($payload['root_file_present']);
        $this->assertSame(self::TEXT_KEYS, array_keys($payload['texts']));
        foreach ($payload['texts'] as $key => $text) {
            $this->assertIsString($text, $key);
            $this->assertNotSame('', $text, $key);
        }
    }

    public function testEveryTextIsTranslatedInEachShippedLanguage(): void
    {
        $msgids = [];
        $capture = static function ($translation, $text) use (&$msgids) {
            $msgids[] = $text;

            return $translation;
        };
        add_filter('gettext_payplug', $capture, 1, 2);
        $this->diagnostics()->payload();
        remove_filter('gettext_payplug', $capture, 1);

        $this->assertCount(count(self::TEXT_KEYS), $msgids);
        foreach (['en_US', 'fr_FR', 'es_ES', 'it_IT'] as $locale) {
            $mo = new \MO();
            $this->assertTrue($mo->import_from_file(PAYPLUG_GATEWAY_PLUGIN_DIR . 'languages/payplug-' . $locale . '.mo'), $locale);
            foreach ($msgids as $msgid) {
                $this->assertArrayHasKey($msgid, $mo->entries, $locale);
                $this->assertNotSame('', $mo->entries[$msgid]->translations[0] ?? '', $locale . ' ' . $msgid);
            }
        }
    }

    public function testSynchronisedPayload(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::valid());
        update_option(WooCommerceDomainAssociationStorage::STATUS_OPTION, ['lastAttemptAt' => 1793207042, 'lastSuccessAt' => 1793207042, 'lastError' => null]);

        $diagnostics = $this->diagnostics()->diagnostics();

        $this->assertSame([
            'state' => 'synced',
            'last_attempt_at' => 1793207042,
            'last_success_at' => 1793207042,
            'last_error' => null,
            'root_file_present' => false,
        ], $diagnostics);
    }

    public function testStateIsNotSynchronisedWhenTheStoredContentIsInvalid(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, DomainAssociationFixtures::htmlChallengePage());

        $this->assertSame('not_synced', $this->diagnostics()->diagnostics()['state']);
    }

    public function testLastErrorIsHtmlEscaped(): void
    {
        $error = 'Source unreachable: <b>x</b> & "y"';
        update_option(WooCommerceDomainAssociationStorage::STATUS_OPTION, ['lastAttemptAt' => 1793207042, 'lastSuccessAt' => null, 'lastError' => $error]);

        $last_error = $this->diagnostics()->diagnostics()['last_error'];

        $this->assertSame(esc_html($error), $last_error);
        $this->assertStringNotContainsString('<b>', $last_error);
    }

    public function testRootFilePresent(): void
    {
        $root = sys_get_temp_dir() . '/' . uniqid('payplug_docroot_');
        mkdir($root . '/.well-known', 0777, true);
        file_put_contents($root . DomainAssociationServer::PATH, 'MANUAL');
        $this->temp_dirs[] = $root;

        $this->assertTrue($this->diagnostics($root)->diagnostics()['root_file_present']);
    }

    public function testDiagnosticsHasOnlyTheFieldsTheResyncUpdates(): void
    {
        $this->assertSame(
            ['state', 'last_attempt_at', 'last_success_at', 'last_error', 'root_file_present'],
            array_keys($this->diagnostics()->diagnostics())
        );
    }

    public function testReadingTheDiagnosticsNeverCallsTheNetwork(): void
    {
        $http = new ScriptedHttpClient();

        $this->diagnostics(null, $http)->payload();

        $this->assertSame([], $http->calls);
    }

    private function diagnostics(?string $document_root = null, ?ScriptedHttpClient $http = null): DomainAssociationDiagnostics
    {
        $storage = new WooCommerceDomainAssociationStorage();
        $service = new DomainAssociationService($http ?? new ScriptedHttpClient(), $storage, new ArrayLogger());

        return new DomainAssociationDiagnostics(static fn () => $service, $storage, $document_root ?? $this->empty_root());
    }

    private function empty_root(): string
    {
        $dir = sys_get_temp_dir() . '/' . uniqid('payplug_docroot_');
        mkdir($dir);
        $this->temp_dirs[] = $dir;

        return $dir;
    }
}
