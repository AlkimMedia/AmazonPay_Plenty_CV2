<?php

namespace AmazonPayCheckout\Procedures;

use AmazonPayCheckout\Contracts\TransactionRepositoryContract;
use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Helpers\OrderHelper;
use AmazonPayCheckout\Repositories\TransactionRepository;
use AmazonPayCheckout\Struct\Refund;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Payment\Models\Payment;

class RefundProcedure
{
    use LoggingTrait;

    public function run(EventProceduresTriggered $eventTriggered)
    {

        try {
            /** @var Order $order */
            $procedureOrderObject = $eventTriggered->getOrder();
            $this->log(__CLASS__, __METHOD__, 'start', '', [$procedureOrderObject]);
            $orderId = 0;
            /** @var OrderHelper $orderHelper */
            $orderHelper = pluginApp(OrderHelper::class);

            switch ($procedureOrderObject->typeId) {
                case OrderType::TYPE_CREDIT_NOTE:
                    $parentOrder = $procedureOrderObject->parentOrder;

                    $this->log(__CLASS__, __METHOD__, 'credit_note_info', '', [
                        'orderReferences' => $procedureOrderObject->orderReferences,
                        'isObject' => is_object($procedureOrderObject->orderReferences),
                        'isArray' => is_array($procedureOrderObject->orderReferences),
                    ]);

                    if (isset($procedureOrderObject->orderReferences)) {
                        foreach ($procedureOrderObject->orderReferences as $reference) {
                            if ($reference->referenceType == 'parent') {
                                $orderId = $reference->originOrderId;
                            }
                        }
                    }

                    if (empty($orderId) && $parentOrder instanceof Order && $parentOrder->typeId == 1) {
                        $orderId = $parentOrder->id;
                    }
                    break;
                case OrderType::TYPE_SALES_ORDER:
                    $orderId = $procedureOrderObject->id;
                    break;
            }
            $this->log(__CLASS__, __METHOD__, 'info', '', ['orderId' => $orderId, 'procedureOrderObjectId' => $procedureOrderObject->id]);
            if (empty($orderId)) {
                throw new Exception('Amazon Pay Refund failed! The given order is invalid!');
            }
            /** @var TransactionRepository $transactionRepository */
            $transactionRepository = pluginApp(TransactionRepositoryContract::class);

            $chargePermissionTransactions = $transactionRepository->getTransactions([
                ['order', '=', $orderId],
                ['type', '=', 'ChargePermission']
            ]);

            $this->log(__CLASS__, __METHOD__, 'amounts_pre', '', ['amounts' => $procedureOrderObject->amounts]);
            if(!empty($chargePermissionTransactions) && isset($chargePermissionTransactions[0])) {
                $chargePermissionTransaction = $chargePermissionTransactions[0];
                $this->log(__CLASS__, __METHOD__, 'amounts_charge_permission', '', ['chargePermissionTransaction' => $chargePermissionTransaction]);
                $currency = $chargePermissionTransaction->currency;
                $amounts = $orderHelper->getOrderAmountObjectByCurrency($procedureOrderObject, $currency);
            }else{
                //fallback
                $amounts = $procedureOrderObject->amounts[0];
            }

            $amount = $amounts->invoiceTotal - $amounts->giftCardAmount;
            $this->log(__CLASS__, __METHOD__, 'amount_final', '', ['amount' => $amount]);
            $captures = $transactionRepository->getTransactions([
                ['order', '=', $orderId],
                ['type', '=', 'Charge'],
                ['status', '=', 'Captured'],
                ['amount', '=', $amount],
            ]);
            $this->log(__CLASS__, __METHOD__, 'captures', '', $captures);

            if (!is_array($captures) || count($captures) == 0) {
                $captures = $transactionRepository->getTransactions([
                    ['order', '=', $orderId],
                    ['type', '=', 'Charge'],
                    ['status', '=', 'Captured'],
                    ['amount', '>', $amount],
                ]);
                $this->log(__CLASS__, __METHOD__, 'captures_2', '', $captures);
            }
            if (is_array($captures) && isset($captures[0]) && !empty($procedureOrderObject->id)) {
                $capture = $captures[0];
                /** @var ApiHelper $apiHelper */
                $apiHelper = pluginApp(ApiHelper::class);
                $refund = $apiHelper->refund($capture->reference, $amount, $procedureOrderObject->id);

                if($refund) {
                    //register payment information
                    /** @var Refund $refund */

                    $payment = $orderHelper->createPaymentObject(
                        $refund->refundAmount->amount,
                        Payment::STATUS_APPROVED,
                        $refund->refundId,
                        'Status: '.$refund->statusDetails->state,
                        null,
                        Payment::PAYMENT_TYPE_DEBIT,
                        Payment::TRANSACTION_TYPE_PROVISIONAL_POSTING,
                        $refund->refundAmount->currencyCode
                    );
                    $orderHelper->assignPlentyPaymentToPlentyOrder($payment, $orderHelper->getOrder($procedureOrderObject->id));
                }


            }
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'failed', '', [$e, $e->getMessage()], true);
        }

    }
}
