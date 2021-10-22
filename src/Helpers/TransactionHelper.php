<?php

namespace AmazonPayCheckout\Helpers;

use AmazonPayCheckout\Contracts\TransactionRepositoryContract;
use AmazonPayCheckout\Models\Transaction;
use AmazonPayCheckout\Struct\Refund;
use AmazonPayCheckout\Struct\StatusDetails;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Modules\Payment\Models\Payment;

class TransactionHelper
{
    use LoggingTrait;

    /**
     * @var \AmazonPayCheckout\Contracts\TransactionRepositoryContract
     */
    private $transactionRepository;

    public function __construct(TransactionRepositoryContract $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * @param \AmazonPayCheckout\Struct\Charge $charge
     * @param null|int $orderId
     *
     * @throws \Exception
     */
    public function updateCharge($charge, $orderId = null)
    {
        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);
        $chargeTransaction = $this->persistTransaction($charge, Transaction::TRANSACTION_TYPE_CHARGE, $orderId);
        $orderId = $chargeTransaction->order;
        if ($chargeTransaction->status === StatusDetails::CAPTURED && !empty($orderId)) {
            if (!$chargeTransaction->adminInformed) {
                /** @var \AmazonPayCheckout\Helpers\OrderHelper $orderHelper */
                $orderHelper = pluginApp(OrderHelper::class);
                $payment = $orderHelper->createPaymentObject($charge->captureAmount->amount, Payment::STATUS_CAPTURED, $charge->chargeId, '', null, Payment::PAYMENT_TYPE_CREDIT, Payment::TRANSACTION_TYPE_BOOKED_POSTING, $charge->captureAmount->currencyCode);
                $orderHelper->assignPlentyPaymentToPlentyOrder($payment, $orderHelper->getOrder($orderId));
                $chargeTransaction->adminInformed = 1;
                $chargeTransaction->paymentId = $payment->id;
                $this->transactionRepository->saveTransaction($chargeTransaction);
            }
        }elseif ($chargeTransaction->status === StatusDetails::AUTHORIZED && $configHelper->getConfigurationValue('captureMode') === 'after_auth') {
            $this->log(__CLASS__, __METHOD__, 'autoCapture', '', ['charge'=>$chargeTransaction]);
            /** @var ApiHelper $apiHelper */
            $apiHelper = pluginApp(ApiHelper::class);
            $capturedCharge = $apiHelper->capture($charge->chargeId);
            $this->persistTransaction($capturedCharge, Transaction::TRANSACTION_TYPE_CHARGE);
        }

        //TODO Get and update charge permission
    }


    public function updateRefund($refund, $orderId = null)
    {
        $refundTransaction = $this->persistTransaction($refund, Transaction::TRANSACTION_TYPE_REFUND, $orderId);
        $orderId             = $refundTransaction->order;

        if ($refundTransaction->status === StatusDetails::REFUNDED && !empty($orderId)) {
            if (!$refundTransaction->adminInformed) {
                /** @var \AmazonPayCheckout\Helpers\OrderHelper $orderHelper */
                $orderHelper = pluginApp(OrderHelper::class);
                $payment = $orderHelper->createPaymentObject($refund->refundAmount->amount, Payment::STATUS_REFUNDED, $refund->refundId, '', null, Payment::PAYMENT_TYPE_DEBIT, Payment::TRANSACTION_TYPE_BOOKED_POSTING, $refund->refundAmount->currencyCode);
                $orderHelper->assignPlentyPaymentToPlentyOrder($payment, $orderHelper->getOrder($orderId));
                $refundTransaction->adminInformed = 1;
                $refundTransaction->paymentId = $payment->id;
                $this->transactionRepository->saveTransaction($refundTransaction);
            }
        }
    }

    public function persistTransaction($transactionStruct, $type, $orderId = null, $paymentId = null)
    {
        /** @var Transaction $transaction */
        if ($type === Transaction::TRANSACTION_TYPE_CHARGE_PERMISSION) {
            $transaction = $this->getChargePermissionTransaction($transactionStruct);
        } elseif ($type === Transaction::TRANSACTION_TYPE_CHARGE) {
            $transaction = $this->getChargeTransaction($transactionStruct);
        } elseif ($type === Transaction::TRANSACTION_TYPE_REFUND) {
            $transaction = $this->getRefundTransaction($transactionStruct);
        } else {
            throw new Exception('Invalid Transaction Type ' . $type);
        }

        if ($orderId) {
            $transaction->order = $orderId;
        }

        if ($paymentId) {
            $transaction->paymentId = $paymentId;
        }

        $this->transactionRepository->saveTransaction($transaction);

        return $transaction;
    }

    /**
     * @param \AmazonPayCheckout\Struct\ChargePermission $chargePermission
     *
     * @return Transaction
     */
    protected function getChargePermissionTransaction($chargePermission)
    {
        $transaction = $this->getTransaction($chargePermission->chargePermissionId, Transaction::TRANSACTION_TYPE_CHARGE_PERMISSION);
        $transaction->amount = $chargePermission->limits->amountLimit->amount;
        $transaction->currency = $chargePermission->limits->amountLimit->amount;
        //->setCapturedAmount($chargePermission->getLimits()->getAmountLimit()->getAmount() - $chargePermission->getLimits()->getAmountBalance()->getAmount())
        $transaction->status = $chargePermission->statusDetails->state;
        $transaction->time = $chargePermission->creationTimestamp;
        $transaction->expiration = $chargePermission->expirationTimestamp;
        return $transaction;
    }

    /**
     * @param string $reference
     * @param string $type
     *
     * @return Transaction
     */
    public function getTransaction(string $reference, string $type)
    {

        if ($transactions = $this->transactionRepository->getTransactions([['reference', '=', $reference], ['type', '=', $type]])) {
            return $transactions[0];
        } else {
            /** @var Transaction $transaction */
            $transaction = pluginApp(Transaction::class);
            $transaction->reference = $reference;
            $transaction->type = $type;
            $transaction->merchantId = 'todo';//TODO
            $transaction->mode = 'todo';//TODO
        }

        return $transaction;
    }

    /**
     * @param \AmazonPayCheckout\Struct\Charge $charge
     *
     * @return Transaction
     */
    protected function getChargeTransaction($charge)
    {
        $transaction = $this->getTransaction($charge->chargeId, Transaction::TRANSACTION_TYPE_CHARGE);
        $transaction->amount = $charge->chargeAmount->amount;
        $transaction->currency = $charge->chargeAmount->currencyCode;
        $transaction->status = $charge->statusDetails->state;
        $transaction->time = $charge->creationTimestamp;
        $transaction->expiration = $charge->expirationTimestamp;

        if ($charge->captureAmount) {
            //$transaction->capturedAmount=(float)$charge->captureAmount->amount;
        }
        if ($charge->refundedAmount) {
            //$transaction->refundedAmount = (float)$charge->refundedAmount->amount;
        }

        return $transaction;
    }

    /**
     * @param Refund $refund
     * @return mixed
     */
    protected function getRefundTransaction($refund)
        {
            $transaction = $this->getTransaction($refund->refundId, Transaction::TRANSACTION_TYPE_REFUND);
            $transaction->amount = (float)$refund->refundAmount->amount;
            $transaction->currency = $refund->refundAmount->currencyCode;
            $transaction->status = $refund->statusDetails->state;
            $transaction->time = $refund->creationTimestamp;
            return $transaction;
        }


    /**
     * @param int $orderId
     *
     * @return Transaction[]
     */
    public function getOrderTransactions(int $orderId)
    {
        return $this->transactionRepository->getTransactions([['order', '=', $orderId]]);
    }

}