<?php

namespace AmazonPayCheckout\Providers;

use Plenty\Plugin\RouteServiceProvider as RouteServiceProviderBase;
use Plenty\Plugin\Routing\Router;

class RouteServiceProvider extends RouteServiceProviderBase
{
    public function map(Router $router)
    {
        $router->post('payment/amazon-pay-get-session', 'AmazonPayCheckout\Controllers\AjaxController@createCheckoutSession');
        $router->post('amazon-pay-get-session', 'AmazonPayCheckout\Controllers\AjaxController@createCheckoutSession');
        $router->get('payment/amazon-pay-return', 'AmazonPayCheckout\Controllers\FrontendController@processReturn');
        $router->get('payment/amazon-pay-checkout-start', 'AmazonPayCheckout\Controllers\FrontendController@checkoutStart');
        $router->get('payment/amazon-pay-place-order', 'AmazonPayCheckout\Controllers\FrontendController@placeOrder');
        $router->get('payment/amazon-pay-existing-order', 'AmazonPayCheckout\Controllers\FrontendController@payOrder');
        $router->get('payment/amazon-pay-existing-order-process', 'AmazonPayCheckout\Controllers\FrontendController@payOrderProcess');
        $router->get('payment/amazon-pay-sign-in', 'AmazonPayCheckout\Controllers\FrontendController@signIn');
        $router->get('payment/amazon-pay-unset-payment-method', 'AmazonPayCheckout\Controllers\FrontendController@unsetPaymentMethod');
        $router->post('payment/amazon-pay-ipn', 'AmazonPayCheckout\Controllers\IpnController@index');

        //$router->match(['post', 'get'], 'amazon-connect-accounts', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonConnectAccountsAction');


        //This is for debugging only:
        $router->get('payment/amazon-pay-get-table', 'AmazonPayCheckout\Controllers\AjaxController@getTable'); //TODO
    }
}
