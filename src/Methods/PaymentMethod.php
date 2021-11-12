<?php

namespace AmazonPayCheckout\Methods;

use AmazonPayCheckout\Helpers\ConfigHelper;
use Plenty\Modules\Payment\Method\Services\PaymentMethodBaseService;
use Plenty\Plugin\Application;
use Plenty\Plugin\Translation\Translator;

class PaymentMethod extends PaymentMethodBaseService
{
    const ICON = '/images/amazon_pay_logo.png';
    const PAYMENT_KEY = 'AMAZON_PAY_CHECKOUT';
    const PLUGIN_KEY = 'alkim_amazonpay_checkout';
    const PAYMENT_NAME = 'Amazon Pay';

    public function isExpressCheckout()
    {
        return true;
    }

    public function isBackendSearchable(): bool
    {
        return true;
    }

    public function isBackendActive(): bool
    {
        return true;
    }

    public function getBackendName(string $lang = ''): string
    {
        return 'Amazon Pay v2';
    }

    public function getName(string $lang = ""): string
    {
        return self::PAYMENT_NAME;
    }

    public function canHandleSubscriptions(): bool
    {
        return false;
    }

    public function getFee(): float
    {
        return 0;
    }

    public function getSourceUrl(string $lang = ""): string
    {
        return '';
    }

    public function isSwitchableTo(): bool
    {
        return true;
    }

    public function isSwitchableFrom(): bool
    {
        return false;
    }

    public function getBackendIcon(): string
    {
        /** @var Application $application */
        $application = pluginApp(Application::class);
        return $application->getUrlPath('AmazonPayCheckout') . '/images/backend_logo.svg';
    }

    public function isActive(): bool
    {
        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);
        return $configHelper->isConfigComplete();
    }

    public function getIcon(string $lang = ""): string
    {
        /** @var Application $application */
        $application = pluginApp(Application::class);
        return $application->getUrlPath('AmazonPayCheckout') . static::ICON;
    }

    public function getDescription(string $lang = ""): string
    {
        /** @var Translator $translator */
        $translator = pluginApp(Translator::class);
        return $translator->trans('AmazonPayCheckout::AmazonPay.checkoutPaymentMethodDescription', [], $lang);
    }
}