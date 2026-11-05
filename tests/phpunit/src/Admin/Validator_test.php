<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Payplug\PayplugWoocommerce\Admin\Validator;
use PHPUnit\Framework\TestCase;

class Validator_test extends TestCase
{
    /**
     * wp_send_json_error() only goes through wp_die() during an AJAX request (a plain die
     * otherwise), so every test fakes one and turns the die into a WPDieException.
     */
    protected function setUp(): void
    {
        parent::setUp();
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', static function () {
            return static function (): void {
                throw new \WPDieException();
            };
        });
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        remove_all_filters('wp_doing_ajax');
        remove_all_filters('wp_die_ajax_handler');
        parent::tearDown();
    }

    private function assertValidationErrorSent(callable $validation): void
    {
        try {
            $validation();
            self::fail('A validation error was expected.');
        } catch (\WPDieException $e) {
            $response = json_decode((string) ob_get_contents(), true);
            self::assertFalse($response['success']);
            self::assertSame('hosted_fields_identifier_required', $response['data']['msg']);
        }
    }

    public function test_hosted_fields_identifier_is_required_when_oauth2_connected(): void
    {
        $this->assertValidationErrorSent(static function (): void {
            Validator::hostedFieldsPaymentGatewayOptions('hosted_fields', '  ', true);
        });
    }

    public function test_hosted_fields_identifier_is_required_by_default(): void
    {
        $this->assertValidationErrorSent(static function (): void {
            Validator::hostedFieldsPaymentGatewayOptions('hosted_fields', '');
        });
    }

    public function test_hosted_fields_identifier_is_not_required_without_oauth2(): void
    {
        self::assertTrue(Validator::hostedFieldsPaymentGatewayOptions('hosted_fields', '', false));
    }

    public function test_identifier_is_not_required_for_another_embedded_mode(): void
    {
        self::assertTrue(Validator::hostedFieldsPaymentGatewayOptions('redirect', '', true));
    }

    public function test_hosted_fields_identifier_is_accepted_when_oauth2_connected(): void
    {
        self::assertTrue(Validator::hostedFieldsPaymentGatewayOptions('hosted_fields', 'acct_1', true));
    }
}
