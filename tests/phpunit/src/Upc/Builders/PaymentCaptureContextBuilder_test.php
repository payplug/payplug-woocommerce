<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Builders;

use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Upc\Builders\PaymentCaptureContextBuilder;
use PHPUnit\Framework\TestCase;

class PaymentCaptureContextBuilder_test extends TestCase
{
    private const DALENYS_RETURN_URL = 'https://shop.example.test/checkout/order-pay/551/?IDENTIFIER=JPYCredoraxUHF'
        . '&OPERATIONTYPE=payment&TRANSACTIONID=A2611171512345678&CLIENTIDENT=guest-551'
        . '&CLIENTEMAIL=jane%40example.test&ORDERID=551&AMOUNT=2300&VERSION=3.0&LANGUAGE=FR&CURRENCY=USD'
        . '&EXECCODE=4001&MESSAGE=The+authentication+of+the+cardholder+failed%2E&DESCRIPTOR=PAYPLUG+SHOP'
        . '&3DSECURE=yes&CARDCODE=402205XXXXXX0001&CARDTYPE=VISA&CARDNETWORK=VISA&CARDVALIDITYDATE=12-30'
        . '&CARDFULLNAME=Jane+Doe&CARDCOUNTRY=FR&CARDUSAGE=CREDIT&CARD3DSECUREENROLLED=yes'
        . '&3DSECUREVERSION=2.2.0&3DSGLOBALSTATUS=NOT_AUTHENTICATED&3DSECUREMODE=CHALLENGE'
        . '&3DSECUREPREFERENCE=SCA&HASH=8f1c2a4b6d8e0f13579bdf2468ace0123456789abcdef0123456789abcdef0123'
        . '&pay_for_order=true&key=wc_order_Ab3dEfGh1JkLm&payplug_upc_cancelled=1';

    /** @var \WC_Order */
    private $order;

    /** @var mixed */
    private $previous_referer;

    protected function setUp(): void
    {
        $this->previous_referer = $_SERVER['HTTP_REFERER'] ?? null;
        unset($_SERVER['HTTP_REFERER']);

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
        if (null === $this->previous_referer) {
            unset($_SERVER['HTTP_REFERER']);
        } else {
            $_SERVER['HTTP_REFERER'] = $this->previous_referer;
        }
        parent::tearDown();
    }

    /**
     * @return string[][]
     */
    public function provideReferers(): array
    {
        return [
            'order-pay page reached back from a failed 3DS' => [self::DALENYS_RETURN_URL, 'https://shop.example.test/checkout/order-pay/551/'],
            'order-pay link' => ['https://shop.example.test/checkout/order-pay/551/?pay_for_order=true&key=wc_order_Ab3dEfGh1JkLm', 'https://shop.example.test/checkout/order-pay/551/'],
            'checkout page' => ['https://shop.example.test/checkout/', 'https://shop.example.test/checkout/'],
            'percent-encoded path' => ['https://shop.example.test/my%20page%40x/?email=jane%40example.test', 'https://shop.example.test/my%20page%40x/'],
            'credentials' => ['https://user:secret@shop.example.test/checkout/', 'https://shop.example.test/checkout/'],
            'port and fragment' => ['http://shop.example.test:8080/checkout/#payment', 'http://shop.example.test:8080/checkout/'],
            'no path' => ['https://shop.example.test', 'https://shop.example.test/'],
            'uppercase scheme' => ['HTTPS://shop.example.test/checkout/', 'https://shop.example.test/checkout/'],
            'path over 255 characters' => ['https://shop.example.test/' . str_repeat('a', 400) . '/?utm_source=x', 'https://shop.example.test/'],
            'host over 255 characters' => ['https://' . str_repeat('a', 300) . '.example.test/checkout/', ''],
            'not a url' => ['not a url', ''],
            'javascript scheme' => ['javascript:alert(1)', ''],
            'ftp scheme' => ['ftp://shop.example.test/checkout/', ''],
            'scheme-relative' => ['//shop.example.test/checkout/', ''],
            'no host' => ['http:///checkout/', ''],
            'empty' => ['', ''],
        ];
    }

    /**
     * @dataProvider provideReferers
     */
    public function testBuildSendsTheRefererAsAnAbsoluteUrlWithoutQueryString(string $referer, string $expected): void
    {
        $_SERVER['HTTP_REFERER'] = $referer;

        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertSame($expected, $dto->browser->referrer);
        $this->assertLessThanOrEqual(255, strlen($dto->browser->referrer));
    }

    public function testBuildSendsAnEmptyRefererWhenTheRequestHasNone(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertSame('', $dto->browser->referrer);
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

    /**
     * PRE-3727: the amount is sent in the minor units of the ORDER's currency - 1:1 for a
     * zero-decimal currency, x100 for a 2-decimal one.
     */
    public function testBuildSendsAZeroDecimalOrderAmountAsIs(): void
    {
        $this->order->set_currency('JPY');
        $this->order->set_total(1000);
        $this->order->save();

        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'VISA', false);

        $this->assertSame(1000, $dto->common->amount);
        $this->assertSame('JPY', $dto->common->currency);
    }

    public function testBuildSendsATwoDecimalOrderAmountInCents(): void
    {
        $this->order->set_currency('EUR');
        $this->order->set_total(1000);
        $this->order->save();

        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'VISA', false);

        $this->assertSame(100000, $dto->common->amount);
        $this->assertSame('EUR', $dto->common->currency);
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

    public function testBuildSendsAPerOrderCustomerForAGuestOrder(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', false);

        $this->assertSame('guest-' . $this->order->get_id(), $dto->customer->id);
        $this->assertSame('jane@example.test', $dto->customer->email);
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

    public function testBuildSendsTheTypedCardholderAsFullName(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', true, '  John Smith ');

        $this->assertSame('John Smith', $dto->paymentMethod['details']['fullName']);
    }

    public function testBuildFallsBackToTheBillingNameWhenNoCardholderIsTyped(): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', 'visa', true, '   ');

        $this->assertSame('Jane Doe', $dto->paymentMethod['details']['fullName']);
    }

    /**
     * @return string[][]
     */
    public function provideAllowedBrands(): array
    {
        return [
            'lowercase visa' => ['visa'],
            'uppercase VISA' => ['VISA'],
            'mixed-case Mastercard' => ['Mastercard'],
        ];
    }

    /**
     * @dataProvider provideAllowedBrands
     */
    public function testBuildKeepsAnAllowedBrandRegardlessOfCasing(string $brand): void
    {
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', $brand, false);

        $this->assertSame($brand, $dto->paymentMethod['details']['selectedBrand']);
    }

    /**
     * @return string[][]
     */
    public function provideDisallowedBrands(): array
    {
        return [
            'unknown brand' => ['AMEX'],
            'CB, only accepted in EUR' => ['CB'],
            'lowercase cb' => ['cb'],
            'reflected markup' => ['<script>alert(1)</script>'],
            'empty string' => [''],
        ];
    }

    /**
     * @dataProvider provideDisallowedBrands
     */
    public function testBuildResetsADisallowedBrandToAnEmptyString(string $brand): void
    {
        // Front\UpcCheckoutReturn::render_upc_3ds_redirect() echoes the Unified API's challenge HTML
        // unescaped on the shop's own origin - selectedBrand must never reach that API call as
        // anything but a known-safe value.
        $dto = (new PaymentCaptureContextBuilder())->build($this->order, 'hf_token_123', $brand, false);

        $this->assertSame('', $dto->paymentMethod['details']['selectedBrand']);
    }

    /**
     * @dataProvider provideAllowedBrands
     */
    public function testIsAllowedBrandAcceptsVisaAndMastercard(string $brand): void
    {
        $this->assertTrue(PaymentCaptureContextBuilder::is_allowed_brand($brand));
    }

    /**
     * PRE-3746: CB is only accepted in EUR and UHF only handles non-EUR carts - HostedFieldsPaymentProcessor
     * refuses the payment with the unsupported-brand message when this returns false.
     *
     * @dataProvider provideDisallowedBrands
     */
    public function testIsAllowedBrandRejectsEverythingElse(string $brand): void
    {
        $this->assertFalse(PaymentCaptureContextBuilder::is_allowed_brand($brand));
    }
}
