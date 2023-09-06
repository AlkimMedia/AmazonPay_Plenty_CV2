<?php

namespace AmazonPayCheckout\Helpers;

use AmazonPayCheckout\Traits\LoggingTrait;
use IO\Extensions\Constants\ShopUrls;
use IO\Services\SessionStorageService;
use IO\Services\UrlBuilder\UrlQuery;
use IO\Services\WebstoreConfigurationService;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Plugin\Contracts\ConfigurationRepositoryContract;
use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Modules\Plugin\Models\Plugin;
use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Modules\Webshop\Contracts\LocalizationRepositoryContract;
use Plenty\Plugin\ConfigRepository;

class ConfigHelper
{
    use LoggingTrait;

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
            'integrator_id' => $this->getPlatformId(),
            'integrator_version' => $this->getPluginVersion(),
            'platform_version' => $this->getShopVersion(),
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

    public function getAuthorizedStatus()
    {
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

    public function getShopCheckoutUrl(): string
    {
        return $this->getAbsoluteUrl($this->getShopCheckoutUrlRelative());
    }

    public function getShopCheckoutUrlRelative(): string
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
        if (in_array($locale, self::AVAILABLE_LOCALES)) {
            return $locale;
        }

        $language = strtolower(substr($locale, 2));
        foreach (self::AVAILABLE_LOCALES as $availableLocale) {
            if (strpos($availableLocale, $language) === 0) {
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
        return static::CUSTOM_INFORMATION_STRING . $this->getPluginVersion();
    }

    public function getPluginVersion()
    {
        $plugin = $this->getDecoratedPlugin('AmazonPayCheckout');
        $version = $plugin->version;
        if (preg_match('/^(\d+\.\d+\.\d)/', $version, $match)) {
            return $match[1];
        }
        return null;
    }

    public function getShopVersion()
    {
        $plugin = $this->getDecoratedPlugin('Ceres');
        $version = $plugin->version;
        if (preg_match('/^(\d+\.\d+\.\d)/', $version, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * @param $pluginName
     * @param $pluginSetId
     * @return Plugin|null
     */
    public function getDecoratedPlugin($pluginName, $pluginSetId = null)
    {

        /** @var PluginRepositoryContract $pluginRepo */
        $pluginRepo = pluginApp(PluginRepositoryContract::class);
        $plugin = $pluginRepo->getPluginByName($pluginName);
        if ($plugin && $plugin->name) {
            $plugin = $pluginRepo->decoratePlugin($plugin, $pluginSetId);
            return $plugin;
        }
        return null;
    }

    public function getStoreName(): string
    {
        /** @var WebstoreHelper $storeHelper */
        $storeHelper = pluginApp(WebstoreHelper::class);
        $storeConfig = $storeHelper->getCurrentWebstoreConfiguration();
        $storeName = $storeConfig->name;
        return (strlen($storeName) > 50 ? substr($storeName, 0, 46) . ' ...' : $storeName);
    }

    public function upgradeKeys()
    {
        /** @var PluginSetRepositoryContract $pluginSetRepo */
        $pluginSetRepo = pluginApp(PluginSetRepositoryContract::class);
        $pluginSets = $pluginSetRepo->list();
        $this->log(__CLASS__, __METHOD__, 'start', 'start key upgrade attempt', ['pluginSets'=>$pluginSets]);
        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        $authHelper->processUnguarded(
            function () use ($pluginSets) {
                if (count($pluginSets)) {
                    foreach ($pluginSets as $pluginSet) {
                        $oldPlugin = $this->getDecoratedPlugin('AmazonLoginAndPay', $pluginSet->id);
                        $newPlugin = $this->getDecoratedPlugin('AmazonPayCheckout', $pluginSet->id);
                        if (!$oldPlugin || !$newPlugin) {
                            continue;
                        }

                        /** @var ConfigurationRepositoryContract $configRepo */
                        $configRepo = pluginApp(ConfigurationRepositoryContract::class);
                        $oldPluginConfig = (array)$configRepo->export($pluginSet->id, $oldPlugin->id);
                        $oldPluginConfig = $oldPluginConfig['AmazonLoginAndPay'];
                        $newPluginConfig = (array)$configRepo->export($pluginSet->id, $newPlugin->id);
                        $newPluginConfig = $newPluginConfig['AmazonPayCheckout'];

                        if($newPluginConfig === null){
                            continue;
                        }

                        if ($newPluginConfig && $newPluginConfig['privateKey'] && $newPluginConfig['publicKeyId']) {
                            continue;
                        }

                        if (empty($oldPluginConfig['merchantId']) || empty($oldPluginConfig['mwsAccessKey']) || empty($oldPluginConfig['mwsSecretAccessKey'])) {
                            continue;
                        }


                        /** @var LibraryCallContract $libCaller */
                        $libCaller = pluginApp(LibraryCallContract::class);

                        $requestData = [
                            'merchantId' => $oldPluginConfig['merchantId'],
                            'accessKeyId' => $oldPluginConfig['mwsAccessKey'],
                            'secretKey' => $oldPluginConfig['mwsSecretAccessKey'],
                        ];

                        $this->log(__CLASS__, __METHOD__, 'request', '', [
                            'requestData' => $requestData,
                            'oldPluginConfig' => $oldPluginConfig,
                            'newPluginConfig' => $newPluginConfig,
                            'pluginSet' => $pluginSet,
                        ]);

                        $result = $libCaller->call(
                            'AmazonPayCheckout::key_upgrade',
                            $requestData
                        );

                        if ($result['error']) {
                            $this->log(__CLASS__, __METHOD__, 'error', $result['error'], [$result], true);
                        } else {
                            $this->log(__CLASS__, __METHOD__, 'success', '', $result, true);
                            $configData = [
                                [
                                    'key' => 'merchantId',
                                    'value' => $oldPluginConfig['merchantId'],
                                ],
                                [
                                    'key' => 'privateKey',
                                    'value' => $result['privateKey'],
                                ],
                                [
                                    'key' => 'publicKeyId',
                                    'value' => $result['publicKeyId'],
                                ],


                            ];
                            $saveResult = $configRepo->saveConfiguration(
                                $newPlugin->id,
                                $configData,
                                $pluginSet->id,
                            );
                            $this->log(__CLASS__, __METHOD__, 'saveResult', '', [$saveResult]);

                        }
                    }
                }

            }
        );
    }

}