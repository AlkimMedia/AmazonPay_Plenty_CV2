<?php

namespace AmazonPayCheckout\Widgets;

use AmazonPayCheckout\Helpers\ConfigHelper;
use Ceres\Widgets\Helper\BaseWidget;

class CheckoutButtonWidget extends BaseWidget
{
    protected $template = "AmazonPayCheckout::content.checkout_button";

    protected function getTemplateData($widgetSettings, $isPreview)
    {
        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);

        return [
            'color' => $configHelper->getConfigurationValue('payButtonColor'),
        ];
    }
}