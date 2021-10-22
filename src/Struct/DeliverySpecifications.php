<?php

namespace AmazonPayCheckout\Struct;

class DeliverySpecifications extends StructBase
{
    /**
     * @var array
     */
    public $specialRestrictions;
    /**
     * @var AddressRestrictions
     */
    public $addressRestrictions;

    /**
     * @return array
     */
    public function getSpecialRestrictions()
    {
        return $this->specialRestrictions;
    }

    /**
     * @param array $specialRestrictions
     *
     * @return DeliverySpecifications
     */
    public function setSpecialRestrictions($specialRestrictions)
    {
        $this->specialRestrictions = $specialRestrictions;

        return $this;
    }

    /**
     * @return \AmazonPayCheckout\Struct\AddressRestrictions
     */
    public function getAddressRestrictions()
    {
        return $this->addressRestrictions;
    }

    /**
     * @param \AmazonPayCheckout\Struct\AddressRestrictions $addressRestrictions
     *
     * @return DeliverySpecifications
     */
    public function setAddressRestrictions($addressRestrictions)
    {
        $this->addressRestrictions = $addressRestrictions;

        return $this;
    }


}
