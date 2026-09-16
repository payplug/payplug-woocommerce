<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Upc\AliasPaymentContextBuilder;
use PHPUnit\Framework\TestCase;

class AliasPaymentContextBuilder_test extends TestCase
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
}
