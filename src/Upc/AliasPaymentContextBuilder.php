<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Upc\Traits\BuildsCommonFields;
use PayplugUnifiedCore\Dto\PaymentDto;

class AliasPaymentContextBuilder
{
    use BuildsCommonFields;

    public function build(\WC_Order $order, string $aliasId): PaymentDto
    {
        $common = $this->build_common($order);

        $payment_method = [
            'details' => [
                'fullName' => $order->get_formatted_billing_full_name(),
            ],
        ];

        return new PaymentDto(
            $common,
            $aliasId,
            // A one-click payment IS always ONE_CLICK - unlike HostedFieldDto's optional
            // recurringMode (only set when creating a NEW alias), PaymentDto's is mandatory.
            'ONE_CLICK',
            $this->build_browser(),
            $this->build_customer($order),
            $payment_method
        );
    }
}
