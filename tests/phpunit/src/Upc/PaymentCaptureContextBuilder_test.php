<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Upc\PaymentCaptureContextBuilder;
use PHPUnit\Framework\TestCase;

class PaymentCaptureContextBuilder_test extends TestCase
{
    /** @var \WC_Order */
    private $order;

    protected function setUp(): void
    {
        (new Configuration())->initialize_option();
        $configuration = new Configuration();
        $options = $configuration->get_options();
        $options['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acc_test_123';
        $configuration->update_options($options);

        $this->order = wc_create_order();
        $this->order->set_currency('USD');
        $this->order->set_billing_first_name('Jane');
        $this->order->set_billing_last_name('Doe');
        $this->order->set_billing_email('jane@example.test');
        $this->order->set_billing_address_1('1 Main St');
        $this->order->set_billing_city('Springfield');
        $this->order->set_billing_country('US');
        $this->order->set_billing_state('IL');
        $this->order->set_billing_postcode('62701');
        $this->order->add_product(\WC_Helper_Product::create_simple_product(), 1);
        $this->order->calculate_totals();
        $this->order->save();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_payplug_settings');
        wp_delete_post($this->order->get_id(), true);
        parent::tearDown();
    }

    public function testBuildSetsTheCommonFieldsFromTheOrder(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertSame('acc_test_123', $dto->common->accountId);
        $this->assertSame('USD', $dto->common->currency);
        $this->assertSame((string) $this->order->get_id(), $dto->common->orderId);
        $this->assertSame('', $dto->common->submerchantExternalId);
        $this->assertSame('hf_token_123', $dto->hfToken);
    }

    public function testBuildSetsTheNotificationUrlToTheFixedIpnRoute(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        // The Unified API ignores this field (delivery is exclusively via Cockpit's Receiver, see
        // Front\UpcWebhook), but it's still sent as an absolute URL matching that same fixed
        // route, to satisfy the DTO's contract.
        $this->assertSame(home_url('/payplug/v2/ipn'), $dto->common->notificationUrl);
    }

    public function testBuildSetsBillingFromTheOrderAddress(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertSame('1 Main St', $dto->common->billing->address->line);
        $this->assertSame('Jane', $dto->common->billing->contact->firstName);
    }

    public function testBuildOmitsCustomerForAGuestOrder(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertNull($dto->customer);
    }

    public function testBuildSetsCustomerForARegisteredCustomer(): void
    {
        $this->order->set_customer_id(1);
        $this->order->save();

        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertSame('1', $dto->customer->id);
        $this->assertSame('jane@example.test', $dto->customer->email);
    }

    public function testBuildWithoutSaveCardOmitsRecurringModeAndSaveFutureUsage(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertNull($dto->recurringMode);
        $this->assertArrayNotHasKey('saveFutureUsage', $dto->paymentMethod);
    }

    public function testBuildWithSaveCardSetsRecurringModeAndSaveFutureUsage(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', true);

        $this->assertSame('ONE_CLICK', $dto->recurringMode);
        $this->assertTrue($dto->paymentMethod['saveFutureUsage']);
    }

    public function testBuildSetsThePaymentMethodDetailsFromTheSelectedBrand(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertSame('visa', $dto->paymentMethod['details']['selectedBrand']);
        $this->assertSame('Jane Doe', $dto->paymentMethod['details']['fullName']);
    }
}
