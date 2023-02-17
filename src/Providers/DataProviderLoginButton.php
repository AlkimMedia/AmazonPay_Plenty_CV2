<?php

namespace AmazonPayCheckout\Providers;

use AmazonPayCheckout\Helpers\ConfigHelper;
use Plenty\Plugin\Templates\Twig;

class DataProviderLoginButton
{
    public function call(Twig $twig)
    {
        /** @var \AmazonPayCheckout\Helpers\ConfigHelper $helper */
        $configHelper = pluginApp(ConfigHelper::class);
        return $twig->render('AmazonPayCheckout::content.login_button', ['color' => $configHelper->getConfigurationValue('loginButtonColor')]);
    }
}