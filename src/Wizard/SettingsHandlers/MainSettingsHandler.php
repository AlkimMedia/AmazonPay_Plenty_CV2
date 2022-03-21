<?php

namespace AmazonPayCheckout\Wizard\SettingsHandlers;


use Plenty\Modules\ContentCache\Contracts\ContentCacheInvalidationRepositoryContract;
use Plenty\Modules\Plugin\Contracts\ConfigurationRepositoryContract;
use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Modules\Plugin\PluginSet\Models\PluginSetEntry;

use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;

/**
 * Class ShopWizardSettingsHandler
 * @package Ceres\Wizard\ShopWizard\SettingsHandlers
 */
class MainSettingsHandler implements WizardSettingsHandler
{
    /**
     * @param array $parameters
     *
     * @return bool
     */
    public function handle(array $parameters): bool
    {
        $data        = $parameters['data'];
        $pluginSetId = (int)$data['pluginSetId'];

        /** @var ConfigurationRepositoryContract $configRepo */
        $configRepo = pluginApp(ConfigurationRepositoryContract::class);
        /** @var PluginSetRepositoryContract $pluginSetRepo */
        $pluginSetRepo = pluginApp(PluginSetRepositoryContract::class);
        $pluginSets    = $pluginSetRepo->list();
        $pluginId      = '';


        if (count($pluginSets)) {
            foreach ($pluginSets as $pluginSet) {
                foreach ($pluginSet->pluginSetEntries as $pluginSetEntry) {
                    if ($pluginSetEntry instanceof PluginSetEntry && $pluginSetEntry->plugin->name === 'AmazonPayCheckout' && $pluginSetEntry->pluginSetId == $pluginSetId) {
                        $pluginId = (int)$pluginSetEntry->pluginId;
                    }
                }
            }
        }


        if (count($data)) {
            $configData = [];
            foreach ($data as $itemKey => $itemVal) {
                if($itemVal === true){
                    $itemVal = 'true';
                }elseif ($itemVal === false){
                    $itemVal = 'false';
                }
                $configData[] = [
                    'key'   => $itemKey,
                    'value' => $itemVal
                ];
            }

            $configRepo->saveConfiguration($pluginId, $configData, $pluginSetId);
        }

        //invalidate caching
        /** @var ContentCacheInvalidationRepositoryContract $cacheInvalidRepo */
        $cacheInvalidRepo = pluginApp(ContentCacheInvalidationRepositoryContract::class);
        $cacheInvalidRepo->invalidateAll();

        return true;
    }

}
