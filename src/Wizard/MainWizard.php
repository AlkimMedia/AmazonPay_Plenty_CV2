<?php

namespace AmazonPayCheckout\Wizard;

use AmazonPayCheckout\Wizard\Services\ConfigService;
use Plenty\Modules\Wizard\Services\WizardProvider;

/**
 * Class ShopWizard
 */
class MainWizard extends WizardProvider
{
    private $configService;

    /**
     * @param ConfigService $configService
     */
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * @return array
     */
    protected function structure(): array
    {
        $return = [
            'title' => 'Amazon Pay',
            'shortDescription' => 'Einfache Einrichtung von Amazon Pay für deinen plentyShop',
            'keywords' => ['Amazon Pay'],
            'topics' => [
                'payment',
            ],
            'key' => 'amazonPayWizard',
            'reloadStructure' => true,
            'priority' => 100,
            'relevance' => 'essential',
            'iconPath' => 'https://m.media-amazon.com/images/G/01/EPSMarketingJRubyWebsite/assets/mindstorms/amazonpay-logo-rgb_clr._CB1560911315_.svg',
            //'dataSource' => 'Ceres\Wizard\ShopWizard\DataSource\ShopWizardDataSource',
            'settingsHandlerClass' => 'AmazonPayCheckout\Wizard\SettingsHandlers\MainSettingsHandler',
            'translationNamespace' => 'AmazonPayCheckout',
            'options' => [
                'pluginSetId' => $this->buildPluginSetOptions(),
            ],
            'steps' => [
                'welcome' => [
                    'title' => 'Wizard.welcomeStepTitle',
                    'description' => 'Wizard.welcomeStepDescription',
                    'condition' => true,
                    'sections' => [
                        [
                            'form' =>[]

                        ],
                    ],
                ],

                'credentialsStep' => [
                    'title' => 'Wizard.credentialsStepTitle',
                    'description' => 'Wizard.credentialsStepDescription',
                    'condition' => true,
                    //'validationClass' => 'AmazonPayCheckout\Wizard\Validators\CredentialsValidator',
                    'sections' => [
                        [
                            'title' => 'Wizard.credentialsStoreIdSectionTitle',
                            'description' => 'Wizard.credentialsStoreIdSectionDescription',
                            'condition' => true,
                            'form' => [
                                'storeId' => [
                                    'type' => 'text',
                                    'options' => [
                                        'name' => 'Config.storeIdLabel',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'title' => 'Wizard.credentialsMerchantIdSectionTitle',
                            'description' => 'Wizard.credentialsMerchantIdSectionDescription',
                            'condition' => true,
                            'form' => [
                                'storeId' => [
                                    'type' => 'text',
                                    'options' => [
                                        'name' => 'Config.merchantIdLabel',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],

                        [
                            'title' => 'Wizard.credentialsKeySectionTitle',
                            'description' => 'Wizard.credentialsKeySectionDescription',
                            'condition' => true,
                            'form' => [
                                'privateKey' => [
                                    'type' => 'textarea',
                                    'options' => [
                                        'name' => 'Config.privateKeyLabel',
                                        'required' => true,
                                    ],
                                ],
                                'publicKeyId' => [
                                    'type' => 'text',
                                    'options' => [
                                        'name' => 'Config.publicKeyIdLabel',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],

                        [
                            'title' => 'Wizard.credentialsCountrySectionTitle',
                            'description' => 'Wizard.credentialsCountrySectionDescription',
                            'condition' => true,
                            'form' => [
                                'accountCountry' => [
                                    'type' => 'select',
                                    'options' => [
                                        'name' => 'Config.accountCountryLabel',
                                        'listBoxValues' => [
                                            [
                                                'value' => 'DE',
                                                'caption' => 'Config.accountCountryPossibleValueDE',
                                            ],
                                            [
                                                'value' => 'UK',
                                                'caption' => 'Config.accountCountryPossibleValueUK',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'ipnStep' => [
                    'title' => 'Wizard.ipnStepTitle',
                    'description' => 'Wizard.ipnStepDescription',
                    'condition' => true,
                    'sections' => [
                        [
                            'form' =>
                                [

                                ],
                        ],
                    ],
                ],
                'sandboxStep' => [
                    'title' => 'Wizard.sandboxStepTitle',
                    'description' => 'Wizard.debugStepDescription',
                    'condition' => true,
                    'sections' => [
                        [
                            'title' => '',
                            'condition' => true,
                            'form' => [
                                'sandbox' => [
                                    'type' => 'checkbox',
                                    'options' => [
                                        'name' => 'Config.sandboxLabel',
                                    ],
                                ],
                                'hideButtons' => [
                                    'type' => 'checkbox',
                                    'options' => [
                                        'name' => 'Config.hideButtonsLabel',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'processesStep' => [
                    'title' => 'Wizard.processesStepTitle',
                    'description' => 'Wizard.processesStepDescription',
                    'condition' => true,
                    'sections' => [
                        [
                            'title' => 'Wizard.processesAuthSectionTitle',
                            'description' => 'Wizard.processesAuthSectionDescription',
                            'condition' => true,
                            'form' => [
                                'authorizationMode' => [
                                    'type' => 'select',
                                    'options' => [
                                        'name' => 'Config.authorizationModeLabel',
                                        'listBoxValues' => [
                                            [
                                                'value' => 'default',
                                                'caption' => 'Config.authorizationModePossibleValueDefault',
                                            ],
                                            [
                                                'value' => 'fast_auth',
                                                'caption' => 'Config.authorizationModePossibleValueFastAuth',
                                            ],
                                            [
                                                'value' => 'manually',
                                                'caption' => 'Config.authorizationModePossibleValueManually',
                                            ],
                                        ],
                                    ],

                                ],
                            ],
                        ],
                        [
                            'title' => 'Wizard.processesCaptureSectionTitle',
                            'description' => 'Wizard.processesCaptureSectionDescription',
                            'condition' => true,
                            'form' => [
                                'captureMode' => [
                                    'type' => 'select',
                                    'options' => [
                                        'name' => 'Config.captureModeLabel',
                                        'listBoxValues' => [
                                            [
                                                'value' => 'after_auth',
                                                'caption' => 'Config.captureModePossibleValueAfterAuth',
                                            ],
                                            [
                                                'value' => 'manually',
                                                'caption' => 'Config.captureModePossibleValueManually',
                                            ]
                                        ],
                                    ],

                                ],
                            ],
                        ],
                        [
                            'title' => 'Wizard.processesStatusAfterAuthSectionTitle',
                            'description' => 'Wizard.processesStatusAfterAuthSectionDescription',
                            'condition' => true,
                            'form' => [
                                'authorizedStatus' => [
                                    'type' => 'text',
                                    'defaultValue' => '4/5',
                                    'options' => [
                                        'name' => 'Config.authorizedStatusLabel',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'title' => 'Wizard.processesShippingMailSectionTitle',
                            'description' => 'Wizard.processesShippingMailSectionDescription',
                            'condition' => true,
                            'form' => [
                                'useEmailInShippingAddress' => [
                                    'type' => 'checkbox',
                                    'defaultValue' => true,
                                    'options' => [
                                        'name' => 'Config.useEmailInShippingAddressLabel',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'styleStep' => [
                    'title' => 'Wizard.styleStepTitle',
                    'condition' => true,
                    'sections' => [
                        [
                            'description' => 'Wizard.styleSectionDescription',
                            'condition' => true,
                            'form' => [
                                'payButtonColor' => [
                                    'type' => 'select',
                                    'options' => [
                                        'name' => 'Config.payButtonColorLabel',
                                        'listBoxValues' => [
                                            [
                                                'value' => 'Gold',
                                                'caption' => 'Config.payButtonColorPossibleValueGold',
                                            ],
                                            [
                                                'value' => 'LightGray',
                                                'caption' => 'Config.payButtonColorPossibleValueLightGray',
                                            ],
                                            [
                                                'value' => 'DarkGray',
                                                'caption' => 'Config.payButtonColorPossibleValueDarkGray',
                                            ],
                                        ],
                                    ],

                                ],
                                'loginButtonColor' => [
                                    'type' => 'select',
                                    'options' => [
                                        'name' => 'Config.loginButtonColorLabel',
                                        'listBoxValues' => [
                                            [
                                                'value' => 'Gold',
                                                'caption' => 'Config.payButtonColorPossibleValueGold',
                                            ],
                                            [
                                                'value' => 'LightGray',
                                                'caption' => 'Config.payButtonColorPossibleValueLightGray',
                                            ],
                                            [
                                                'value' => 'DarkGray',
                                                'caption' => 'Config.payButtonColorPossibleValueDarkGray',
                                            ],
                                        ],
                                    ],

                                ],
                            ],
                        ],
                    ],
                ],
                'eventsStep' => [
                    'title' => 'Wizard.eventsStepTitle',
                    'condition' => true,
                    'sections' => [
                        [
                            'title' => 'Wizard.eventsCaptureSectionTitle',
                            'description' => 'Wizard.eventsCaptureSectionDescription',

                            'form' =>
                                [],


                        ],
                        [
                            'title' => 'Wizard.eventsCloseSectionTitle',
                            'description' => 'Wizard.eventsCloseSectionDescription',
                            'form' => [],

                        ],
                        [
                            'title' => 'Wizard.eventsRefundSectionTitle',
                            'description' => 'Wizard.eventsRefundSectionDescription',
                            'form' => [],

                        ],
                    ],
                ],
            ],
        ];
        return $return;
    }

    /**
     * @return array
     */
    private function buildPluginSetOptions()
    {
        $pluginSets = $this->configService->getPluginSets();
        $pluginSetValues = [
            [
                'value' => '',
                'caption' => '',
            ],
        ];

        if (count($pluginSets)) {
            foreach ($pluginSets as $pluginSet) {
                $pluginSetValues[] = [
                    'value' => $pluginSet['id'],
                    'caption' => $pluginSet['name'],
                ];
            }
        }

        return [
            'type' => 'select',
            'defaultValue' => $pluginSetValues[0]['value'],
            'options' => [
                'name' => 'Wizard.pluginSetSelection',
                'listBoxValues' => $pluginSetValues,
            ],
        ];
    }
}