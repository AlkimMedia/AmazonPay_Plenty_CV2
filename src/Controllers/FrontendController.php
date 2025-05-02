<?php

namespace AmazonPayCheckout\Controllers;

use AmazonPayCheckout\Helpers\AccountHelper;
use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Helpers\CheckoutHelper;
use AmazonPayCheckout\Helpers\ConfigHelper;
use AmazonPayCheckout\Helpers\OrderHelper;
use AmazonPayCheckout\Helpers\PaymentMethodHelper;
use AmazonPayCheckout\Struct\StatusDetails;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use IO\Extensions\Constants\ShopUrls;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\PaymentMethod\Contracts\FrontendPaymentMethodRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Templates\Twig;

class FrontendController extends Controller
{
    use LoggingTrait;

    /**
     * @var Response
     */
    public $response;
    /**
     * @var Request
     */
    public $request;
    /**
     * @var Twig
     */
    private $twig;

    public function __construct(Response $response, Request $request, Twig $twig)
    {
        parent::__construct();
        $this->response = $response;
        $this->request = $request;
        $this->twig = $twig;
    }

    public function processReturn()
    {
        $this->log(__CLASS__, __METHOD__, 'start', '👩 process return');

        $checkoutSessionId = $this->request->get('amazonCheckoutSessionId');

        if (empty($checkoutSessionId)) {
            return $this->response->redirectTo($this->getShopBasketUrl()); //TODO error msg+log
        }
        $apiHelper = pluginApp(ApiHelper::class);
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);

        $sessionStorageRepository->setSessionValue('amazonCheckoutSessionId', $checkoutSessionId);
        $checkoutSession = $apiHelper->getCheckoutSession($checkoutSessionId);

        if ($checkoutSession->statusDetails->state !== StatusDetails::OPEN) {
            return $this->response->redirectTo($this->getShopBasketUrl()); //TODO error msg+log
        }

        $accountHelper = pluginApp(AccountHelper::class);
        $checkoutHelper = pluginApp(CheckoutHelper::class);

        if ($accountHelper->isLoggedIn()) {
            $accountHelper->setAddresses($checkoutSession);
        } else {
            $accountHelper->createGuestSession($checkoutSession);
        }
        $checkoutHelper->setCurrentPaymentMethodId();

        return $this->response->redirectTo($this->getShopCheckoutUrl());
    }

    public function checkoutStart()
    {
        $this->log(__CLASS__, __METHOD__, 'start', '👩 checkout start');
        $apiHelper = pluginApp(ApiHelper::class);
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);
        $checkoutHelper = pluginApp(CheckoutHelper::class);

        try {
            $checkoutSessionId = $sessionStorageRepository->getSessionValue('amazonCheckoutSessionId');

            if (!$checkoutSessionId) {
                throw new Exception('empty');
            }
            $checkoutSession = $apiHelper->getCheckoutSession($checkoutSessionId);
            if (!$checkoutSession->statusDetails || $checkoutSession->statusDetails->state !== StatusDetails::OPEN) {
                throw new Exception('no valid checkout session');
            }
            try {
                $checkoutHelper->validateShippingAddress($checkoutSession, $checkoutHelper->getCurrentCheckoutShippingAddress(pluginApp(Checkout::class)));
            } catch (Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'addressValidationFailed', '', [$e->getMessage()]);
                throw $e;
            }
        } catch (Exception $e) {
            return $this->_continueWithAdditionalPaymentButton($apiHelper, $checkoutHelper);
        }

        if (empty($checkoutSessionId)) {
            $checkoutHelper->scheduleNotification($checkoutHelper->getTranslation('AmazonPay.pleaseSelectPaymentMethod'));
            return $this->response->redirectTo($this->getShopCheckoutUrl());
        }


        $basket = $checkoutHelper->getBasket();
        $updatedCheckoutSession = $apiHelper->updateCheckoutSessionBeforeCheckout(
            $checkoutSessionId,
            $basket->basketAmount,
            $basket->currency
        );

        if (!empty($updatedCheckoutSession->webCheckoutDetails->amazonPayRedirectUrl)) {
            return $this->response->redirectTo($updatedCheckoutSession->webCheckoutDetails->amazonPayRedirectUrl);
        } else {
            return $this->_cancelCheckoutStart($sessionStorageRepository, $checkoutHelper);
        }
    }

    public function payOrder()
    {
        $this->log(__CLASS__, __METHOD__, 'start', '👩 pay order start');
        $apiHelper = pluginApp(ApiHelper::class);
        $checkoutHelper = pluginApp(CheckoutHelper::class);
        $orderHelper = pluginApp(OrderHelper::class);

        $orderId = (int)$this->request->get('order_id');
        $order = $orderHelper->getOrder($orderId);

        return $this->_continueWithAdditionalPaymentButton($apiHelper, $checkoutHelper, $order);


    }

    public function payOrderProcess()
    {
        $this->log(__CLASS__, __METHOD__, 'start', '👩 pay order process', [$this->request->all()]);
        $apiHelper = pluginApp(ApiHelper::class);
        $checkoutSessionId = $this->request->get('amazonCheckoutSessionId');
        $checkoutSession = $apiHelper->getCheckoutSession($checkoutSessionId);
        $this->log(__CLASS__, __METHOD__, 'checkoutSession', '', [$checkoutSession]);
        if ($checkoutSession->statusDetails->state === StatusDetails::OPEN) {
            $orderId = (int)$this->request->get('order_id');
            $orderHelper = pluginApp(OrderHelper::class);
            $checkoutHelper = pluginApp(CheckoutHelper::class);
            $order = $orderHelper->getOrder($orderId);
            try {
                $checkoutHelper->executePayment($order, $checkoutSessionId);
            } catch (Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'failed', '', [$e->getMessage(), $e->getTraceAsString()]);
                $checkoutHelper->scheduleNotification($checkoutHelper->getTranslation('AmazonPay.executePaymentError'));
            }
        }
        return $this->response->redirectTo($this->getOrderConfirmationUrl($order));
    }


    public function placeOrder()
    {
        $this->log(__CLASS__, __METHOD__, 'start', '👩 place order', [$this->request->all()]);
        /** @var ApiHelper $apiHelper */
        $apiHelper = pluginApp(ApiHelper::class);
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);

        $checkoutSessionId = $this->request->get('amazonCheckoutSessionId');
        $sessionStorageRepository->setSessionValue('amazonCheckoutSessionId', $checkoutSessionId);
        $checkoutSession = $apiHelper->getCheckoutSession($checkoutSessionId);
        $this->log(__CLASS__, __METHOD__, 'info', '', [$checkoutSession]);
        if ($checkoutSession->statusDetails->state === StatusDetails::OPEN) {
            $configHelper = pluginApp(ConfigHelper::class);
            return $this->response->redirectTo($configHelper->getAbsoluteUrl('place-order'));
        } else {
            $checkoutHelper = pluginApp(CheckoutHelper::class);
            $checkoutHelper->scheduleNotification($checkoutHelper->getTranslation('AmazonPay.pleaseSelectAnotherPaymentMethod'));
            $sessionStorageRepository->setSessionValue('amazonCheckoutSessionId', null);
            $checkoutHelper->resetPaymentMethod();

            $this->log(__CLASS__, __METHOD__, 'failed', '☹ Checkout failed - buyer cancelled or was declined');
            return $this->response->redirectTo($this->getShopCheckoutUrl());
        }
    }

    public function signIn()
    {
        $this->log(__CLASS__, __METHOD__, 'start', '👩 sign in');
        $buyerToken = $this->request->get('buyerToken');

        if (empty($buyerToken)) {
            return $this->response->redirectTo(''); //TODO error msg+log
        }

        $apiHelper = pluginApp(ApiHelper::class);
        $accountHelper = pluginApp(AccountHelper::class);

        $buyer = $apiHelper->getBuyer($buyerToken);
        $accountHelper->createAccountSession($buyer);
        return $this->response->redirectTo($this->getShopAccountUrl());
    }

    public function unsetPaymentMethod()
    {
        $this->log(__CLASS__, __METHOD__, 'start', '👩 unset payment method');
        $checkoutHelper = pluginApp(CheckoutHelper::class);
        $checkoutHelper->resetPaymentMethod();
        return $this->response->redirectTo($this->getShopCheckoutUrl());
    }

    private function _cancelCheckoutStart(SessionStorageRepositoryContract $sessionStorageRepository, CheckoutHelper $checkoutHelper)
    {
        $checkout = pluginApp(Checkout::class);
        $frontendPaymentMethodRepository = pluginApp(FrontendPaymentMethodRepositoryContract::class);
        $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);

        $sessionStorageRepository->setSessionValue('amazonCheckoutSessionId', null);
        $amazonPayPaymentMethod = $paymentMethodHelper->createMopIfNotExistsAndReturnId();

        foreach ($frontendPaymentMethodRepository->getCurrentPaymentMethodsList() as $paymentMethod) {
            if ($paymentMethod->id != $amazonPayPaymentMethod) {
                $checkout->setPaymentMethodId($paymentMethod->id);
                break;
            }
        }

        $checkoutHelper->scheduleNotification($checkoutHelper->getTranslation('AmazonPay.pleaseSelectAnotherPaymentMethod'));
        return $this->response->redirectTo($this->getShopCheckoutUrl());
    }

    private function _continueWithAdditionalPaymentButton(ApiHelper $apiHelper, CheckoutHelper $checkoutHelper, $existingOrder = null)
    {
        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);
        $checkoutDetails = $checkoutHelper->getCheckoutSessionDataForDirectCheckout($existingOrder);
        try {
            $createCheckoutSessionPayload = stripslashes(json_encode($checkoutDetails, JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'failed', '', [$e->getMessage(), $e->getTraceAsString()]);
            $checkoutHelper->scheduleNotification($checkoutHelper->getTranslation('AmazonPay.pleaseSelectAnotherPaymentMethod'));
            return $this->response->redirectTo($this->getShopCheckoutUrl());
        }
        return $this->twig->render('AmazonPayCheckout::content.additional_payment_button', [
            'createCheckoutSessionPayload' => $createCheckoutSessionPayload,
            'estimatedOrderAmount' => ['amount' => (string)$checkoutDetails['paymentDetails']['chargeAmount']['amount'], 'currencyCode' => $checkoutDetails['paymentDetails']['chargeAmount']['currencyCode']],
            'language' => $configHelper->getLocale(),
            'createCheckoutSessionSignature' => $apiHelper->generateButtonSignature($createCheckoutSessionPayload),
        ]);
    }

    private function getShopCheckoutUrl()
    {
        return pluginApp(ConfigHelper::class)->getShopCheckoutUrlRelative();
    }

    private function getShopAccountUrl()
    {
        return pluginApp(ShopUrls::class)->myAccount;
    }

    private function getOrderConfirmationUrl(Order $order)
    {
        $shopUrls = pluginApp(ShopUrls::class);
        $orderRepo = pluginApp(OrderRepositoryContract::class);
        $authHelper = pluginApp(AuthHelper::class);

        $url = $shopUrls->orderConfirmation($order->id);
        $orderAccessKey = $authHelper->processUnguarded(
            function () use ($order, $orderRepo) {
                return $orderRepo->generateAccessKey($order->id);
            }
        );
        $url .= (str_contains($url, '?') ? '&' : '/') . $orderAccessKey;
        return str_replace('//', '/', $url);
    }

    private function getShopBasketUrl()
    {
        return pluginApp(ShopUrls::class)->basket;
    }
}
