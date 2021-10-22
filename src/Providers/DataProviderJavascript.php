<?php

namespace AmazonPayCheckout\Providers;

use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Helpers\ConfigHelper;
use AmazonPayCheckout\Helpers\PaymentMethodHelper;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\Templates\Twig;

class DataProviderJavascript
{

    public function call(Twig $twig)
    {
        /** @var \AmazonPayCheckout\Helpers\ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);

        /** @var \AmazonPayCheckout\Helpers\PaymentMethodHelper $paymentMethodHelper */
        $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);

        /** @var \AmazonPayCheckout\Helpers\ApiHelper $apiHelper */
        $apiHelper = pluginApp(ApiHelper::class);

        /** @var SessionStorageRepositoryContract $sessionStorageRepository */
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);
        $checkoutSessionId        = $sessionStorageRepository->getSessionValue('amazonCheckoutSessionId');

        $urls = [
            'createCheckoutSession' => $configHelper->getCreateCheckoutSessionUrl(),
            'test'                  => 'testurl'
        ];
        
        $loginPayload = stripslashes(json_encode([
            'signInReturnUrl' => $configHelper->getSignInReturnUrl(),
            'storeId'         => $configHelper->getConfigurationValue('storeId'),
            'signInScopes'    => ["name", "email", "postalCode"]
        ]));
        $loginSignature = $apiHelper->generateButtonSignature($loginPayload);

        return $twig->render('AmazonPayCheckout::content.javascript', [
            'urls' => $urls,
            'checkoutSessionId' => $checkoutSessionId,
            'paymentMethodId' => $paymentMethodHelper->createMopIfNotExistsAndReturnId(),
            'loginPayload'=>$loginPayload,
            'loginSignature'=>$loginSignature
        ]);
    }
}