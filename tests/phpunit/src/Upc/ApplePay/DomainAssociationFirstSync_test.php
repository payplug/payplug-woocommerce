<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\ApplePay;

use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ArrayLogger;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\DomainAssociationFixtures;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScriptedHttpClient;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceDomainAssociationStorage;
use Payplug\PayplugWoocommerce\Upc\ApplePay\DomainAssociationFirstSync;
use PayplugUnifiedCore\Services\DomainAssociationService;
use PHPUnit\Framework\TestCase;

class DomainAssociationFirstSync_test extends TestCase
{
    /**
     * @var int[]
     */
    private $user_ids = [];

    protected function tearDown(): void
    {
        delete_option(WooCommerceDomainAssociationStorage::CONTENT_OPTION);
        delete_option(WooCommerceDomainAssociationStorage::STATUS_OPTION);
        remove_filter('wp_doing_ajax', '__return_true');
        wp_set_current_user(0);
        foreach ($this->user_ids as $user_id) {
            wp_delete_user($user_id);
        }
        $this->user_ids = [];
        parent::tearDown();
    }

    public function testDownloadsOnceWhenNeverAttempted(): void
    {
        $this->login_as('shop_manager');
        $http = new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]);

        $this->first_sync($http)->maybe_sync();

        $this->assertCount(1, $http->calls);
        $this->assertSame(DomainAssociationFixtures::valid(), (new WooCommerceDomainAssociationStorage())->readContent());
        $status = (new WooCommerceDomainAssociationStorage())->readStatus();
        $this->assertIsInt($status['lastAttemptAt']);
        $this->assertIsInt($status['lastSuccessAt']);
        $this->assertNull($status['lastError']);
    }

    public function testDoesNotDownloadAgainOnLaterAdminPages(): void
    {
        $this->login_as('administrator');
        $http = new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]);
        $sync = $this->first_sync($http);

        $sync->maybe_sync();
        $sync->maybe_sync();

        $this->assertCount(1, $http->calls);
    }

    public function testAFailedFirstAttemptIsNotRetried(): void
    {
        $this->login_as('administrator');
        $http = new ScriptedHttpClient([['status' => 500, 'body' => ''], ['status' => 200, 'body' => DomainAssociationFixtures::valid()]]);
        $sync = $this->first_sync($http);

        $sync->maybe_sync();
        $sync->maybe_sync();

        $this->assertCount(1, $http->calls);
        $status = (new WooCommerceDomainAssociationStorage())->readStatus();
        $this->assertIsInt($status['lastAttemptAt']);
        $this->assertNull($status['lastSuccessAt']);
        $this->assertStringContainsString('Unexpected HTTP status 500', (string) $status['lastError']);
        $this->assertNull((new WooCommerceDomainAssociationStorage())->readContent());
    }

    public function testAnUnreachableSourceIsNotRetried(): void
    {
        $this->login_as('administrator');
        $http = new ScriptedHttpClient([new \RuntimeException('timeout'), ['status' => 200, 'body' => DomainAssociationFixtures::valid()]]);

        $this->first_sync($http)->maybe_sync();
        $this->first_sync($http)->maybe_sync();

        $this->assertCount(1, $http->calls);
        $this->assertStringContainsString('Source unreachable', (string) (new WooCommerceDomainAssociationStorage())->readStatus()['lastError']);
    }

    public function testAPluginUpdateDoesNotDownloadAgain(): void
    {
        $this->login_as('administrator');
        $http = new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]);
        $this->first_sync($http)->maybe_sync();
        wp_cache_flush();

        $this->first_sync($http)->maybe_sync();

        $this->assertCount(1, $http->calls);
    }

    public function testSkippedForAShopperOrAnAnonymousVisitor(): void
    {
        $http = new ScriptedHttpClient();

        $this->first_sync($http)->maybe_sync();
        $this->login_as('subscriber');
        $this->first_sync($http)->maybe_sync();
        $this->login_as('customer');
        $this->first_sync($http)->maybe_sync();
        $this->login_as('editor');
        $this->first_sync($http)->maybe_sync();

        $this->assertSame([], $http->calls);
        $this->assertNull((new WooCommerceDomainAssociationStorage())->readStatus()['lastAttemptAt']);
    }

    public function testSkippedDuringAnAjaxRequest(): void
    {
        $this->login_as('administrator');
        add_filter('wp_doing_ajax', '__return_true');
        $http = new ScriptedHttpClient();

        $this->first_sync($http)->maybe_sync();

        $this->assertSame([], $http->calls);
    }

    public function testSkippedWithoutCountingAsTheAttemptWhenTheAccountHasNoApplePay(): void
    {
        $this->login_as('administrator');
        $http = new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]);

        $this->first_sync($http, false)->maybe_sync();

        $this->assertSame([], $http->calls);
        $this->assertNull((new WooCommerceDomainAssociationStorage())->readStatus()['lastAttemptAt']);
        $this->assertNull((new WooCommerceDomainAssociationStorage())->readContent());
    }

    public function testRunsOnALaterAdminPageOnceTheAccountHasApplePay(): void
    {
        $this->login_as('administrator');
        $http = new ScriptedHttpClient([['status' => 200, 'body' => DomainAssociationFixtures::valid()]]);
        $this->first_sync($http, false)->maybe_sync();

        $this->first_sync($http, true)->maybe_sync();

        $this->assertCount(1, $http->calls);
        $this->assertSame(DomainAssociationFixtures::valid(), (new WooCommerceDomainAssociationStorage())->readContent());
    }

    public function testRegistersOnAdminInit(): void
    {
        $sync = $this->first_sync(new ScriptedHttpClient());

        $sync->register();

        $this->assertSame(10, has_action('admin_init', [$sync, 'maybe_sync']));
        remove_action('admin_init', [$sync, 'maybe_sync']);
    }

    private function first_sync(ScriptedHttpClient $http, bool $account_has_apple_pay = true): DomainAssociationFirstSync
    {
        $storage = new WooCommerceDomainAssociationStorage();
        $service = new DomainAssociationService($http, $storage, new ArrayLogger());

        return new DomainAssociationFirstSync(static fn () => $service, $storage, static fn (): bool => $account_has_apple_pay);
    }

    private function login_as(string $role): void
    {
        $user_id = wp_insert_user([
            'user_login' => uniqid('payplug_' . $role . '_'),
            'user_pass' => wp_generate_password(),
            'role' => $role,
        ]);
        $this->assertIsInt($user_id);
        $this->user_ids[] = $user_id;
        wp_set_current_user($user_id);
    }
}
