<?php

namespace AmazonPayCheckout\Providers;

use AmazonPayCheckout\Helpers\PaymentMethodHelper;
use Plenty\Plugin\Templates\Twig;

class DataProviderReinitializeButton
{
    public function call(Twig $twig, $arg): string
    {
        /** @var PaymentMethodHelper $paymentMethodHelper */
        $paymentMethodHelper = pluginApp(PaymentMethodHelper::class);
        return $twig->render('PaymentMethod::PaymentMethodReinitializePayment', ["order" => $arg[0], "paymentMethodId" => $paymentMethodHelper->createMopIfNotExistsAndReturnId()]);
    }
}