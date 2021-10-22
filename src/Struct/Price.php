<?php

namespace AmazonPayCheckout\Struct;

class Price extends StructBase
{
    /**
     * @var string
     */
    public $amount;
    /**
     * @var string
     */
    public $currencyCode;

    /**
     * @return string
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param string $amount
     *
     * @return Price
     */
    public function setAmount($amount)
    {
        $this->amount = round($amount, 2);

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    /**
     * @param string $currencyCode
     *
     * @return Price
     */
    public function setCurrencyCode($currencyCode)
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

}
