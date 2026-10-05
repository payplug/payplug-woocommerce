<?php

namespace Payplug\PayplugWoocommerce\Helper;

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

class Lock
{
    /**
     * @throws \Payplug\PayplugWoocommerce\Exception\LockUnavailableException when no lock id can be obtained
     *
     * @return false|int the lock id, or false when the payment must not be processed by this request
     */
    public static function handle_insert($save, $payment_id)
    {
        if ($save) {
            $lock_id = \Payplug\PayplugWoocommerce\Model\Lock::insert_lock($payment_id);

            //there's a payment being processed this moment
            if (\Payplug\PayplugWoocommerce\Model\Lock::locked($payment_id, $lock_id)) {
                PayplugGateway::log(sprintf('Payment %s already being processed.', $payment_id));

                return false;
            }

            return $lock_id;
        } else {
            //saved request to be processed
            $lock = \Payplug\PayplugWoocommerce\Model\Lock::get_lock_by_payment_id($payment_id);

            if (empty($lock->id)) {
                return false;
            }

            return $lock->id;
        }
    }
}
