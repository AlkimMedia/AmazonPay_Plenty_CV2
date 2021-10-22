<?php
namespace AmazonPayCheckout\Struct;

class Charge extends StructBase
{
    /**
     * @var string
     */
    public $chargeId;
    /**
     * @var string
     */
    public $chargePermissionId;
    /**
     * @var ChargeAmount
     */
    public $chargeAmount;
    /**
     * @var CaptureAmount
     */
    public $captureAmount;
    /**
     * @var RefundedAmount
     */
    public $refundedAmount;
    /**
     * @var string
     */
    public $softDescriptor;
    /**
     * @var bool
     */
    public $captureNow;
    /**
     * @var bool
     */
    public $canHandlePendingAuthorization;
    /**
     * @var ProviderMetadata
     */
    public $providerMetadata;
    /**
     * @var string
     */
    public $creationTimestamp;
    /**
     * @var string
     */
    public $expirationTimestamp;
    /**
     * @var StatusDetails
     */
    public $statusDetails;
    /**
     * @var ConvertedAmount
     */
    public $convertedAmount;
    /**
     * @var double
     */
    public $conversionRate;
    /**
     * @var string
     */
    public $releaseEnvironment;


}
