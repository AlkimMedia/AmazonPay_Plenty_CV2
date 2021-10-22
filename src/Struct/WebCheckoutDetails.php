<?php

namespace AmazonPayCheckout\Struct;

class WebCheckoutDetails extends StructBase
{
    /**
     * @var string
     */
    public $checkoutReviewReturnUrl;

    /**
     * @var string
     */
    public $checkoutResultReturnUrl;

    /**
     * @var string
     */
    public $amazonPayRedirectUrl;

    /**
     * @return string
     */
    public function getCheckoutReviewReturnUrl()
    {
        return $this->checkoutReviewReturnUrl;
    }

    /**
     * @param string $checkoutReviewReturnUrl
     *
     * @return WebCheckoutDetails
     */
    public function setCheckoutReviewReturnUrl($checkoutReviewReturnUrl)
    {
        $this->checkoutReviewReturnUrl = $checkoutReviewReturnUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getCheckoutResultReturnUrl()
    {
        return $this->checkoutResultReturnUrl;
    }

    /**
     * @param string $checkoutResultReturnUrl
     *
     * @return WebCheckoutDetails
     */
    public function setCheckoutResultReturnUrl($checkoutResultReturnUrl)
    {
        $this->checkoutResultReturnUrl = $checkoutResultReturnUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getAmazonPayRedirectUrl()
    {
        return $this->amazonPayRedirectUrl;
    }

    /**
     * @param string $amazonPayRedirectUrl
     *
     * @return WebCheckoutDetails
     */
    public function setAmazonPayRedirectUrl($amazonPayRedirectUrl)
    {
        $this->amazonPayRedirectUrl = $amazonPayRedirectUrl;

        return $this;
    }

}