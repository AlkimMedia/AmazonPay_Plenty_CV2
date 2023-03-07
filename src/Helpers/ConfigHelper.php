<?php

namespace AmazonPayCheckout\Helpers;

use IO\Extensions\Constants\ShopUrls;
use IO\Services\SessionStorageService;
use IO\Services\UrlBuilder\UrlQuery;
use IO\Services\WebstoreConfigurationService;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Modules\Webshop\Contracts\LocalizationRepositoryContract;
use Plenty\Plugin\ConfigRepository;

class ConfigHelper
{
    const AVAILABLE_LOCALES = ['en_GB', 'de_DE', 'fr_FR', 'it_IT', 'es_ES'];
    const CUSTOM_INFORMATION_STRING = 'Created by Alkim Media, plentymarkets, v';

    /**
     * @var ConfigRepository
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

    public function getClientConfiguration(): array
    {
        return [
            'public_key_id' => $this->getConfigurationValue('publicKeyId'),
            'private_key' => $this->getConfigurationValue('privateKey'),
            'region' => $this->getConfigurationValue('accountCountry'),
            'sandbox' => $this->getConfigurationValue('sandbox') === 'true',
        ];
    }

    public function isConfigComplete(): bool
    {
        return
            !empty($this->getConfigurationValue('publicKeyId'))
            &&
            !empty($this->getConfigurationValue('privateKey'))
            &&
            !empty($this->getConfigurationValue('accountCountry'))
            &&
            !empty($this->getConfigurationValue('sandbox'));
    }

    public function getConfigurationValue($key)
    {
        return $this->configRepository->get('AmazonPayCheckout.' . $key);
    }

    public function getAuthorizedStatus(){
        return $this->getConfigurationValue('authorizedStatus');
    }

    public function getUrl($path): string
    {
        return $this->getAbsoluteUrl($path);
    }

    public function getAbsoluteUrl($path): string
    {
        /** @var WebstoreConfigurationService $webstoreConfigurationService */
        $webstoreConfigurationService = pluginApp(WebstoreConfigurationService::class);
        /** @var SessionStorageService $sessionStorage */
        $sessionStorage = pluginApp(SessionStorageService::class);
        $defaultLanguage = $webstoreConfigurationService->getDefaultLanguage();
        $lang = $sessionStorage->getLang();

        $includeLanguage = $lang !== null && $lang !== $defaultLanguage;
        /** @var UrlQuery $urlQuery */
        $urlQuery = pluginApp(UrlQuery::class, ['path' => $path, 'lang' => $lang]);

        return $urlQuery->toAbsoluteUrl($includeLanguage);
    }

    public function getCheckoutReviewReturnUrl(): string
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-return');
    }

    public function getCreateCheckoutSessionUrl(): string
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-get-session');
    }

    public function getSignInReturnUrl(): string
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-sign-in');
    }

    public function getIpnUrl(): string
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-ipn');
    }

    public function getCheckoutStartUrl(): string
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-checkout-start');
    }

    public function getShopCheckoutUrl():string
    {
        return $this->getAbsoluteUrl($this->getShopCheckoutUrlRelative());
    }
    public function getShopCheckoutUrlRelative():string
    {
        /** @var ShopUrls $shopUrls */
        $shopUrls = pluginApp(ShopUrls::class);
        return $shopUrls->checkout;
    }

    public function getLocale(): string
    {
        /** @var LocalizationRepositoryContract $localizationRepository */
        $localizationRepository = pluginApp(LocalizationRepositoryContract::class);
        $locale = $localizationRepository->getLocale();
        if(in_array($locale, self::AVAILABLE_LOCALES)){
            return $locale;
        }

        $language = strtolower(substr($locale, 2));
        foreach(self::AVAILABLE_LOCALES as $availableLocale){
            if(strpos($availableLocale, $language) === 0){
                return $availableLocale;
            }
        }

        return 'en_GB';
    }

    public function getCheckoutResultReturnUrl(): string
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-place-order');
    }

    public function getPayExistingOrderCheckoutResultReturnUrl($orderId): string
    {
        return $this->getAbsoluteUrl('payment/amazon-pay-existing-order-process') . '?order_id=' . $orderId;
    }


    public function getCustomInformationString(): string
    {
        return static::CUSTOM_INFORMATION_STRING.$this->getPluginVersion();
    }

    public function getPluginVersion(){
        /** @var PluginRepositoryContract $pluginRepo */
        $pluginRepo = pluginApp(PluginRepositoryContract::class);
        $plugin = $pluginRepo->getPluginByName("AmazonPayCheckout");
        $plugin = $pluginRepo->decoratePlugin($plugin);
        return $plugin->version;
    }

    public function getStoreName(): string
    {
        /** @var WebstoreHelper $storeHelper */
        $storeHelper = pluginApp(WebstoreHelper::class);
        $storeConfig = $storeHelper->getCurrentWebstoreConfiguration();
        $storeName = $storeConfig->name;
        return (strlen($storeName) > 50 ? substr($storeName, 0, 46) . ' ...' : $storeName);
    }
}