<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugOney4xWithoutFees extends PayplugOney3xWithoutFees
{
    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'oney_x4_without_fees';

    protected $icon = 'x4_without_fees_';
}
