<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Payplug\PayplugWoocommerce\Admin\Ajax;
use Payplug\PayplugWoocommerce\Service\Configuration;
use PHPUnit\Framework\TestCase;

class Ajax_test extends TestCase
{
    private const LIVE_ONLY_OAUTH = '{"test":[],"live":{"client_id":"id","client_secret":"secret"}}';
    private const TEST_ONLY_OAUTH = '{"test":{"client_id":"id","client_secret":"secret"},"live":[]}';

    /**
     * wp_send_json_error() only goes through wp_die() during an AJAX request (a plain die
     * otherwise), so every test fakes one and turns the die into a WPDieException.
     */
    protected function setUp(): void
    {
        parent::setUp();
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', static fn () => static function (): void {
            throw new \WPDieException();
        });
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        remove_all_filters('wp_doing_ajax');
        remove_all_filters('wp_die_ajax_handler');
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    private function stored_options(string $oauth_client_data, bool $live, string $identifier): array
    {
        $options = (new Configuration())->extract_options_from_fields();
        $options['mode'] = $live;
        $options['oauth_client_data'] = $oauth_client_data;
        $options['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $options['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = $identifier;
        update_option('woocommerce_payplug_settings', $options);

        return $options;
    }

    private function assertIdentifierRequired(callable $save): void
    {
        try {
            $save();
            self::fail('A validation error was expected.');
        } catch (\WPDieException $e) {
            $response = json_decode((string) ob_get_contents(), true);
            self::assertFalse($response['success']);
            self::assertSame('hosted_fields_identifier_required', $response['data']['msg']);
        }
    }

    public function test_switching_to_a_mode_with_oauth2_does_not_require_the_hidden_identifier(): void
    {
        $stored = $this->stored_options(self::LIVE_ONLY_OAUTH, false, '');

        $identifier = (new Ajax())->resolve_hosted_fields_identifier(['payplug_sandbox' => false], 'hosted_fields', $stored);

        self::assertSame('', $identifier);
    }

    public function test_switching_away_from_the_displayed_mode_still_requires_the_shown_identifier(): void
    {
        $stored = $this->stored_options(self::LIVE_ONLY_OAUTH, true, '');

        $this->assertIdentifierRequired(static function () use ($stored): void {
            (new Ajax())->resolve_hosted_fields_identifier(['payplug_sandbox' => true, 'identifier' => ''], 'hosted_fields', $stored);
        });
    }

    public function test_shown_identifier_left_empty_is_rejected(): void
    {
        $stored = $this->stored_options(self::TEST_ONLY_OAUTH, false, 'ACC-1');

        $this->assertIdentifierRequired(static function () use ($stored): void {
            (new Ajax())->resolve_hosted_fields_identifier(['identifier' => '   '], 'hosted_fields', $stored);
        });
    }

    public function test_submitted_identifier_is_sanitized_and_saved(): void
    {
        $stored = $this->stored_options(self::TEST_ONLY_OAUTH, false, 'ACC-1');

        $identifier = (new Ajax())->resolve_hosted_fields_identifier(['identifier' => " ACC-2\n"], 'hosted_fields', $stored);

        self::assertSame('ACC-2', $identifier);
    }

    public function test_stored_identifier_is_kept_when_the_field_was_not_rendered(): void
    {
        $stored = $this->stored_options('{}', false, 'ACC-1');

        $identifier = (new Ajax())->resolve_hosted_fields_identifier([], 'hosted_fields', $stored);

        self::assertSame('ACC-1', $identifier);
    }

    public function test_identifier_is_not_required_for_another_embedded_mode(): void
    {
        $stored = $this->stored_options(self::TEST_ONLY_OAUTH, false, '');

        self::assertSame('', (new Ajax())->resolve_hosted_fields_identifier(['identifier' => ''], 'redirect', $stored));
    }
}
