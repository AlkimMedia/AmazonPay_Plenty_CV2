<?php

namespace AmazonPayCheckout\Helpers;

use AmazonPayCheckout\Struct\Charge;
use AmazonPayCheckout\Struct\ChargePermission;
use AmazonPayCheckout\Struct\CheckoutSession;
use AmazonPayCheckout\Struct\StatusDetails;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;

class ApiHelper
{
    use LoggingTrait;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    public function __construct(ConfigHelper $configHelper)
    {

        $this->configHelper = $configHelper;
    }


    /**
     * @param $checkoutSessionId
     *
     * @return CheckoutSession
     */
    public function getCheckoutSession($checkoutSessionId)
    {
        $response = $this->call('getCheckoutSession', [
            'checkout_session_id' => $checkoutSessionId,
        ]);
        return $response->response->checkoutSession;
    }

    /**
     * @param string $checkoutSessionId
     * @param float $amount
     * @param string $currency
     *
     * @return CheckoutSession
     */
    public function updateCheckoutSessionBeforeCheckout(string $checkoutSessionId, float $amount, string $currency = 'EUR')
    {
        $response = $this->call('updateCheckoutSessionBeforeCheckout', [
            'checkout_session_id' => $checkoutSessionId,
            'checkout_result_return_url' => $this->configHelper->getCheckoutResultReturnUrl(),
            'authorization_mode' => $this->configHelper->getConfigurationValue('authorizationMode'),
            'amount' => $amount,
            'currency' => $currency,
        ]);
        return $response->response->checkoutSession;

    }

    /**
     * @param string $checkoutSessionId
     * @param float $amount
     * @param string $currency
     *
     * @return CheckoutSession
     * @throws Exception
     */
    public function completeCheckoutSession(string $checkoutSessionId, float $amount, string $currency = 'EUR')
    {
        $response = $this->call('completeCheckoutSession', [
            'checkout_session_id' => $checkoutSessionId,
            'amount' => $amount,
            'currency' => $currency,
        ]);
        if (isset($response->exception)) {
            throw new Exception($response->exception->message);
        }
        return $response->response->checkoutSession;

    }

    /**
     * @param string $chargePermissionId
     * @param int $orderId
     *
     * @return ChargePermission
     * @throws Exception
     */
    public function updateChargePermission(string $chargePermissionId, int $orderId)
    {
        $response = $this->call('updateChargePermission', [
            'charge_permission_id' => $chargePermissionId,
            'order_id' => $orderId,
        ]);
        if (isset($response->exception)) {
            throw new Exception($response->exception->message);
        }
        return $response->response->chargePermission;

    }

    /**
     * @param string $chargePermissionId
     *
     * @return ChargePermission
     * @throws Exception
     */
    public function closeChargePermission(string $chargePermissionId)
    {
        $response = $this->call('closeChargePermission', [
            'charge_permission_id' => $chargePermissionId,
        ]);
        if (isset($response->exception)) {
            throw new Exception($response->exception->message);
        }
        return $response->response->chargePermission;

    }

    public function generateButtonSignature($payload)
    {
        /** @var SessionStorageRepositoryContract $sessionStorageRepository */
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);
        $storageKey = 'amazonPayButtonSignature' . md5($payload);
        if (!($signature = $sessionStorageRepository->getSessionValue($storageKey))) {
            $response = $this->call('generateButtonSignature', [
                'payload' => $payload,
            ]);
            $signature = $response->response->signature;
            $sessionStorageRepository->setSessionValue($storageKey, $signature);
        }
        return $signature;
    }

    /**
     * @param string $chargePermissionId
     * @return ChargePermission
     */
    public function getChargePermission(string $chargePermissionId)
    {
        $response = $this->call('getChargePermission', [
            'charge_permission_id' => $chargePermissionId,
        ]);
        return $response->response->charge_permission;
    }

    /**
     * @param $action
     * @param $parameters
     *
     * @return \stdClass
     */
    public function call($action, $parameters)
    {
        /** @var \Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract $sdkClient */
        $sdkClient = pluginApp(LibraryCallContract::class);
        $startTime = microtime(true);
        $result = $sdkClient->call(
            'AmazonPayCheckout::sdk_client',
            array_merge(
                [
                    'config' => $this->configHelper->getClientConfiguration(),
                    'action' => $action,
                ]
                , $parameters
            )
        );
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        if ($action !== 'generateButtonSignature') {
            $this->log(__CLASS__, __METHOD__, 'result', '', [
                'startTime' => $startTime,
                'endTime' => $endTime,
                'duration' => $duration,
                'action' => $action,
                'parameters' => $parameters,
                'result' => $result,
            ]);
        }

        return json_decode(json_encode($result));
    }

    /**
     * @param string $chargeId
     * @return Charge
     */
    public function getCharge(string $chargeId)
    {
        $response = $this->call('getCharge', [
            'charge_id' => $chargeId,
        ]);
        return $response->response->charge;
    }

    /**
     * @param string $refundId
     * @return Charge
     */
    public function getRefund(string $refundId)
    {
        $response = $this->call('getRefund', [
            'refund_id' => $refundId,
        ]);
        return $response->response->refund;
    }

    /**
     * @param string $buyerToken
     *
     * @return \AmazonPayCheckout\Struct\Buyer
     */
    public function getBuyer(string $buyerToken)
    {
        $response = $this->call('getBuyer', [
            'buyer_token' => $buyerToken,
        ]);
        return $response->response->buyer;
    }

    public function capture(string $chargeId, $amount = null)
    {
        $this->log(__CLASS__, __METHOD__, 'start', '', ['chargeId' => $chargeId, 'amount' => $amount]);
        try {
            $originalCharge = $this->getCharge($chargeId);
            if ($originalCharge->statusDetails->state === StatusDetails::AUTHORIZED) {
                $response = $this->call('capture', [
                    'charge_id' => $chargeId,
                    'amount' => $amount,
                ]);
                if (!empty($response) && !empty($response->response->charge)) {
                    return $response->response->charge;
                }
            }
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'fail', '', ['chargeId' => $chargeId, 'amount' => $amount, 'msg' => $e->getMessage()], true);
        }
        return null;
    }

    public function refund(string $chargeId, $amount = null, $orderId = null)
    {
        $this->log(__CLASS__, __METHOD__, 'start', '', ['chargeId' => $chargeId, 'amount' => $amount]);
        try {
            $originalCharge = $this->getCharge($chargeId);
            if ($originalCharge->statusDetails->state !== StatusDetails::CAPTURED) {
                throw new Exception('refund only allowed for captured charges - this charge has the status '.$originalCharge->statusDetails->state);
            }
            $response = $this->call('refund', [
                'charge_id' => $chargeId,
                'amount' => $amount,
            ]);
            $this->log(__CLASS__, __METHOD__, 'response', '', [$response]);
            if (!empty($response) && !empty($response->response->refund)) {
                /** @var TransactionHelper $transactionHelper */
                $transactionHelper = pluginApp(TransactionHelper::class);
                $transactionHelper->updateRefund($response->response->refund, $orderId);
                return $response->response->refund;
            }

        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'fail', '', ['chargeId' => $chargeId, 'amount' => $amount, 'msg' => $e->getMessage()], true);
        }
        return null;
    }

    /**
     * @return string
     */
    public function createCheckoutSession(): string
    {
        /** @var CheckoutHelper $checkoutHelper */
        $checkoutHelper = pluginApp(CheckoutHelper::class);
        $response = $this->call('createCheckoutSession', [
            'store_name' => $this->configHelper->getStoreName(),
            'custom_information' => $this->configHelper->getCustomInformationString(),
            'platform_id' => $this->configHelper->getPlatformId(),
            'store_id' => $this->configHelper->getConfigurationValue('storeId'),
            'review_return_url' => $this->configHelper->getCheckoutReviewReturnUrl(),
            'allowed_countries' => $checkoutHelper->getShippingCountries(),
        ]);
        return (string)$response->response->checkoutSessionId;
    }


}
