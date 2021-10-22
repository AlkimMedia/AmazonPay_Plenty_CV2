<?php

namespace AmazonPayCheckout\Struct;

class ProviderMetadata extends StructBase
{
    /**
     * @var string
     */
    public $providerReferenceId;

    /**
     * @return string
     */
    public function getProviderReferenceId()
    {
        return $this->providerReferenceId;
    }

    /**
     * @param string $providerReferenceId
     *
     * @return ProviderMetadata
     */
    public function setProviderReferenceId($providerReferenceId)
    {
        $this->providerReferenceId = $providerReferenceId;

        return $this;
    }


}
