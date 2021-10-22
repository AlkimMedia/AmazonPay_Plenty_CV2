<?php

namespace AmazonPayCheckout\Struct;

class CheckoutSession extends StructBase
{

    /**
     * @var string
     */
    public $checkoutSessionId;

    /**
     * @var WebCheckoutDetails
     */
    public $webCheckoutDetails;
    /**
     * @var string
     */
    public $productType;

    /**
     * @var PaymentDetails
     */
    public $paymentDetails;

    /**
     * @var MerchantMetadata
     */
    public $merchantMetadata;

    /**
     * @var string
     */
    public $platformId;

    /**
     * @var ProviderMetadata
     */
    public $providerMetadata;

    /**
     * @var Buyer
     */
    public $buyer;

    /**
     * @var ShippingAddress
     */
    public $shippingAddress;

    /**
     * @var BillingAddress
     */
    public $billingAddress;

    public $supplementaryData;

    /**
     * @var string
     */

    public $reasonCode;
    /**
     * @var string
     */
    public $message;

    /**
     * @var ???
     */
    public $paymentPreferences;

    /**
     * @var StatusDetails
     */
    public $statusDetails;

    /**
     * @var ???
     */
    public $constraints;

    /**
     * @var string
     */
    public $creationTimestamp;

    /**
     * @var string
     */
    public $expirationTimestamp;
    /**
     * @var string
     */
    public $chargePermissionId;
    /**
     * @var string
     */
    public $chargeId;
    /**
     * @var string
     */
    public $storeId;

    /**
     * @var DeliverySpecifications
     */
    public $deliverySpecifications;

    /**
     * @var string
     */
    public $releaseEnvironment;

    /**
     * @var string
     */
    public $chargePermissionType;

}