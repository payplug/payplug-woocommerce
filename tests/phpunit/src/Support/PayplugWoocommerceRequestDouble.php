<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

use Payplug\PayplugWoocommerce\PayplugWoocommerceRequest;

/**
 * Replaces the PayPlug API calls and the gateway lookup; everything else is the production code.
 */
class PayplugWoocommerceRequestDouble extends PayplugWoocommerceRequest
{
    /**
     * @var string[]
     */
    public $calls = [];

    /**
     * @var FakePayment|null
     */
    public $payment;

    /**
     * @var array|null
     */
    public $payment_data;

    /**
     * @var FakeGateway|null
     */
    public $gateway_double;

    /**
     * @var bool
     */
    public $init_api_result = true;

    public function run_process_order_payment($order_id, $payment_method): void
    {
        $this->process_order_payment($order_id, $payment_method);
    }

    protected function init_api(): bool
    {
        $this->calls[] = 'init_api';

        return $this->init_api_result;
    }

    protected function retrieve_payment($payment_id)
    {
        $this->calls[] = 'retrieve_payment';

        return $this->payment;
    }

    protected function load_payment($payment_id, $order_id)
    {
        $this->calls[] = 'load_payment';

        return $this->payment;
    }

    protected function create_payment(array $payment_data)
    {
        $this->calls[] = 'create_payment';
        $this->payment_data = $payment_data;

        return $this->payment;
    }

    protected function process_apple_pay_order_pay($order_id): void
    {
        $this->calls[] = 'process_apple_pay_order_pay';
        parent::process_apple_pay_order_pay($order_id);
    }

    protected function get_payplug_gateway($id)
    {
        return $this->gateway_double && $id === $this->gateway_double->id ? $this->gateway_double : null;
    }
}
