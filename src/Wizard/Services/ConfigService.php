<?php

namespace AmazonPayCheckout\Wizard\Services;

use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Modules\Plugin\Models\Plugin;
use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Modules\Plugin\PluginSet\Models\PluginSet;

class ConfigService
{
    /**
     * @var array
     */
    private $pluginSetList;

    /**
     * @return array
     */
    public function getPluginSets(): array
    {
        if (is_array($this->pluginSetList)) {
            return $this->pluginSetList;
        }
        /** @var PluginSetRepositoryContract $pluginSetRepo */
        $pluginSetRepo = pluginApp(PluginSetRepositoryContract::class);
        /** @var PluginRepositoryContract $pluginRepo */
        $pluginRepo = pluginApp(PluginRepositoryContract::class);
        $pluginSets = $pluginSetRepo->list();
        $pluginSetsData = $pluginSets->toArray();
        $pluginSetList = [];
        if (count($pluginSetsData)) {
            $plugin = $pluginRepo->getPluginByName("AmazonPayCheckout");
            if ($plugin instanceof Plugin) {
                foreach ($pluginSetsData as $pluginSetData) {
                    $pluginSet = $pluginSets->where('id', '=', $pluginSetData['id'])->first();
                    if ($pluginSet instanceof PluginSet) {
                        if ($pluginRepo->isActiveInPluginSet($plugin->id, $pluginSet)) {
                            $pluginSetList[] = $pluginSetData;
                        }
                    }
                }
            }
        }

        $this->pluginSetList = $pluginSetList;
        return $pluginSetList;
    }

}