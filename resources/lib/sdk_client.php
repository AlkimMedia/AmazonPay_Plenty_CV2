<?php

/*
 *
 * required parameters:
 *  config
 *  store_name (create session)
 *  custom_information (create session)
 *  review_return_url (create session)
 *  platform_id (create session)
 *  store_id (create session)
 */

use AmazonPayApiSdkExtension\Client\Client;
use AmazonPayApiSdkExtension\Struct\CaptureAmount;
use AmazonPayApiSdkExtension\Struct\Charge;
use AmazonPayApiSdkExtension\Struct\ChargePermission;
use AmazonPayApiSdkExtension\Struct\CheckoutSession;
use AmazonPayApiSdkExtension\Struct\DeliverySpecifications;
use AmazonPayApiSdkExtension\Struct\MerchantMetadata;
use AmazonPayApiSdkExtension\Struct\PaymentDetails;
use AmazonPayApiSdkExtension\Struct\Price;
use AmazonPayApiSdkExtension\Struct\Refund;
use AmazonPayApiSdkExtension\Struct\RefundAmount;
use AmazonPayApiSdkExtension\Struct\WebCheckoutDetails;

class ApiHelperSdk
{

    private static $client;

    /**
     * @return \AmazonPayApiSdkExtension\Struct\CheckoutSession|array|string
     * @throws \Exception
     */
    public function createCheckoutSession()
    {
        $merchantData = (new MerchantMetadata())
            ->setMerchantStoreName(SdkRestApi::getParam('store_name'))
            ->setCustomInformation(SdkRestApi::getParam('custom_information'));

        $webCheckoutDetails = new WebCheckoutDetails();
        $webCheckoutDetails->setCheckoutReviewReturnUrl(SdkRestApi::getParam('review_return_url'));

        if (SdkRestApi::getParam('allowed_countries')) {
            $countries           = array_fill_keys(SdkRestApi::getParam('allowed_countries'), new \stdClass());
            $addressRestrictions = new AmazonPayApiSdkExtension\Struct\AddressRestrictions();
            $addressRestrictions->setType('Allowed')
                ->setRestrictions($countries);

            $deliverySpecifications = new DeliverySpecifications();
            $deliverySpecifications->setAddressRestrictions($addressRestrictions);
        }
        $checkoutSession = new CheckoutSession();
        $checkoutSession->setMerchantMetadata($merchantData)
            ->setWebCheckoutDetails($webCheckoutDetails)
            ->setStoreId(SdkRestApi::getParam('store_id'));
        if (!empty($deliverySpecifications)) {
            $checkoutSession->setDeliverySpecifications($deliverySpecifications);
        }
        if (SdkRestApi::getParam('platform_id')) {
            $checkoutSession->setPlatformId(SdkRestApi::getParam('platform_id'));
        }

        return $this->getClient()->createCheckoutSession($checkoutSession);
    }

    public function updateCheckoutSession($checkoutSessionId, $canHandlePending = false)
    {
        $checkoutSessionUpdate = new CheckoutSession();
        $webCheckoutDetails    = new WebCheckoutDetails();
        $webCheckoutDetails->setCheckoutResultReturnUrl(SdkRestApi::getParam('checkout_result_return_url'));

        $paymentDetails = new PaymentDetails();
        $paymentDetails
            ->setPaymentIntent('Authorize')
            ->setCanHandlePendingAuthorization($canHandlePending)
            ->setChargeAmount(new Price(['amount' => SdkRestApi::getParam('amount'), 'currencyCode' => SdkRestApi::getParam('currency')]));

        $checkoutSessionUpdate
            ->setWebCheckoutDetails($webCheckoutDetails)
            ->setPaymentDetails($paymentDetails);

        $updatedCheckoutSession = $this->getClient()->updateCheckoutSession($checkoutSessionId, $checkoutSessionUpdate);
        return $updatedCheckoutSession;
    }

    public function updateChargePermission($chargePermissionId, $orderId)
    {
        $merchantMeta = (new MerchantMetadata())
            ->setMerchantReferenceId($orderId);

        $chargePermissionUpdate = (new ChargePermission())
            ->setMerchantMetadata($merchantMeta);
        $chargePermission       = $this->getClient()->updateChargePermission($chargePermissionId, $chargePermissionUpdate);
        return $chargePermission;
    }

    public function closeChargePermission($chargePermissionId)
    {
        $chargePermission = $this->getClient()->closeChargePermission($chargePermissionId, ['closureReason' => '-']);
        return $chargePermission;
    }

    public function completeCheckoutSession($checkoutSessionId)
    {
        $paymentDetails = new PaymentDetails();
        $paymentDetails->setChargeAmount(new Price(['amount' => SdkRestApi::getParam('amount'), 'currencyCode' => SdkRestApi::getParam('currency')]));
        $checkoutSession = $this->getClient()->completeCheckoutSession($checkoutSessionId, $paymentDetails);
        return $checkoutSession;
    }

    public function capture($chargeId, $amount = null)
    {
        $originalCharge = $this->getClient()->getCharge($chargeId);
        $captureCharge  = new Charge();
        $captureAmount  = new CaptureAmount($originalCharge->getChargeAmount()->toArray());
        if (!empty($amount)) {
            $captureAmount->setAmount($amount);
        }
        $captureCharge->setCaptureAmount($captureAmount);
        $captureCharge = $this->getClient()->captureCharge($originalCharge->getChargeId(), $captureCharge);
        return $captureCharge;

    }

    public function refund($chargeId, $amount = null)
    {
        $originalCharge = $this->getClient()->getCharge($chargeId);
        $refund         = new Refund();
        $refundAmount   = new RefundAmount($originalCharge->getCaptureAmount()->toArray());
        if (!empty($amount)) {
            $refundAmount->setAmount($amount);
        } elseif ($originalCharge->getRefundedAmount()->getAmount()) {
            $refundAmount->setAmount($refundAmount->getAmount() - $originalCharge->getRefundedAmount()->getAmount());
        }

        if ($refundAmount->getAmount() > 0) {
            $refund->setRefundAmount($refundAmount);
            $refund->setChargeId($chargeId);
            $refund = $this->getClient()->createRefund($refund);
            return $refund;
        } else {
            throw new Exception('refund amount must be greater than 0');
        }

    }

    /**
     * @param bool $forceSandbox
     *
     * @return \AmazonPayApiSdkExtension\Client\Client
     * @throws \Exception
     */
    public function getClient($forceSandbox = false)
    {
        if (!isset(self::$client)) {

            $configuration = SdkRestApi::getParam('config');
            if ($forceSandbox) {
                $configuration['sandbox'] = true;
            }
            self::$client = new Client($configuration);
        }

        return self::$client;
    }

    public function getHeaders()
    {
        return ['x-amz-pay-Idempotency-Key' => uniqid()];
    }

}

try {
    $action             = SdkRestApi::getParam('action');
    $apiHelper          = new ApiHelperSdk();
    $startTime          = microtime(true);
    $return['response'] = [];
    switch ($action) {
        case 'createCheckoutSession':
            $return['response']['checkoutSessionId'] = $apiHelper->createCheckoutSession()->getCheckoutSessionId();
            break;
        case 'getCheckoutSession':
            $return['response']['checkoutSession'] = $apiHelper->getClient()->getCheckoutSession(SdkRestApi::getParam('checkout_session_id'))->toArray();
            break;
        case 'getCharge':
            $return['response']['charge'] = $apiHelper->getClient()->getCharge(SdkRestApi::getParam('charge_id'))->toArray();
            break;
        case 'getChargePermission':
            $return['response']['charge_permission'] = $apiHelper->getClient()->getChargePermission(SdkRestApi::getParam('charge_permission_id'))->toArray();
            break;
        case 'getRefund':
            $return['response']['refund'] = $apiHelper->getClient()->getRefund(SdkRestApi::getParam('refund_id'))->toArray();
            break;
        case 'updateCheckoutSessionBeforeCheckout':
            $return['response']['checkoutSession'] = $apiHelper->updateCheckoutSession(SdkRestApi::getParam('checkout_session_id'), SdkRestApi::getParam('authorization_mode') !== 'fast_auth')->toArray();
            break;
        case 'completeCheckoutSession':
            $return['response']['checkoutSession'] = $apiHelper->completeCheckoutSession(SdkRestApi::getParam('checkout_session_id'))->toArray();
            break;
        case 'updateChargePermission':
            $return['response']['chargePermission'] = $apiHelper->updateChargePermission(SdkRestApi::getParam('charge_permission_id'), SdkRestApi::getParam('order_id'))->toArray();
            break;
        case 'closeChargePermission':
            $return['response']['chargePermission'] = $apiHelper->closeChargePermission(SdkRestApi::getParam('charge_permission_id'))->toArray();
            break;
        case 'generateButtonSignature':
            $return['response']['signature'] = $apiHelper->getClient()->generateButtonSignature(SdkRestApi::getParam('payload'));
            break;
        case 'getBuyer':
            $return['response']['buyer'] = $apiHelper->getClient()->getBuyer(SdkRestApi::getParam('buyer_token'))->toArray();
            break;
        case 'capture':
            $return['response']['charge'] = $apiHelper->capture(SdkRestApi::getParam('charge_id'), SdkRestApi::getParam('amount'))->toArray();
            break;
        case 'refund':
            $return['response']['refund'] = $apiHelper->refund(SdkRestApi::getParam('charge_id'), SdkRestApi::getParam('amount'))->toArray();
            break;
    }
    $endTime                 = microtime(true);
    $duration                = $endTime - $startTime;
    $return["action"]        = $action;
    $return["call_duration"] = $duration;

} catch (Exception $e) {
    $return = [
        'exception' => [
            'object' => $e,
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ],
    ];
}

return $return;