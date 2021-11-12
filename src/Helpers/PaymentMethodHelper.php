<?php

namespace AmazonPayCheckout\Helpers;

use AmazonPayCheckout\Methods\PaymentMethod;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;

class PaymentMethodHelper
{
    private static $paymentMethodId = null;

    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function createMopIfNotExistsAndReturnId()
    {
        if(!isset(self::$paymentMethodId)) {
            $paymentMethodId = $this->getPaymentMethod();
            if ($paymentMethodId === false) {
                $paymentMethodData = [
                    'pluginKey' => PaymentMethod::PLUGIN_KEY,
                    'paymentKey' => PaymentMethod::PAYMENT_KEY,
                    'name' => PaymentMethod::PAYMENT_NAME
                ];

                $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
                $paymentMethodId = $this->getPaymentMethod();
            }
            self::$paymentMethodId = $paymentMethodId;
        }

        return self::$paymentMethodId;
    }



    public function getPaymentMethod()
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin(PaymentMethod::PLUGIN_KEY);
        if (!is_null($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod->paymentKey == PaymentMethod::PAYMENT_KEY) {
                    return $paymentMethod->id;
                }
            }
        }

        return false;
    }
}
