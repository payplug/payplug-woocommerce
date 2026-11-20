<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Builders;

use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Upc\Builders\AliasPaymentContextBuilder;
use PHPUnit\Framework\TestCase;

class AliasPaymentContextBuilder_test extends TestCase
{
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

    public function testBuildSetsTheAliasIdAndAlwaysUsesOneClickRecurringMode(): void
    {
        $dto = (new AliasPaymentContextBuilder())->build($this->order, 'alias_123');

        $this->assertSame('alias_123', $dto->aliasId);
        $this->assertSame('ONE_CLICK', $dto->recurringMode);
    }

    public function testBuildSetsTheCommonFieldsFromTheOrder(): void
    {
        $dto = (new AliasPaymentContextBuilder())->build($this->order, 'alias_123');

        $this->assertSame('acc_test_123', $dto->common->accountId);
        $this->assertSame('USD', $dto->common->currency);
        $this->assertSame((string) $this->order->get_id(), $dto->common->orderId);
    }

    public function testBuildSetsFullNameAndNoSaveFutureUsageOrSelectedBrand(): void
    {
        $dto = (new AliasPaymentContextBuilder())->build($this->order, 'alias_123');

        $this->assertSame('Jane Doe', $dto->paymentMethod['details']['fullName']);
        $this->assertArrayNotHasKey('saveFutureUsage', $dto->paymentMethod);
        $this->assertArrayNotHasKey('selectedBrand', $dto->paymentMethod['details']);
    }

    public function testBuildSendsTheRefererWithoutTheGatewayReturnQueryString(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://shop.example.test/checkout/order-pay/551/?IDENTIFIER=JPYCredoraxUHF'
            . '&EXECCODE=4001&CLIENTEMAIL=jane%40example.test&CARDFULLNAME=Jane+Doe&3DSGLOBALSTATUS=NOT_AUTHENTICATED'
            . '&HASH=' . str_repeat('8f1c2a4b', 8) . '&pay_for_order=true&key=wc_order_Ab3dEfGh1JkLm&payplug_upc_cancelled=1';

        $dto = (new AliasPaymentContextBuilder())->build($this->order, 'alias_123');

        $this->assertSame('https://shop.example.test/checkout/order-pay/551/', $dto->browser->referrer);
    }

    public function testBuildSendsAnEmptyRefererWhenTheRequestHasNone(): void
    {
        $dto = (new AliasPaymentContextBuilder())->build($this->order, 'alias_123');

        $this->assertSame('', $dto->browser->referrer);
    }
}
