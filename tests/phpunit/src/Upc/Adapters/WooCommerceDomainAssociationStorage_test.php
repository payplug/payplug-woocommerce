<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use PHPUnit\Framework\TestCase;

class WooCommerceDomainAssociationStorage_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION);
        delete_option(WooCommerceDomainAssociationStorage::STATUS_OPTION);
        parent::tearDown();
    }

    public function exactContents(): array
    {
        return [
            'trailing LF' => ["AB12\n"],
            'trailing CRLF' => ["AB12\r\n"],
            'quotes and backslashes' => ["a\\b'c\"d\n"],
            'leading and trailing spaces' => [" AB12 \n"],
            'html entities' => ["<b>&amp;</b>\n"],
        ];
    }

    /**
     * @dataProvider exactContents
     */
    public function testWriteThenReadReturnsTheExactBytes(string $content): void
    {
        $storage = new WooCommerceDomainAssociationStorage();

        $storage->writeContent($content);
        wp_cache_flush();

        $this->assertSame($content, (new WooCommerceDomainAssociationStorage())->readContent());
    }

    public function testReadContentIsNullWhenNothingIsStored(): void
    {
        $this->assertNull((new WooCommerceDomainAssociationStorage())->readContent());
    }

    public function testReadContentIsNullWhenTheStoredValueIsEmpty(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, '');

        $this->assertNull((new WooCommerceDomainAssociationStorage())->readContent());
    }

    public function testReadContentIsNullWhenTheStoredValueIsNotAString(): void
    {
        update_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION, ['x']);

        $this->assertNull((new WooCommerceDomainAssociationStorage())->readContent());
    }

    public function testContentOptionIsNotAutoloaded(): void
    {
        (new WooCommerceDomainAssociationStorage())->writeContent("AB12\n");

        $this->assertContains($this->autoload(WooCommerceDomainAssociationStorage::CONTENT_OPTION), ['no', 'off', 'auto-off']);
    }

    public function testReadStatusDefaultsToThreeNullKeys(): void
    {
        $this->assertSame(
            ['lastAttemptAt' => null, 'lastSuccessAt' => null, 'lastError' => null],
            (new WooCommerceDomainAssociationStorage())->readStatus()
        );
    }

    public function testReadStatusCastsDigitStringTimestamps(): void
    {
        update_option(WooCommerceDomainAssociationStorage::STATUS_OPTION, [
            'lastAttemptAt' => '1700000000',
            'lastSuccessAt' => 1700000001,
            'lastError' => 'boom',
        ]);

        $this->assertSame(
            ['lastAttemptAt' => 1700000000, 'lastSuccessAt' => 1700000001, 'lastError' => 'boom'],
            (new WooCommerceDomainAssociationStorage())->readStatus()
        );
    }

    public function testReadStatusIgnoresAValueThatIsNotAnArray(): void
    {
        update_option(WooCommerceDomainAssociationStorage::STATUS_OPTION, 'oops');

        $this->assertSame(
            ['lastAttemptAt' => null, 'lastSuccessAt' => null, 'lastError' => null],
            (new WooCommerceDomainAssociationStorage())->readStatus()
        );
    }

    public function testReadStatusDropsMalformedValues(): void
    {
        update_option(WooCommerceDomainAssociationStorage::STATUS_OPTION, [
            'lastAttemptAt' => '17e8',
            'lastSuccessAt' => -1.5,
            'lastError' => '',
        ]);

        $this->assertSame(
            ['lastAttemptAt' => null, 'lastSuccessAt' => null, 'lastError' => null],
            (new WooCommerceDomainAssociationStorage())->readStatus()
        );
    }

    public function testWriteStatusRoundTrip(): void
    {
        $status = ['lastAttemptAt' => 1793207042, 'lastSuccessAt' => null, 'lastError' => 'Unexpected HTTP status 500 from secure.payplug.com.'];

        (new WooCommerceDomainAssociationStorage())->writeStatus($status);
        wp_cache_flush();

        $this->assertSame($status, (new WooCommerceDomainAssociationStorage())->readStatus());
    }

    public function testStatusOptionIsNotAutoloaded(): void
    {
        (new WooCommerceDomainAssociationStorage())->writeStatus(['lastAttemptAt' => 1, 'lastSuccessAt' => 1, 'lastError' => null]);

        $this->assertContains($this->autoload(WooCommerceDomainAssociationStorage::STATUS_OPTION), ['no', 'off', 'auto-off']);
    }

    private function autoload(string $option): ?string
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option));
    }
}
