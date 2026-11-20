<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

/**
 * Simple product reporting the type of a WooCommerce Subscriptions product.
 */
class SubscriptionProductStub extends \WC_Product_Simple
{
    public function get_type()
    {
        return 'subscription';
    }
}
