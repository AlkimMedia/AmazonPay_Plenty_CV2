<?php

namespace AmazonPayCheckout\Widgets;

use AmazonPayCheckout\Helpers\ConfigHelper;
use Ceres\Widgets\Helper\BaseWidget;

class LoginButtonWidget extends BaseWidget
{
    protected $template = "AmazonPayCheckout::content.login_button";

    protected function getTemplateData($widgetSettings, $isPreview)
    {
        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);

        return [
            'color' => $configHelper->getConfigurationValue('loginButtonColor'),
        ];
    }
}