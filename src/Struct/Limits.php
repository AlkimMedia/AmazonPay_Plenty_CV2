<?php

namespace AmazonPayCheckout\Struct;

class Limits extends StructBase {

    /**
     * @var \AmazonPayCheckout\Struct\AmountLimit
     */
    public $amountLimit;

    /**
     * @var \AmazonPayCheckout\Struct\AmountBalance
     */
    public $amountBalance;

    /**
     * @return \AmazonPayCheckout\Struct\AmountLimit
     */
    public function getAmountLimit()
    {
        return $this->amountLimit;
    }

    /**
     * @param \AmazonPayCheckout\Struct\AmountLimit $amountLimit
     *
     * @return Limits
     */
    public function setAmountLimit($amountLimit)
    {
        $this->amountLimit = $amountLimit;

        return $this;
    }

    /**
     * @return \AmazonPayCheckout\Struct\AmountBalance
     */
    public function getAmountBalance()
    {
        return $this->amountBalance;
    }

    /**
     * @param \AmazonPayCheckout\Struct\AmountBalance $amountBalance
     *
     * @return Limits
     */
    public function setAmountBalance($amountBalance)
    {
        $this->amountBalance = $amountBalance;

        return $this;
    }


}