<?php

namespace AmazonPayCheckout\Helpers;

use IO\Services\SessionStorageService;
use IO\Services\UrlBuilder\UrlQuery;
use IO\Services\WebstoreConfigurationService;
use Plenty\Plugin\ConfigRepository;

class ConfigHelper
{
    /**
     * @var \Plenty\Plugin\ConfigRepository
     */
    private $configRepository;
    private $platformId = 'A1SGXK19QKIYNB';

    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;

    }

    /**
     * @return string
     */
    public function getPlatformId(): string
    {
        return $this->platformId;
    }

    public function getClientConfiguration()
    {
        return [
            'public_key_id' => $this->getConfigurationValue('publicKeyId'),
            'private_key'   => $this->getConfigurationValue('privateKey'),
            'region'        => $this->getConfigurationValue('accountCountry'),
            'sandbox'       => (bool)$this->getConfigurationValue('sandbox'),
        ];

    }

    public function getConfigurationValue($key)
    {
        return $this->configRepository->get('AmazonPayCheckout.' . $key);
    }
    public function getUrl($path)
    {
        return $this->getAbsoluteUrl($path);
    }

    public function getAbsoluteUrl($path)
    {
        /** @var WebstoreConfigurationService $webstoreConfigurationService */
        $webstoreConfigurationService = pluginApp(WebstoreConfigurationService::class);
        /** @var SessionStorageService $sessionStorage */
        $sessionStorage  = pluginApp(SessionStorageService::class);
        $defaultLanguage = $webstoreConfigurationService->getDefaultLanguage();
        $lang            = $sessionStorage->getLang();

        $includeLanguage = $lang !== null && $lang !== $defaultLanguage;
        /** @var UrlQuery $urlQuery */
        $urlQuery = pluginApp(UrlQuery::class, ['path' => $path, 'lang' => $lang]);

        return $urlQuery->toAbsoluteUrl($includeLanguage);
    }

    public function getCheckoutReviewReturnUrl()
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-return');
    }

    public function getCreateCheckoutSessionUrl()
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-get-session');
    }

    public function getSignInReturnUrl()
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-sign-in');
    }

    public function getIpnUrl()
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-ipn');
    }

    public function getCheckoutStartUrl()
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-checkout-start');
    }

    public function getCurrency()
    {
        return 'EUR';//TODO
    }

    public function getLanguage()
    {
        return 'de_DE';
    }

    public function getCheckoutResultReturnUrl()
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-place-order');
    }

    public function getPayExistingOrderCheckoutResultReturnUrl($orderId)
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-existing-order-process').'?order_id='.$orderId;
    }


    public function getCustomInformationString()
    {
        return 'Created by Alkim Media, plentymarkets, V*'; //TODO
    }

    public function getStoreName():string{
        return ''; //TODO //(strlen($storeName) > 50 ? substr($storeName, 0, 46) . ' ...' : $storeName);
    }

}