<?php

namespace AmazonPayCheckout\Helpers;

use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Property\Contracts\OrderPropertyRepositoryContract;
use Plenty\Modules\Order\Property\Models\OrderProperty;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Templates\Twig;

class OrderHelper
{
    use LoggingTrait;

    public function createPaymentObject($amount, $status, $transactionId, $comment = '', $dateTime = null, $type = Payment::PAYMENT_TYPE_CREDIT, $transactionType = Payment::TRANSACTION_TYPE_BOOKED_POSTING, $currency = 'EUR'): Payment
    {
        $this->log(__CLASS__, __METHOD__, 'start', '', [$amount, $status, $transactionId, $comment, $dateTime, $type, $transactionType, $currency]);
        if ($dateTime === null) {
            $dateTime = date('Y-m-d H:i:s');
        }
        /** @var \AmazonPayCheckout\Helpers\PaymentMethodHelper $paymentMethodHelper */
        $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);

        /** @var \Plenty\Modules\Payment\Contracts\PaymentRepositoryContract $paymentRepository */
        $paymentRepository = pluginApp(PaymentRepositoryContract::class);

        /** @var Payment $payment */
        $payment = pluginApp(Payment::class);

        $payment->mopId = (int)$paymentMethodHelper->createMopIfNotExistsAndReturnId();
        $payment->transactionType = $transactionType;
        $payment->type = $type;
        $payment->status = $status;
        $payment->currency = $currency;
        $payment->isSystemCurrency = ($currency === 'EUR');
        $payment->amount = $amount;
        $payment->receivedAt = $dateTime;
        if ($status != Payment::STATUS_CAPTURED && $status != Payment::STATUS_REFUNDED) {
            $payment->unaccountable = 1;
        } else {
            $payment->unaccountable = 0;
        }

        $paymentProperties = [];
        $paymentProperties[] = $this->createPaymentProperty(PaymentProperty::TYPE_BOOKING_TEXT, $transactionId . ' ' . $comment);
        $paymentProperties[] = $this->createPaymentProperty(PaymentProperty::TYPE_TRANSACTION_ID, $transactionId);


        $payment->properties = $paymentProperties;
        $payment = $paymentRepository->createPayment($payment);
        $this->log(__CLASS__, __METHOD__, 'result', '', [$payment]);

        return $payment;
    }

    /**
     * @param Order $order
     * @param PaymentMethodHelper $paymentMethodHelper
     * @return string
     */
    public function createPayButtonForExistingOrder(Order $order, PaymentMethodHelper $paymentMethodHelper): string
    {
        $paymentMethodId = $paymentMethodHelper->createMopIfNotExistsAndReturnId();
        $this->log(__CLASS__, __METHOD__, 'start', '', ['order' => $order, 'paymentMethod' => $paymentMethodId]);
        /** @var OrderProperty $property */
        foreach ($order->properties as $property) {
            if ((int)$property->typeId === 3) {
                if ((int)$property->value === (int)$paymentMethodId) {
                    /** @var TransactionHelper $transactionHelper */
                    $transactionHelper = pluginApp(TransactionHelper::class);
                    if (count($transactionHelper->getOrderTransactions($order->id))) {
                        return '';
                    }
                    $this->log(__CLASS__, __METHOD__, 'order', '', [$property, $property->typeId, $property->value]);
                    return pluginApp(Twig::class)->render('AmazonPayCheckout::content.payment_method_reinitialize', [
                        'order' => $order,
                    ]);
                }
            }
        }
        return '';
    }

    /**
     * @param int $typeId
     * @param string $value
     *
     * @return PaymentProperty
     */
    private function createPaymentProperty(int $typeId, string $value): PaymentProperty
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty = pluginApp(PaymentProperty::class);
        $paymentProperty->typeId = $typeId;
        $paymentProperty->value = $value;

        return $paymentProperty;
    }

    /**
     * @param Payment $payment
     * @param Order $order
     *
     * @return bool
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, Order $order): bool
    {
        $this->log(__CLASS__, __METHOD__, 'start', '', ['order' => $order, 'payment' => $payment]);

        try {
            /** @var AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            /** @var \Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract $paymentOrderRelationRepository */
            $paymentOrderRelationRepository = pluginApp(PaymentOrderRelationRepositoryContract::class);

            $return = $authHelper->processUnguarded(
                function () use ($paymentOrderRelationRepository, $payment, $order) {
                    return $paymentOrderRelationRepository->createOrderRelation($payment, $order);
                }
            );
            $this->log(__CLASS__, __METHOD__, 'success', '', [$return]);

        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'error', 'assign payment to order failed', [$e, $e->getMessage()], true);
            return false;
        }

        return true;
    }

    public function setOrderExternalId($orderId, $externalId)
    {
        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var OrderPropertyRepositoryContract $orderPropertyRepository */
        $orderPropertyRepository = pluginApp(OrderPropertyRepositoryContract::class);
        $loggable = $this;
        $authHelper->processUnguarded(
            function () use ($orderPropertyRepository, $orderId, $externalId, $loggable) {
                try {
                    /** @var OrderProperty $existing */
                    $existing = $orderPropertyRepository->findByOrderId($orderId, OrderPropertyType::EXTERNAL_ORDER_ID);
                    $existingArray = $existing->toArray();
                    if (!empty($existingArray)) {
                        $loggable->log(__CLASS__, __METHOD__, 'existing', '', [$existingArray]);
                        return;
                    }
                    $orderProperty = $orderPropertyRepository->create([
                        'orderId' => $orderId,
                        'typeId' => OrderPropertyType::EXTERNAL_ORDER_ID,
                        'value' => $externalId,
                    ]);
                    $loggable->log(__CLASS__, __METHOD__, 'success', '', [$orderProperty]);
                } catch (\Exception $e) {
                    $loggable->log(__CLASS__, __METHOD__, 'error', '', [$e->getCode(), $e->getMessage(), $e->getLine()], true);
                }

            });
    }

    /**
     * @param int $orderId
     *
     * @return Order
     */
    public function getOrder(int $orderId)
    {
        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);
        /** @var \Plenty\Modules\Order\Contracts\OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(OrderRepositoryContract::class);

        return $authHelper->processUnguarded(
            function () use ($orderRepository, $orderId) {
                return $orderRepository->findOrderById($orderId);
            }
        );
    }

    public function setOrderStatusAuthorized($orderId)
    {
        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(OrderRepositoryContract::class);

        if($order = $orderRepository->findOrderById($orderId)){
            if((float)$order->statusId > 3.001){
                return;
            }
        }

        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);
        $newOrderStatus = $configHelper->getAuthorizedStatus();
        if ($newOrderStatus === '4/5') {
            try {
                $this->log(__CLASS__, __METHOD__, 'auth_status_45', 'start intelligent stock', ['order' => $orderId]);

                /** @var AuthHelper $authHelper */
                $authHelper = pluginApp(AuthHelper::class);
                $authHelper->processUnguarded(
                    function () use ($orderRepository, $orderId) {
                        return $orderRepository->setOrderStatus45((int)$orderId);
                    }
                );
            } catch (\Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'auth_status_45_failed', 'set intelligent stock order status failed', [$e, $e->getMessage()], true);
            }
        } else {
            $this->setOrderStatus($orderId, $newOrderStatus);
        }
    }

    public function setOrderStatus($orderId, $status)
    {
        $this->log(__CLASS__, __METHOD__, 'start', 'try to set order status', ['order' => $orderId, 'status' => $status]);
        if (!empty($status)) {
            $order = ['statusId' => (float)$status];
            $response = '';
            try {
                /** @var OrderRepositoryContract $orderRepository */
                $orderRepository = pluginApp(OrderRepositoryContract::class);
                /** @var AuthHelper $authHelper */
                $authHelper = pluginApp(AuthHelper::class);
                $response = $authHelper->processUnguarded(
                    function () use ($orderRepository, $order, $orderId) {
                        return $orderRepository->updateOrder($order, (int)$orderId);
                    }
                );
            } catch (\Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'failed', 'set order status failed', [$e, $e->getMessage()], true);
            }
            $this->log(__CLASS__, __METHOD__, 'done', 'finished set order status', ['order' => $response, 'status' => $status]);
        } else {
            $this->log(__CLASS__, __METHOD__, 'empty_status', 'set order status cancelled because of empty status', null);
        }

    }

}
