<?php

namespace AmazonPayCheckout\Struct;

class StatusDetails extends StructBase
{
    const OPEN = 'Open';
    const COMPLETED = 'Completed';
    const AUTHORIZED = 'Authorized';
    const CAPTURED = 'Captured';
    const REFUND_INITIATED = 'RefundInitiated';
    const REFUNDED = 'Refunded';
    const CANCELED = 'Canceled';
    const DECLINED = 'Declined';
    const AUTHORIZATION_INITIATED = 'AuthorizationInitiated';

    const REASON_DECLINED = 'Declined';
    const REASON_BUYER_CANCELED = 'BuyerCanceled';

    /**
     * @var string
     */
    public $state;
    /**
     * @var string
     */
    public $reasonCode;

    /**
     * @var string
     */
    public $reasonDescription;
    /**
     * @var string
     */
    public $lastUpdatedTimestamp;
    /**
     * @var string
     */
    public $dateTime;

    /**
     * @return string
     */
    public function getReasonDescription()
    {
        return $this->reasonDescription;
    }

    /**
     * @param string $reasonDescription
     *
     * @return StatusDetails
     */
    public function setReasonDescription($reasonDescription)
    {
        $this->reasonDescription = $reasonDescription;

        return $this;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $state
     *
     * @return StatusDetails
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return string
     */
    public function getReasonCode()
    {
        return $this->reasonCode;
    }

    /**
     * @param string $reasonCode
     *
     * @return StatusDetails
     */
    public function setReasonCode($reasonCode)
    {
        $this->reasonCode = $reasonCode;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastUpdatedTimestamp()
    {
        return $this->lastUpdatedTimestamp;
    }

    /**
     * @param string $lastUpdatedTimestamp
     *
     * @return StatusDetails
     */
    public function setLastUpdatedTimestamp($lastUpdatedTimestamp)
    {
        $this->lastUpdatedTimestamp = $lastUpdatedTimestamp;

        return $this;
    }

    /**
     * @return string
     */
    public function getDateTime()
    {
        return $this->dateTime;
    }

    /**
     * @param string $dateTime
     *
     * @return StatusDetails
     */
    public function setDateTime($dateTime)
    {
        $this->dateTime = $dateTime;

        return $this;
    }

}