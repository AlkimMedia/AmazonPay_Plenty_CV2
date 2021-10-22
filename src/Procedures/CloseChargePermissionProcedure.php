<?php

namespace AmazonPayCheckout\Procedures;


use AmazonPayCheckout\Contracts\TransactionRepositoryContract;
use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Helpers\TransactionHelper;
use AmazonPayCheckout\Models\Transaction;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\OrderType;

class CloseChargePermissionProcedure
{
    use LoggingTrait;
    public function run(EventProceduresTriggered $eventTriggered,TransactionRepositoryContract $transactionRepository, ApiHelper $apiHelper, TransactionHelper $transactionHelper)
    {
        $order = $eventTriggered->getOrder();
        $this->log(__CLASS__, __METHOD__, 'closeOrderProcedure', '', [$order]);
        switch ($order->typeId) {
            case OrderType::TYPE_SALES_ORDER:
                $orderId = $order->id;
                break;
        }
        if (empty($orderId)) {
            throw new Exception('Amazon Pay Close Order failed! The given order is invalid!');
        }

        $chargePermissionTransaction = $transactionRepository->getTransactions([
            ['orderId', '=', $orderId],
            ['type', '=', Transaction::TRANSACTION_TYPE_CHARGE_PERMISSION]
        ])[0];

        $chargePermission = $apiHelper->closeChargePermission($chargePermissionTransaction->orderReference);
        $transactionHelper->persistTransaction($chargePermission, Transaction::TRANSACTION_TYPE_CHARGE_PERMISSION);
    }
}