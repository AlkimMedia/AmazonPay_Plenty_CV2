<?php

namespace AmazonPayCheckout\Providers;

use AmazonPayCheckout\Helpers\ConfigHelper;
use Plenty\Plugin\Templates\Twig;

class DataProviderCheckoutButton
{
    public function call(Twig $twig)
    {
        /** @var \AmazonPayCheckout\Helpers\ConfigHelper $helper */
        $configHelper = pluginApp(ConfigHelper::class);
        return $twig->render('AmazonPayCheckout::content.checkout_button', ['color' => $configHelper->getConfigurationValue('payButtonColor')]);
    }
}