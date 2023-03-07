<?php

namespace AmazonPayCheckout\Helpers;

use AmazonPayCheckout\Models\Transaction;
use AmazonPayCheckout\Struct\StatusDetails;
use AmazonPayCheckout\Traits\LoggingTrait;
use AmazonPayCheckout\Traits\TranslationTrait;
use Exception;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\PaymentMethod\Contracts\FrontendPaymentMethodRepositoryContract;
use Plenty\Modules\Frontend\Services\VatService;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Models\Country;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\Application;

class CheckoutHelper
{
    use LoggingTrait;
    use TranslationTrait;

    public static $sessionStatusCache = [];

    public function getBasket(): Basket
    {
        /** @var BasketRepositoryContract $basketRepository */
        $basketRepository = pluginApp(BasketRepositoryContract::class);
        $basket = $basketRepository->load();

        if (!empty($basket) && !empty($basket->itemSum)) {
            /** @var VatService $vatService */
            $vatService = pluginApp(VatService::class);
            $vats = $vatService->getCurrentTotalVats();

            $order = pluginApp(SessionStorageRepositoryContract::class)->getOrder();
            $isNet = false;
            if (!is_null($order)) {
                $isNet = $order->isNet;
            }
            if (empty($vats) && $isNet) {
                $basket->basketAmount = $basket->basketAmountNet;
            }
        }

        return $basket;
    }

    public function getShippingCountries(): array
    {
        /** @var CountryRepositoryContract $countryRepository */
        $countryRepository = pluginApp(CountryRepositoryContract::class);
        $countryList = $countryRepository->getActiveCountriesList();
        $result = [];
        /** @var Country $country */
        foreach ($countryList as $country) {
            if (in_array($country->isoCode2, ['EA'])) {
                continue;
            }
            $result[] = $country->isoCode2;
        }
        return $result;
    }

    public function resetPaymentMethod()
    {
        /** @var \Plenty\Modules\Frontend\PaymentMethod\Contracts\FrontendPaymentMethodRepositoryContract $frontendPaymentMethodRepository */
        $frontendPaymentMethodRepository = pluginApp(FrontendPaymentMethodRepositoryContract::class);
        $paymentMethods = $frontendPaymentMethodRepository->getCurrentPaymentMethodsList();

        /** @var PaymentMethodHelper $paymentMethodHelper */
        $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);

        /** @var Checkout $checkout */
        $checkout = pluginApp(Checkout::class);

        $amazonPayPaymentMethod = $paymentMethodHelper->createMopIfNotExistsAndReturnId();
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->id != $amazonPayPaymentMethod) {
                $checkout->setPaymentMethodId($paymentMethod->id);
                break;
            }
        }
    }

    public function executePayment(Order $order, string $checkoutSessionId)
    {
        $this->log(__CLASS__, __METHOD__, 'start', '', ['order' => $order, 'session' => $checkoutSessionId]);

        /** @var ApiHelper $apiHelper */
        $apiHelper = pluginApp(ApiHelper::class);
        $checkoutSession = $apiHelper->getCheckoutSession($checkoutSessionId);

        if ($checkoutSession->statusDetails->state === StatusDetails::OPEN) {
            try {
                $checkoutSession = $apiHelper->completeCheckoutSession($checkoutSessionId, $order->amounts[0]->invoiceTotal, $order->amounts[0]->currency);

                if ($checkoutSession->statusDetails->state === StatusDetails::COMPLETED) {
                    $this->updateChargePermissionWithPlentyOrderId($checkoutSession->chargePermissionId, (int)$order->id);

                    /** @var \AmazonPayCheckout\Helpers\OrderHelper $orderHelper */
                    $orderHelper = pluginApp(OrderHelper::class);

                    $payment = $orderHelper->createPaymentObject(
                        $order->amounts[0]->invoiceTotal,
                        Payment::STATUS_APPROVED,
                        $checkoutSession->chargePermissionId,
                        'Checkout Session Completed',
                        null, Payment::PAYMENT_TYPE_CREDIT,
                        Payment::TRANSACTION_TYPE_PROVISIONAL_POSTING,
                        $order->amounts[0]->currency
                    );

                    $orderHelper->assignPlentyPaymentToPlentyOrder($payment, $order);
                    $orderHelper->setOrderExternalId($order->id, $checkoutSession->chargePermissionId);
                    /** @var \AmazonPayCheckout\Helpers\TransactionHelper $transactionHelper */
                    $transactionHelper = pluginApp(TransactionHelper::class);

                    if ($checkoutSession->chargeId) {
                        $charge = $apiHelper->getCharge($checkoutSession->chargeId);
                        $transactionHelper->updateCharge($charge, $order->id);
                    }

                    $chargePermission = $apiHelper->getChargePermission($checkoutSession->chargePermissionId);
                    $transactionHelper->persistTransaction($chargePermission, Transaction::TRANSACTION_TYPE_CHARGE_PERMISSION, $order->id, $payment->id);

                    if ($checkoutSession->chargeId) {
                        $charge = $apiHelper->getCharge($checkoutSession->chargeId);
                        $transactionHelper->updateCharge($charge, $order->id);
                    }
                }
            } catch (Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'error', '', [$e->getMessage(), $order], true);
            }
        } else {
            $this->log(__CLASS__, __METHOD__, 'wrong_state', '', [$checkoutSession, $order], true);
        }

    }

    public function updateChargePermissionWithPlentyOrderId(string $chargePermissionId, int $orderId)
    {
        try {
            /** @var ApiHelper $apiHelper */
            $apiHelper = pluginApp(ApiHelper::class);
            $response = $apiHelper->updateChargePermission($chargePermissionId, $orderId);
            $this->log(__CLASS__, __METHOD__, 'result', '', [$response]);
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'error', '', [$e->getMessage(), $chargePermissionId, $orderId], true);
        }
    }


    public function setCurrentPaymentMethodId()
    {
        /** @var Checkout $checkout */
        $checkout = pluginApp(Checkout::class);
        /** @var PaymentMethodHelper $paymentMethodHelper */
        $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);

        return $checkout->setPaymentMethodId($paymentMethodHelper->createMopIfNotExistsAndReturnId());
    }

    /**
     * @return \Plenty\Modules\Account\Address\Models\Address|null
     */
    public function getShippingAddress()
    {
        /** @var Checkout $checkout */
        $checkout = pluginApp(Checkout::class);
        $shippingAddressId = $checkout->getCustomerShippingAddressId();
        if ($shippingAddressId) {
            /** @var AddressRepositoryContract $addressRepository */
            $addressRepository = pluginApp(AddressRepositoryContract::class);
            return $addressRepository->findAddressById($shippingAddressId);
        }

        return null;
    }

    public function isCurrentPaymentMethodAmazonPay(): bool
    {
        /** @var PaymentMethodHelper $paymentMethodHelper */
        $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);

        if ($paymentMethodHelper->createMopIfNotExistsAndReturnId() != $this->getCurrentPaymentMethodId()) {
            return false;
        }

        /** @var SessionStorageRepositoryContract $sessionStorageRepository */
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);

        $this->log(__CLASS__, __METHOD__, 'session', '', ['sessionId' => $sessionStorageRepository->getSessionValue('amazonCheckoutSessionId')]);
        if (empty($sessionStorageRepository->getSessionValue('amazonCheckoutSessionId'))) {
            return false;
        }

        return true;
    }

    public function getCurrentPaymentMethodId(): int
    {
        /** @var Checkout $checkout */
        $checkout = pluginApp(Checkout::class);

        return $checkout->getPaymentMethodId();
    }

    public function hasOpenSession(): bool
    {
        /** @var SessionStorageRepositoryContract $sessionStorageRepository */
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);
        $checkoutSessionId = $sessionStorageRepository->getSessionValue('amazonCheckoutSessionId');
        if (empty($checkoutSessionId)) {
            return false;
        }

        if (!empty(self::$sessionStatusCache[$checkoutSessionId])) {
            return true;
        }
        /** @var ApiHelper $apiHelper */
        $apiHelper = pluginApp(ApiHelper::class);

        $checkoutSession = $apiHelper->getCheckoutSession($checkoutSessionId);
        if ($checkoutSession->statusDetails->state === StatusDetails::OPEN) {
            self::$sessionStatusCache[$checkoutSessionId] = true;
            return true;
        }
        return false;
    }

    /**
     * @return \AmazonPayCheckout\Struct\CheckoutSession|null
     */
    public function getOpenSession()
    {
        /** @var SessionStorageRepositoryContract $sessionStorageRepository */
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);
        $checkoutSessionId = $sessionStorageRepository->getSessionValue('amazonCheckoutSessionId');
        if (empty($checkoutSessionId)) {
            return null;
        }

        /** @var ApiHelper $apiHelper */
        $apiHelper = pluginApp(ApiHelper::class);

        $checkoutSession = $apiHelper->getCheckoutSession($checkoutSessionId);
        if ($checkoutSession->statusDetails->state === StatusDetails::OPEN) {
            return $checkoutSession;
        }
        return null;
    }

    public function scheduleNotification($message, $type = 'error')
    {
        $notification = [
            'message' => $message,
            'code' => 0,
            'stackTrace' => [],
        ];
        $notifications[$type] = $notification;
        $this->setToSession('notifications', json_encode($notifications));
    }

    public function setToSession($key, $value)
    {
        /** @var FrontendSessionStorageFactoryContract $session */
        $session = pluginApp(FrontendSessionStorageFactoryContract::class);
        $session->getPlugin()->setValue($key, $value);
    }

    /**
     * @param Order|null $existingOrder
     * @return array
     */
    public function getCheckoutSessionDataForDirectCheckout($existingOrder = null): array
    {
        /** @var AddressRepositoryContract $addressRepository */
        $addressRepository = pluginApp(AddressRepositoryContract::class);
        /** @var CheckoutHelper $checkoutHelper */
        $checkoutHelper = pluginApp(CheckoutHelper::class);
        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);
        /** @var Checkout $checkout */
        $checkout = pluginApp(Checkout::class);
        $basket = $checkoutHelper->getBasket();
        if ($existingOrder) {
            $this->log(__CLASS__, __METHOD__, 'addressRelations ', '', [$existingOrder->addressRelations]);
            $shippingAddressId = null;
            $billingAddressId = null;

            foreach ($existingOrder->addressRelations as $addressRelation) {
                $this->log(__CLASS__, __METHOD__, 'addressRelation ', '', [$addressRelation, AddressRelationType::BILLING_ADDRESS, AddressRelationType::DELIVERY_ADDRESS]);
                if ($addressRelation->typeId == AddressRelationType::BILLING_ADDRESS) {
                    $billingAddressId = $addressRelation->addressId;
                } elseif ($addressRelation->typeId == AddressRelationType::DELIVERY_ADDRESS) {
                    $shippingAddressId = $addressRelation->addressId;
                }
            }
            $this->log(__CLASS__, __METHOD__, 'addressRelationResult ', '', [$shippingAddressId, $billingAddressId]);
            $addressId = $shippingAddressId ?: $billingAddressId;

        } else {
            $addressId = $checkout->getCustomerShippingAddressId() ?? $checkout->getCustomerInvoiceAddressId();
        }

        $this->log(__CLASS__, __METHOD__, 'addressIds', '', ['addressId' => $addressId]);
        $shippingAddress = $addressRepository->findAddressById($addressId);
        /** @var CountryRepositoryContract $countryRepository */
        $countryRepository = pluginApp(CountryRepositoryContract::class);
        $country = $countryRepository->getCountryById($shippingAddress->countryId);

        $this->log(__CLASS__, __METHOD__, 'shippingAddress', '', ['address' => $shippingAddress, 'country' => $country]);

        //TODO leave address empty if pay only
        return [
            'webCheckoutDetails' => [
                'checkoutResultReturnUrl' => $existingOrder === null ? $configHelper->getCheckoutResultReturnUrl() : $configHelper->getPayExistingOrderCheckoutResultReturnUrl($existingOrder->id),
                'checkoutCancelUrl' => $configHelper->getShopCheckoutUrl(),
                'checkoutMode' => 'ProcessOrder',
            ],
            'storeId' => $configHelper->getConfigurationValue('storeId'),
            'scopes' => ['name', 'email', 'phoneNumber', 'billingAddress'],
            'paymentDetails' => [
                'paymentIntent' => 'Authorize',
                'canHandlePendingAuthorization' => $configHelper->getConfigurationValue('authorizationMode') !== 'fast_auth',
                'chargeAmount' => [
                    'amount' => $existingOrder ? $existingOrder->amounts[0]->invoiceTotal : $basket->basketAmount,
                    'currencyCode' => $existingOrder ? $existingOrder->amounts[0]->currency : $basket->currency,
                ],
            ],
            'merchantMetadata' => [
                'merchantStoreName' => $configHelper->getStoreName(),
            ],
            'addressDetails' => [
                'name' => trim($shippingAddress->name1 . ' ' . $shippingAddress->name2 . ' ' . $shippingAddress->name3),
                'addressLine1' => trim($shippingAddress->address1 . ' ' . $shippingAddress->address2 . ' ' . $shippingAddress->address3 . ' ' . $shippingAddress->address4),
                'city' => $shippingAddress->town,
                'postalCode' => $shippingAddress->postalCode,
                'countryCode' => $country->isoCode2,
                'phoneNumber' => '00000',
            ],

        ];
    }

    public function hasAvailableShippingMethod(): bool
    {
        //TODO Logging here and in child methods (getBasket) necessary?
        $params = [
            'countryId' => $this->getShippingCountryId(),
            'webstoreId' => pluginApp(Application::class)->getWebstoreId(),
        ];

        $accountContactClassId = pluginApp(FrontendSessionStorageFactoryContract::class)->getCustomer()->accountContactClassId;
        /** @var ParcelServicePresetRepositoryContract $repo */
        $repo = pluginApp(ParcelServicePresetRepositoryContract::class);
        $basket = $this->getBasket();

        /** @var PaymentMethodHelper $paymentMethodHelper */
        $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);
        $paymentMethodId = $paymentMethodHelper->createMopIfNotExistsAndReturnId();
        $shippingMethods = $repo->getLastWeightedPresetCombinations($basket, $accountContactClassId, $params);
        foreach ($shippingMethods as $shippingMethod) {
            $excludedMethods = [];
            if (!empty($shippingMethod->excludedPaymentMethodIds)) {
                $excludedMethods = $shippingMethod->excludedPaymentMethodIds;
            } elseif (!empty($shippingMethod['excludedPaymentMethodIds'])) {
                $excludedMethods = $shippingMethod['excludedPaymentMethodIds'];
            }

            if (empty($excludedMethods) || !in_array($paymentMethodId, $excludedMethods)) {
                return true;
            }
        }
        return false;
    }

    public function getShippingCountryId(): int
    {
        /** @var Checkout $checkout */
        $checkout = pluginApp(Checkout::class);
        return $checkout->getShippingCountryId();
    }


}