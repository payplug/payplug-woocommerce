<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

/**
 * Stand-in for \Payplug\Resource\Payment: public attributes plus recorded update() and abort() calls.
 */
class FakePayment
{
    public $id;
    public $metadata;
    public $is_paid = false;
    public $paid_at;
    public $failure;
    public $hosted_payment;
    public $payment_method = [];
    public $is_refunded = false;
    public $amount = 0;
    public $amount_refunded = 0;
    public $is_3ds = false;
    public $is_live = false;
    public $created_at = 1700000000;
    public $card;

    /**
     * @var array[]
     */
    public $updates = [];

    /**
     * @var int
     */
    public $aborts = 0;

    /**
     * @var array client passed to each abort() call
     */
    public $abort_clients = [];

    /**
     * @var \Throwable|null
     */
    public $abort_exception;

    /**
     * @param string $id
     * @param int|null $order_id
     */
    public function __construct($id, $order_id = null)
    {
        $this->id = $id;
        $this->metadata = null === $order_id ? null : ['order_id' => $order_id];
    }

    public function update(array $data)
    {
        $this->updates[] = $data;

        return (object) ['is_paid' => $this->is_paid];
    }

    public function abort($payplug = null)
    {
        $this->aborts++;
        $this->abort_clients[] = $payplug;
        if ($this->abort_exception) {
            throw $this->abort_exception;
        }

        return $this;
    }
}
