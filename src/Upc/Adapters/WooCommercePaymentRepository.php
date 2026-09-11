<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\IPaymentRepository;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\Exceptions\PaymentNotFoundException;

class WooCommercePaymentRepository implements IPaymentRepository
{
    public function getByOrderId(string $orderId): OperationData
    {
        $order = wc_get_order((int) $orderId);

        if (!$order instanceof \WC_Order || '' === (string) $order->get_meta('_payplug_upc_operation_id')) {
            throw new PaymentNotFoundException(sprintf('No operation for order "%s".', $orderId));
        }

        return $this->from_order($order);
    }

    public function getByOperationId(string $operationId): OperationData
    {
        $order = $this->find_order_by_operation_id($operationId);

        if (!$order instanceof \WC_Order) {
            throw new PaymentNotFoundException(sprintf('No operation for operation id "%s".', $operationId));
        }

        return $this->from_order($order);
    }

    public function save(OperationData $operationData): void
    {
        $order = wc_get_order((int) $operationData->orderId);

        if (!$order instanceof \WC_Order) {
            throw new PaymentNotFoundException(sprintf('No order "%s" to save the operation against.', $operationData->orderId));
        }

        $order->update_meta_data('_payplug_upc_operation_id', $operationData->operationId);
        $order->update_meta_data('_payplug_upc_exec_code', $operationData->execCode);
        $order->update_meta_data('_payplug_upc_outcome', $operationData->outcome);
        $order->update_meta_data('_payplug_upc_amount', $operationData->amount);
        $order->save();
    }

    public function markTreated(string $operationId): void
    {
        $order = $this->find_order_by_operation_id($operationId);

        if (!$order instanceof \WC_Order) {
            throw new PaymentNotFoundException(sprintf('No operation for operation id "%s".', $operationId));
        }

        $order->update_meta_data('_payplug_upc_treated', 'yes');
        $order->save();
    }

    public function isTreated(string $operationId): bool
    {
        $order = $this->find_order_by_operation_id($operationId);

        return $order instanceof \WC_Order && 'yes' === $order->get_meta('_payplug_upc_treated');
    }

    private function find_order_by_operation_id(string $operationId): ?\WC_Order
    {
        $orders = wc_get_orders([
            'meta_key' => '_payplug_upc_operation_id',
            'meta_value' => $operationId,
            'limit' => 1,
            'return' => 'objects',
        ]);

        return $orders[0] ?? null;
    }

    private function from_order(\WC_Order $order): OperationData
    {
        return new OperationData(
            (string) $order->get_meta('_payplug_upc_operation_id'),
            (string) $order->get_meta('_payplug_upc_exec_code'),
            (string) $order->get_meta('_payplug_upc_outcome'),
            (int) $order->get_meta('_payplug_upc_amount'),
            (string) $order->get_id()
        );
    }
}
