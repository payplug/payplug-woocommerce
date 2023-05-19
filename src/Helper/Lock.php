<?php

namespace Payplug\PayplugWoocommerce\Helper;

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

class Lock
{

	public function handleLock($payment_id){

		do{
			$lock = \Payplug\PayplugWoocommerce\Model\Lock::get_lock($payment_id);
			if( !empty($lock) ){
				if( time() - strtotime($lock->created) > 9 ) {
					\Payplug\PayplugWoocommerce\Model\Lock::delete_lock($payment_id);

				}else{
					sleep(1);
				}

			}

		} while ( \Payplug\PayplugWoocommerce\Model\Lock::insert_lock($payment_id) === false );

	}

	public function deleteLock($payment_id){
		return \Payplug\PayplugWoocommerce\Model\Lock::delete_lock($payment_id);
	}

}
