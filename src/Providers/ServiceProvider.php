<?php

namespace AmazonPayCheckout\Providers;

use AmazonPayCheckout\Contracts\TransactionRepositoryContract;
use AmazonPayCheckout\CronHandlers\ExternalOrderMatcherCronHandler;
use AmazonPayCheckout\Helpers\CheckoutHelper;
use AmazonPayCheckout\Helpers\ConfigHelper;
use AmazonPayCheckout\Helpers\OrderHelper;
use AmazonPayCheckout\Helpers\PaymentMethodHelper;
use AmazonPayCheckout\Methods\PaymentMethod;
use AmazonPayCheckout\Repositories\TransactionRepository;
use AmazonPayCheckout\Traits\LoggingTrait;
use AmazonPayCheckout\Wizard\MainWizard;
use Ceres\Helper\LayoutContainer;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\Cron\Services\CronContainer;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Modules\Wizard\Contracts\WizardContainerContract;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider as ServiceProviderParent;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Translation\Translator;

class ServiceProvider extends ServiceProviderParent
{
    use LoggingTrait;

    public function boot(
        PaymentMethodHelper    $paymentMethodHelper,
        Dispatcher             $eventDispatcher,
        PaymentMethodContainer $payContainer,
        EventProceduresService $eventProceduresService,
        CronContainer          $cronContainer
    )
    {
        $paymentMethodId = $paymentMethodHelper->createMopIfNotExistsAndReturnId(); //TODO move to migration
        $payContainer->register(
            PaymentMethod::PLUGIN_KEY . '::' . PaymentMethod::PAYMENT_KEY,
            PaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class,
            ]
        );
        $this->registerCronjobs($cronContainer);

        $eventDispatcher->listen(ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentMethodId) {
                if ($event->getMop() == $paymentMethodId) {
                    /** @var \AmazonPayCheckout\Helpers\CheckoutHelper $checkoutHelper */
                    $checkoutHelper = pluginApp(CheckoutHelper::class);

                    $orderId = $event->getOrderId();

                    /** @var OrderHelper $orderHelper */
                    $orderHelper = pluginApp(OrderHelper::class);
                    $order = $orderHelper->getOrder($orderId);

                    /** @var SessionStorageRepositoryContract $sessionStorageRepository */
                    $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);
                    $checkoutSessionId = $sessionStorageRepository->getSessionValue('amazonCheckoutSessionId');

                    if (empty($checkoutSessionId)) {
                        $this->log(__CLASS__, __METHOD__, 'emptyId', 'checkoutSessionId_empty', [
                            'orderId' => $orderId,
                            'paymentMethodId' => $event->getMop(),
                        ]);
                        $event->setType('error');
                        $event->setValue($checkoutHelper->getTranslation('AmazonPay.executePaymentError'));
                        return;
                    }
                    try {
                        $checkoutHelper->executePayment($order, $checkoutSessionId);
                    }catch(\Exception $e){
                        $this->log(__CLASS__, __METHOD__, 'failed', $e->getMessage(), [
                            'orderId' => $orderId
                        ]);
                        $event->setType('error');
                        $event->setValue($checkoutHelper->getTranslation('AmazonPay.executePaymentError'));
                    }
                }
            }
        );

        $eventDispatcher->listen(GetPaymentMethodContent::class,
            function (GetPaymentMethodContent $event) use ($paymentMethodId) {
                if ($event->getMop() == $paymentMethodId) {
                    /** @var ConfigHelper $configHelper */
                    $configHelper = pluginApp(ConfigHelper::class);
                    $this->log(__CLASS__, __METHOD__, 'get_payment_method_content');
                    $event->setType(GetPaymentMethodContent::RETURN_TYPE_REDIRECT_URL);
                    $event->setValue($configHelper->getCheckoutStartUrl());
                }
            });

        $eventDispatcher->listen('Ceres.LayoutContainer.Checkout.PaymentList',
            function (LayoutContainer $container, $arguments) {
                /** @var \AmazonPayCheckout\Helpers\CheckoutHelper $checkoutHelper */
                $checkoutHelper = pluginApp(CheckoutHelper::class);
                /** @var Translator $translator */
                $translator = pluginApp(Translator::class);
                if ($checkoutHelper->isCurrentPaymentMethodAmazonPay() && ($checkoutSession = $checkoutHelper->getOpenSession())) {
                    $paymentDescriptor = '';
                    if (is_array($checkoutSession->paymentPreferences)) {
                        foreach ($checkoutSession->paymentPreferences as $paymentPreference) {
                            if (is_object($paymentPreference) && isset($paymentPreference->paymentDescriptor)) {
                                $paymentDescriptor = $paymentPreference->paymentDescriptor;
                                break;
                            }
                        }
                    }
                    $container->addContent('
                        <div class="amazon-pay-payment-info-container card mb-4">
                            <div class="checkout-amazon-pay-logo-container">
                                <img src="https://amazon-pay-assets.s3.eu-central-1.amazonaws.com/logos/logo_default.svg"/>
                            </div>
                            <div>' . $paymentDescriptor . '</div>
                            <div><a href="#" id="amazon-pay-change-payment">' . $checkoutHelper->getTranslation('AmazonPay.changeAmazonPayPaymentMean') . '</a></div>
                            <div><a href="/payment/amazon-pay-unset-payment-method">' . $checkoutHelper->getTranslation('AmazonPay.changePaymentMethod') . '</a></div>
                        </div>'
                    );
                }
            });

        $eventDispatcher->listen('Ceres.LayoutContainer.MyAccount.OrderHistoryPaymentInformation',
            function (LayoutContainer $container, $order) use ($paymentMethodHelper) {
                /** @var OrderHelper $orderHelper */
                $orderHelper = pluginApp(OrderHelper::class);
                if (!empty($order->id)) {
                    $buttonHtml = $orderHelper->createPayButtonForExistingOrder($order, $paymentMethodHelper);
                    $container->addContent(
                        $buttonHtml
                    );
                }
            });

        $eventDispatcher->listen('Ceres.LayoutContainer.OrderConfirmation.AdditionalPaymentInformation',
            function (LayoutContainer $container, $order) use ($paymentMethodHelper) {
                /** @var OrderHelper $orderHelper */
                $orderHelper = pluginApp(OrderHelper::class);
                $orderHelper->log(__CLASS__, __METHOD__, 'ohContainer', 'test', [$order, is_object($order), is_array($order), $order->id, $order['id']]);
                if (!empty($order['id'])) {
                    $buttonHtml = $orderHelper->createPayButtonForExistingOrder($orderHelper->getOrder($order['id']), $paymentMethodHelper);
                    $container->addContent(
                        $buttonHtml
                    );
                }
            });

        $eventDispatcher->listen('Ceres.LayoutContainer.Script.AfterScriptsLoaded',
            function (LayoutContainer $container) {
                /** @var ConfigHelper $configHelper */
                $configHelper = pluginApp(ConfigHelper::class);
                if (!$configHelper->isConfigComplete()) {
                    return;
                }
                /** @var DataProviderJavascript $dataProvider */
                $dataProvider = pluginApp(DataProviderJavascript::class);
                $container->addContent($dataProvider->call(pluginApp(Twig::class)));
            });


        $eventDispatcher->listen('Ceres.LayoutContainer.Checkout.BeforeShippingAddress',
            function (LayoutContainer $container, $arguments) {
                /** @var \AmazonPayCheckout\Helpers\CheckoutHelper $checkoutHelper */
                $checkoutHelper = pluginApp(CheckoutHelper::class);
                /** @var Translator $translator */
                $translator = pluginApp(Translator::class);
                if ($checkoutHelper->isCurrentPaymentMethodAmazonPay() && $checkoutHelper->hasOpenSession()) {
                    if ($shippingAddress = $checkoutHelper->getShippingAddress()) {
                        $container->addContent('
                        <div class="amazon-pay-shipping-address-container card mb-4">
                            <div class="amazon-pay-shipping-address">
                                <div>' . $shippingAddress->companyName . '</div>
                                <div>' . $shippingAddress->firstName . ' ' . $shippingAddress->lastName . '</div>
                                <div>' . $shippingAddress->street . ' ' . $shippingAddress->houseNumber . '</div>
                                <div>' . $shippingAddress->postalCode . ' ' . $shippingAddress->town . '</div>
                                <div>' . $shippingAddress->country->name . '</div>
                                <div class="amazon-pay-change-address-container"><a href="#" id="amazon-pay-change-address">' . $checkoutHelper->getTranslation('AmazonPay.changeAddress') . '</a></div>
                            </div>
                       </div>
                       ');
                    }
                }
            });
        $eventProceduresService->registerProcedure(
            PaymentMethod::PLUGIN_KEY,
            ProcedureEntry::PROCEDURE_GROUP_ORDER,
            [
                'de' => 'Amazon Pay v2: Vorgang schließen',
                'en' => 'Amazon Pay v2: Close order',
            ],
            '\AmazonPayCheckout\Procedures\CloseChargePermissionProcedure@run'
        );

        $eventProceduresService->registerProcedure(
            PaymentMethod::PLUGIN_KEY,
            ProcedureEntry::PROCEDURE_GROUP_ORDER,
            [
                'de' => 'Amazon Pay v2: Vollständiger Zahlungseinzug',
                'en' => 'Amazon Pay v2: Complete capture',
            ],
            '\AmazonPayCheckout\Procedures\CaptureProcedure@run'
        );

        $eventProceduresService->registerProcedure(
            PaymentMethod::PLUGIN_KEY,
            ProcedureEntry::PROCEDURE_GROUP_ORDER,
            [
                'de' => 'Amazon Pay v2: Rückzahlung',
                'en' => 'Amazon Pay v2: Refund',
            ],
            '\AmazonPayCheckout\Procedures\RefundProcedure@run'
        );

        /** @var WizardContainerContract $wizardContainerContract */
        $wizardContainerContract = pluginApp(WizardContainerContract::class);
        $wizardContainerContract->register('amazonPayWizard', MainWizard::class);

        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);
        $configHelper->upgradeKeys();
    }

    protected function registerCronjobs(CronContainer $cronContainer)
    {
        $cronContainer->add(CronContainer::EVERY_FIFTEEN_MINUTES, ExternalOrderMatcherCronHandler::class);
    }

    /**
     * Register the service provider.
     */

    public function register()
    {
        $this->getApplication()->register(RouteServiceProvider::class);
        $this->getApplication()->bind(TransactionRepositoryContract::class, TransactionRepository::class);
    }
}
