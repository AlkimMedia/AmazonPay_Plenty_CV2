<?php

namespace AmazonPayCheckout\Helpers;


use AmazonPayCheckout\Models\Transaction;
use AmazonPayCheckout\Struct\ChargePermission;
use AmazonPayCheckout\Traits\LoggingTrait;
use Plenty\Modules\Account\Address\Models\AddressOption;
use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Order\Property\Models\OrderProperty;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Modules\Payment\Models\Payment;

class ExternalOrderHelper
{

    protected TransactionHelper $transactionHelper;
    protected ApiHelper $apiHelper;
    protected ConfigHelper $configHelper;
    use LoggingTrait;

    /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
    public function __construct()
    {
        $this->transactionHelper = pluginApp(TransactionHelper::class);
        $this->apiHelper = pluginApp(ApiHelper::class);
        $this->configHelper = pluginApp(ConfigHelper::class);
    }

    public function process($maxTimeBack = 86400, $maxStatusId = 5)
    {
        if (!$this->configHelper->isExternalOrderMatchingActive()) {
            $this->log(__CLASS__, __METHOD__, 'off', '', ['configValue' => $this->configHelper->getConfigurationValue('useExternalOrderMatching')]);
            return;
        }
        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);
        $authHelper->processUnguarded(function () use ($maxTimeBack, $maxStatusId) {

            /** @var PaymentMethodHelper $paymentMethodHelper */
            $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);
            $paymentMethodId = (int)$paymentMethodHelper->createMopIfNotExistsAndReturnId();

            /** @var OrderRepositoryContract $orderRepository */
            $orderRepository = pluginApp(OrderRepositoryContract::class);
            $orderRepository->setFilters(
                [
                    'createdAtFrom' => date('c', time() - $maxTimeBack),
                    'statusIdTo' => $maxStatusId,
                    'orderTypes' => [OrderType::TYPE_SALES_ORDER],
                    'methodOfPaymentId' => $paymentMethodId, //probably not working
                ]
            );
            $page = 1;
            while ($orderResponse = $orderRepository->searchOrders($page, 100, ['payments', 'addresses'])) {
                /** @var Order[] $orders */
                $orders = $orderResponse->getResult();
                $this->log(__CLASS__, 'process', 'orders', '', [$orders]);
                foreach ($orders as $order) {
                    $orderPaymentMethodId = null;
                    foreach ($order['properties'] as $orderProperty) {
                        if ((int)$orderProperty['typeId'] === (int)OrderPropertyType::PAYMENT_METHOD) {
                            $orderPaymentMethodId = (int)$orderProperty['value'];
                            break;
                        }
                    }
                    if ($orderPaymentMethodId === $paymentMethodId) {
                        $this->processOrder($order);
                    }

                }
                if ($orderResponse->isLastPage()) {
                    break;
                }
                $page++;
            }

        });
    }

    //would need auth helper if public
    protected function processOrder($order): void
    {
        if((int)$order['typeId'] !== OrderType::TYPE_SALES_ORDER){
            $this->log(__CLASS__, __METHOD__, 'notSalesOrder', '', [$order]);
            return;
        }
        $this->log(__CLASS__, __METHOD__, 'amazonPayOrder', '', [$order]);
        $orderTransactions = $this->transactionHelper->getOrderTransactions((int)$order['id']);
        if (!empty($orderTransactions)) {
            //already matched
            return;
        }

        $chargePermissionId = $this->getChargePermissionId($order);



        if ($chargePermissionId) {
            $existingChargePermissionTransaction = $this->transactionHelper->getTransaction($chargePermissionId, Transaction::TRANSACTION_TYPE_CHARGE_PERMISSION);
            if($existingChargePermissionTransaction && $existingChargePermissionTransaction->order){
                $this->log(__CLASS__, __METHOD__, 'alreadyMatched', '', ['chargePermissionId' => $chargePermissionId, 'order' => $order]);
                return;
            }
            $this->executeMatching((int)$order['id'], $chargePermissionId);

        }
    }

    public function getChargePermissionId($order): ?string
    {
        $candidates = [];
        $orderAmount = $order['amounts'][0]['invoiceTotal'] - $order['amounts'][0]['giftCardAmount'];

        /** @var OrderProperty $property */
        foreach ($order['properties'] as $property) {
            $candidates[] = (string)$property['value'];
        }
        $this->log(__CLASS__, __METHOD__, 'externalMatching_stringCandidates', '', [$candidates]);
        foreach ($candidates as $candidate) {
            if ($chargePermissionId = TransactionHelper::findAmazonPayTransactionIdInString($candidate)) {
                $chargePermissionFromApi = $this->apiHelper->getChargePermission($chargePermissionId);
                $chargePermissionAmount = $chargePermissionFromApi->limits->amountLimit->amount;
                if (number_format($chargePermissionAmount, 2) === number_format($orderAmount, 2)) {
                    $this->log(__CLASS__, __METHOD__, 'stringBasedMatch', '', [
                        'chargePermission' => $chargePermissionFromApi,
                        'order' => $order,
                        'chargePermissionAmount' => $chargePermissionAmount,
                        'orderAmount' => $orderAmount,
                    ]);
                    return $chargePermissionId;
                } else {
                    $this->log(__CLASS__, __METHOD__, 'amountMismatch', '', [
                        'chargePermission' => $chargePermissionFromApi,
                        'order' => $order,
                        'chargePermissionAmount' => $chargePermissionAmount,
                        'orderAmount' => $orderAmount,
                    ]);
                }
            }
        }

        $chargePermissionCandidates = $this->transactionHelper->getChargePermissionsByAmountAndTime($orderAmount, $order['createdAt']);

        $chargePermissionCandidates = array_filter($chargePermissionCandidates, function (Transaction $chargePermission) {
            return empty($chargePermission->order);
        });

        $orderEmailAddresses = $this->getEmailAddressesFromOrder($order);
        $this->log(__CLASS__, __METHOD__, 'hotCandidates', '', ['candidates' => $chargePermissionCandidates, 'orderEmailAddresses' => $orderEmailAddresses]);
        $chargePermissionCandidates = array_values(
            array_filter($chargePermissionCandidates, function (Transaction $chargePermission) use ($orderEmailAddresses, $order) {
                $chargePermissionFromApi = $this->apiHelper->getChargePermission($chargePermission->reference);
                return in_array(strtolower($chargePermissionFromApi->buyer->email), $orderEmailAddresses) || $this->doAddressesMatch($order, $chargePermissionFromApi);
            })
        );
        $this->log(__CLASS__, __METHOD__, 'finalCandidates', '', [$chargePermissionCandidates]);
        if (count($chargePermissionCandidates) !== 1) {
            $this->log(__CLASS__, __METHOD__, 'failed', '', ['candidates' => $chargePermissionCandidates, 'order' => $order]);
            return null;
        }
        return $chargePermissionCandidates[0]->reference;
    }

    /**
     * @param Order $order
     * @param ChargePermission $chargePermission
     * @return bool
     */
    protected function doAddressesMatch($order, $chargePermission): bool
    {
        $chargePermissionAddress = $chargePermission->shippingAddress;
        $chargePermissionAddressString = $chargePermissionAddress->name . ' ' . $chargePermissionAddress->city . ' ' . $chargePermissionAddress->postalCode;
        $orderAddress = $this->getShippingAddressArray($order);

        $orderAddressString = $orderAddress['name2'] . ' ' . $orderAddress['name3'] . ' ' . $orderAddress['town'] . ' ' . $orderAddress['postalCode'];

        $regex = '/[^a-z0-9 ]/i';
        $chargePermissionAddressString = preg_replace($regex, '', $chargePermissionAddressString);
        $orderAddressString = preg_replace($regex, '', $orderAddressString);

        $chargePermissionAddressParts = explode(' ', $chargePermissionAddressString);
        $orderAddressParts = explode(' ', $orderAddressString);

        $diff1 = array_diff($chargePermissionAddressParts, $orderAddressParts);
        $diff2 = array_diff($orderAddressParts, $chargePermissionAddressParts);

        $differenceNumber = count($diff1) + count($diff2);
        $originalNumber = count($chargePermissionAddressParts) + count($orderAddressParts);
        $differencePercentage = $differenceNumber / $originalNumber;
        $this->log(__CLASS__, __METHOD__, 'addressMatch', '', [
            'chargePermissionAddress' => $chargePermissionAddress,
            'orderAddress' => $orderAddress,
            'chargePermissionAddressString' => $chargePermissionAddressString,
            'orderAddressString' => $orderAddressString,
            'diff1' => $diff1,
            'diff2' => $diff2,
            'differenceNumber' => $differenceNumber,
            'originalNumber' => $originalNumber,
            'differencePercentage' => $differencePercentage,
        ]);
        return $differencePercentage <= 0.25;
    }

    protected function getShippingAddressArray($order): ?array
    {
        $shippingAddress = null;
        foreach ($order['addressRelations'] as $addressRelation) {
            if ($addressRelation['typeId'] == AddressRelationType::DELIVERY_ADDRESS) {
                $shippingAddressId = (int)$addressRelation['addressId'];
                break;
            }
        }

        if (empty($shippingAddressId)) {
            return null;
        }

        foreach ($order['addresses'] as $address) {
            if ((int)$address['id'] === $shippingAddressId) {
                $shippingAddress = $address;
                break;
            }
        }
        return $shippingAddress;
    }

    protected function getEmailAddressesFromOrder($order): array
    {
        if (empty($order['addresses'])) {
            return [];
        }
        $emailAddresses = [];
        foreach ($order['addresses'] as $address) {
            foreach ($address['options'] as $option) {
                if ((int)$option['typeId'] === (int)AddressOption::TYPE_EMAIL) {
                    $emailAddresses[] = strtolower($option['value']);
                }
            }
        }
        return $emailAddresses;
    }

    public function isAmazonPayTransactionId($transactionId)
    {
        if (preg_match(TransactionHelper::TRANSACTION_ID_PATTERN, $transactionId)) {
            return true;
        }
    }

    //would need auth helper if public
    protected function executeMatching(int $orderId, string $chargePermissionId): void
    {
        $this->log(__CLASS__, __METHOD__, 'start', '', ['orderId' => $orderId]);
        /** @var OrderHelper $orderHelper */
        $orderHelper = pluginApp(OrderHelper::class);
        $order = $orderHelper->getOrder($orderId);
        $this->log(__CLASS__, __METHOD__, 'data', '', [$order, $chargePermissionId]);
        $chargePermissionFromApi = $this->apiHelper->getChargePermission($chargePermissionId);
        $chargePermissionTransaction = $this->transactionHelper->persistTransaction($chargePermissionFromApi, Transaction::TRANSACTION_TYPE_CHARGE_PERMISSION, $orderId);

        $payment = $orderHelper->createPaymentObject(
            $chargePermissionTransaction->amount,
            Payment::STATUS_APPROVED,
            $chargePermissionTransaction->reference,
            'Matched from external order',
            $chargePermissionFromApi->creationTimestamp,
            Payment::PAYMENT_TYPE_CREDIT,
            Payment::TRANSACTION_TYPE_PROVISIONAL_POSTING,
            $order->amount->currency
        );

        $orderHelper->assignPlentyPaymentToPlentyOrder($payment, $order);

        $transactions = $this->transactionHelper->getAllTransactionsForChargePermissionId($chargePermissionId);
        foreach ($transactions as $transaction) {
            if ($transaction->type === Transaction::TRANSACTION_TYPE_CHARGE) {
                $chargeFromApi = $this->apiHelper->getCharge($transaction->reference);
                $this->transactionHelper->updateCharge($chargeFromApi, $orderId);
            } elseif ($transaction->type === Transaction::TRANSACTION_TYPE_REFUND) {
                $refundFromApi = $this->apiHelper->getRefund($transaction->reference);
                $this->transactionHelper->updateRefund($refundFromApi, $orderId);
            }
        }
    }

}