<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

/**
 * Payment gateway double: records process_payment() calls and answers a fixed result.
 */
class FakeGateway extends \WC_Payment_Gateway
{
    public $domain_name = 'shop.example.test';

    /**
     * @var int[]
     */
    public $processed = [];

    /**
     * @var array
     */
    public $process_result = ['result' => 'failure'];

    /**
     * @var callable|null called with the order id when process_payment() runs
     */
    public $on_process;

    /**
     * @param string $id
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->enabled = 'yes';
    }

    public function process_payment($order_id)
    {
        $this->processed[] = $order_id;
        if ($this->on_process) {
            ($this->on_process)($order_id);
        }

        return $this->process_result;
    }

    public function validate_order_amount($amount)
    {
        return $amount;
    }

    public function save_card_available(): bool
    {
        return false;
    }
}
