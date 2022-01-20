<?php

namespace AmazonPayCheckout\Procedures;

use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Helpers\TransactionHelper;
use AmazonPayCheckout\Models\Transaction;
use AmazonPayCheckout\Repositories\TransactionRepository;
use AmazonPayCheckout\Struct\StatusDetails;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\OrderType;

class CaptureProcedure
{
    use LoggingTrait;

    public function run(EventProceduresTriggered $eventTriggered, TransactionRepository $transactionRepository, TransactionHelper $transactionHelper, ApiHelper $apiHelper)
    {
        $order = $eventTriggered->getOrder();
        $this->log(__CLASS__, __METHOD__, 'start', '', [$order]);
        switch ($order->typeId) {
            case OrderType::TYPE_SALES_ORDER:
                $orderId = $order->id;
                break;
        }
        if (empty($orderId)) {
            throw new Exception('Amazon Pay Capture failed! The given order is invalid!');
        }

        $authorizedCharges = $transactionRepository->getTransactions([
            ['order', '=', $orderId],
            ['type', '=', Transaction::TRANSACTION_TYPE_CHARGE],
            ['status', '=', StatusDetails::AUTHORIZED],
        ]);

        foreach ($authorizedCharges as $authorizedCharge) {
            $charge = $apiHelper->getCharge($authorizedCharge->reference);
            $transactionHelper->updateCharge($charge);
        }

        $authorizedCharges = $transactionRepository->getTransactions([
            ['order', '=', $orderId],
            ['type', '=', Transaction::TRANSACTION_TYPE_CHARGE],
            ['status', '=', StatusDetails::AUTHORIZED],
        ]);

        /* TODO
        if (count($authorizedCharges) === 0) {
            $oroArr = $transactionHelper->amzTransactionRepository->getTransactions([
                ['order', '=', $orderId],
                ['type', '=', 'order_ref'],
            ]);
            $oro    = $oroArr[0];
            $amount = $oro->amount;
            $transactionHelper->authorize($oro->orderReference, $amount, 0);

            $openAuths = $transactionHelper->amzTransactionRepository->getTransactions([
                ['order', '=', $orderId],
                ['type', '=', 'auth'],
                ['status', '=', 'Open'],
            ]);

        }
        */

        foreach ($authorizedCharges as $authorizedCharge) {
            $this->log(__CLASS__, __METHOD__, 'before_capture', '', [$authorizedCharge, $order->amount]);
            $amountToCapture = min($authorizedCharge->amount, $order->amount->invoiceTotal);
            $capturedCharge = $apiHelper->capture($authorizedCharge->reference, $amountToCapture);
            $transactionHelper->persistTransaction($capturedCharge, Transaction::TRANSACTION_TYPE_CHARGE);
            $transactionHelper->updateCharge($capturedCharge); //to trigger additional actions
        }

    }
}
