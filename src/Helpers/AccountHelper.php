<?php

namespace AmazonPayCheckout\Helpers;

use AmazonPayCheckout\Struct\Address;
use AmazonPayCheckout\Struct\CheckoutSession;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Plenty\Modules\Account\Contact\Contracts\ContactAddressRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract as WebshopContactRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\ExternalAuth\Contracts\ExternalAccessRepositoryContract;
use Plenty\Plugin\ExternalAuth\Services\ExternalAuthService;

class AccountHelper
{
    const EXTERNAL_AUTH_SLUG = 'AmazonCV2Login';
    use LoggingTrait;

    public function isLoggedIn()
    {
        /** @var \Plenty\Modules\Webshop\Contracts\ContactRepositoryContract $contactRepository */
        $contactRepository = pluginApp(WebshopContactRepositoryContract::class);
        return $contactRepository->getContactId() > 0;
    }

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function setAddresses($checkoutSession)
    {
        $this->setShippingAddress($checkoutSession);
        $this->setBillingAddress($checkoutSession);
    }

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function setShippingAddress($checkoutSession)
    {
        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);
        $this->log(__CLASS__, __METHOD__, 'start');
        $formattedShippingAddress = null;
        $shippingAddressObject = null;
        try {
            $email = null;
            if ($configHelper->getConfigurationValue('useEmailInShippingAddress') === 'true') {
                $email = $checkoutSession->buyer->email;
            }
            $formattedShippingAddress = $this->reformatAmazonAddress($checkoutSession->shippingAddress, $email);
            $shippingAddressObject = $this->createAddress($formattedShippingAddress, 'delivery');
            /** @var \Plenty\Modules\Frontend\Contracts\Checkout $checkout */
            $checkout = pluginApp(Checkout::class);
            $checkout->setCustomerShippingAddressId($shippingAddressObject->id);
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'error', 'setShippingAddress failed', [$e, $e->getMessage()], true);
        }

        $this->log(__CLASS__, __METHOD__, 'completed', '', [
            'shippingAddressArray' => $formattedShippingAddress,
            'shippingAddress' => $shippingAddressObject,
        ]);
    }

    /**
     * @param Address $address
     * @param null $email
     *
     * @return array[]
     */
    public function reformatAmazonAddress($address, $email = null)
    {
        $finalAddress = [
            'options' => [],
        ];
        $name = $address->name;
        $t = explode(' ', $name);
        $lastName = array_pop($t);
        $firstName = implode(' ', $t);


        $city = $address->city;
        $postcode = $address->postalCode;
        $countryCode = $address->countryCode;
        $phone = $address->phoneNumber;

        $finalAddress["name2"] = $firstName;
        $finalAddress["name3"] = $lastName;
        $this->addStreetAndCompany($finalAddress, $address);

        $finalAddress["postalCode"] = $postcode;
        $finalAddress["town"] = $city;
        $finalAddress["countryId"] = $this->getCountryId($countryCode);
        if (!empty($phone)) {
            $finalAddress["phone"] = $phone;
            $finalAddress["options"][] = [
                'typeId' => 4,
                'value' => $phone,
            ];

        }
        if (!empty($email)) {
            $finalAddress["email"] = $email;
            $finalAddress["options"][] = [
                'typeId' => 5,
                'value' => $email,
            ];
        }
        $this->log(__CLASS__, __METHOD__, 'completed', '', [$finalAddress]);

        return $finalAddress;
    }

    /**
     * @param array $finalAddress
     * @param Address $amazonAddress
     * @return void
     */
    protected function addStreetAndCompany(&$finalAddress, $amazonAddress)
    {
        if (in_array($amazonAddress->countryCode, ['DE', 'AT'])) {
            $this->_addStreetAndCompanyDA($finalAddress, $amazonAddress);

        } else {
            $this->_addStreetAndCompany($finalAddress, $amazonAddress);
        }
    }

    /**
     * @param array $finalAddress
     * @param Address $amazonAddress
     * @return void
     */
    protected function _addStreetAndCompanyDA(&$finalAddress, $amazonAddress)
    {
        $addressLine1 = trim($amazonAddress->addressLine1);
        $addressLine2 = trim($amazonAddress->addressLine2);
        $addressLine3 = trim($amazonAddress->addressLine3);
        $company = '';
        $street = '';
        $houseNumber = '';
        $additionalAddressPart = '';
        if ($addressLine2 !== '') {
            if (strlen($addressLine2) < 10 && preg_match('/^[0-9]+/', $addressLine2)) {
                $houseNumber = $addressLine2;
                $street = $addressLine1;
            } else {
                if (preg_match('/\d+/', substr($addressLine1, -2))) {
                    $street = trim($amazonAddress->addressLine1);
                    $company = trim($amazonAddress->addressLine2);
                } else {
                    $street = trim($amazonAddress->addressLine2);
                    $company = trim($amazonAddress->addressLine1);
                }
            }
        } elseif ($addressLine1 !== '') {
            $street = $addressLine1;
        }

        if (empty($houseNumber)) {
            $streetParts = explode(' ', $street);
            if (count($streetParts) > 1) {
                $_houseNumber = array_pop($streetParts);
                if ($this->isHouseNumber($_houseNumber)) {
                    $houseNumber = $_houseNumber;
                    $street = implode(' ', $streetParts);
                }
            }
        }

        if ($addressLine3 !== '') {
            $additionalAddressPart = $addressLine3;
        }

        $finalAddress['name1'] = trim($company);
        $finalAddress['address1'] = trim($street);
        $finalAddress['address2'] = trim($houseNumber);
        $finalAddress['address3'] = trim($additionalAddressPart);
    }

    /**
     * @param array $finalAddress
     * @param Address $amazonAddress
     * @return void
     */
    protected function _addStreetAndCompany(&$finalAddress, $amazonAddress)
    {
        //street might be in e.g. US format (123 Sth Street)
        $addressLine1 = trim($amazonAddress->addressLine1);
        $addressLine2 = trim($amazonAddress->addressLine2);
        $addressLine3 = trim($amazonAddress->addressLine3);
        $company = '';
        $street = '';
        $houseNumber = '';
        $additionalAddressPart = '';

        if ($addressLine1 !== '') {
            $street = $addressLine1;
            if ($addressLine2 !== '') {
                if ($this->isHouseNumber($addressLine1) || $this->isHouseNumber($addressLine2)) {
                    $street = $addressLine1 . ' ' . $addressLine2;
                } else {
                    $company = $addressLine2;
                }
            }
            if ($addressLine3 !== '') {
                $additionalAddressPart = $addressLine3;
            }
        } elseif ($addressLine2 !== '') {
            $street = $addressLine2;
            if ($addressLine3 !== '') {
                $company = $addressLine3;
            }
        } elseif ($addressLine3 !== '') {
            $street = $addressLine3;
        }

        $streetParts = explode(' ', $street);
        if (count($streetParts) > 1) {
            $_houseNumber = array_pop($streetParts);
            if ($this->isHouseNumber($_houseNumber)) {
                $houseNumber = $_houseNumber;
                $street = implode(' ', $streetParts);
            }
        }
        $finalAddress['name1'] = trim($company);
        $finalAddress['address1'] = trim($street);
        $finalAddress['address2'] = trim($houseNumber);
        $finalAddress['address3'] = trim($additionalAddressPart);
    }

    protected function isHouseNumber($string)
    {
        return preg_match('/^\d+\s*[a-z-]{0,3}$/i', $string);
    }

    protected function getCountryId($countryIso2)
    {
        /** @var CountryRepositoryContract $countryContract */
        $countryContract = pluginApp(CountryRepositoryContract::class);
        $country = $countryContract->getCountryByIso($countryIso2, 'isoCode2');
        $this->log(__CLASS__, __METHOD__, 'result', '', [$countryIso2, $country]);

        return (!empty($country) ? $country->id : 1);
    }

    protected function createAddress($data, $type)
    {
        $addressObject = null;
        $contactId = $this->getContactId();
        if ($contactId) {
            /** @var ContactAddressRepositoryContract $contactAddressRepo */
            $contactAddressRepository = pluginApp(ContactAddressRepositoryContract::class);
            try {
                $addressObject = $contactAddressRepository->createAddress($data, $contactId, ($type === 'delivery' ? AddressRelationType::DELIVERY_ADDRESS : AddressRelationType::BILLING_ADDRESS));
            } catch (Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'error', 'address creation for existing contact failed', [$e, $e->getMessage()], true);
            }
            $this->log(__CLASS__, __METHOD__, 'completed', 'address for existing contact created', [$data, $addressObject]);
        } else {
            /** @var AddressRepositoryContract $addressRepo */
            $addressRepository = pluginApp(AddressRepositoryContract::class);
            try {
                $addressObject = $addressRepository->createAddress($data);
            } catch (Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'error', 'address creation for guest failed', [$e, $e->getMessage()], true);
            }
            $this->log(__CLASS__, __METHOD__, 'completed', 'address for guest created', [$data, $addressObject]);
        }

        return $addressObject;
    }

    protected function getContactId()
    {
        $accountService = pluginApp(AccountService::class);

        return $accountService->getAccountContactId();
    }

    public function setBillingAddress($checkoutSession)
    {
        $this->log(__CLASS__, __METHOD__, 'start');
        $formattedBillingAddress = null;
        $billingAddressObject = null;
        try {
            $email = $checkoutSession->buyer->email;
            $formattedBillingAddress = $this->reformatAmazonAddress($checkoutSession->billingAddress, $email);
            $billingAddressObject = $this->createAddress($formattedBillingAddress, 'billing');
            /** @var \Plenty\Modules\Frontend\Contracts\Checkout $checkout */
            $checkout = pluginApp(Checkout::class);
            $checkout->setCustomerInvoiceAddressId($billingAddressObject->id);
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'error', 'setBillingAddress failed', [$e, $e->getMessage()], true);
        }

        $this->log(__CLASS__, __METHOD__, 'completed', '', [
            'shippingAddressArray' => $formattedBillingAddress,
            'shippingAddress' => $billingAddressObject,
        ]);
    }

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function createGuestSession($checkoutSession)
    {
        $loginResult = $this->createAccountSession($checkoutSession->buyer, false);
        if (!$loginResult['success']) {
            /** @var SessionStorageRepositoryContract $sessionStorageRepository */
            $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);
            $sessionStorageRepository->setSessionValue(SessionStorageRepositoryContract::GUEST_EMAIL, $checkoutSession->buyer->email);
        }
        $this->setAddresses($checkoutSession);
    }

    /**
     * @param \AmazonPayCheckout\Struct\Buyer $buyer
     *
     * @param bool $createAccount
     *
     * @return false[]
     */
    public function createAccountSession($buyer, $createAccount = true)
    {

        /** @var ExternalAccessRepositoryContract $externalAccessRepository */
        $externalAccessRepository = pluginApp(ExternalAccessRepositoryContract::class);

        /** @var ExternalAuthService $externalAuthService */
        $externalAuthService = pluginApp(ExternalAuthService::class);

        $this->log(__CLASS__, __METHOD__, 'start_login', '', [$buyer]);

        $return = [
            'success' => false,
        ];

        $email = $buyer->email;
        $name = $buyer->name;
        $amazonUserId = $buyer->buyerId;
        if (!empty($buyer->buyerId) && !empty($buyer->email)) {
            $doLogin = false;
            $externalAccessInfo = null;
            $contactIdByEmail = $this->getContactIdByEmail($email);
            try {
                $externalAccessInfo = $externalAccessRepository->findForTypeAndExternalId(self::EXTERNAL_AUTH_SLUG, $amazonUserId);
            } catch (Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'login_error_by_external_id', 'no external access info received', [$e, $e->getMessage(), self::EXTERNAL_AUTH_SLUG, $amazonUserId]);
            }
            $this->log(__CLASS__, __METHOD__, 'external_access_info', '', [$externalAccessInfo, self::EXTERNAL_AUTH_SLUG, $amazonUserId]);

            if (!is_object($externalAccessInfo) || empty($externalAccessInfo->contactId)) {
                if (empty($contactIdByEmail)) {
                    if ($createAccount) {
                        /** @var ContactRepositoryContract $contactRepository */
                        $contactRepository = pluginApp(ContactRepositoryContract::class);

                        $contactData = [
                            'typeId' => 1,
                            'fullName' => $name,
                            'email' => $email,
                            'referrerId' => 1,
                            'options' => [
                                [
                                    'typeId' => 2,
                                    'subTypeId' => 4,
                                    'value' => $email,
                                    'priority' => 0,
                                ],
                                [
                                    'typeId' => 8,
                                    'subTypeId' => 4,
                                    'value' => $name,
                                    'priority' => 0,
                                ],
                            ],
                        ];

                        $contact = $contactRepository->createContact($contactData);
                        $this->log(__CLASS__, __METHOD__, 'contact_created', '', [$contact, $contactData]);
                        $contactId = $contact->id;
                        $externalAccessCreatedInfo = $externalAccessRepository->create([
                            'contactId' => $contactId,
                            'accessType' => self::EXTERNAL_AUTH_SLUG,
                            'externalContactId' => $amazonUserId,
                        ]);
                        $this->log(__CLASS__, __METHOD__, 'external_access_created', '', [$externalAccessCreatedInfo]);
                        $doLogin = true;
                    }
                } else {


                    try {
                        $externalAccessInfoByContact = $externalAccessRepository->findForTypeAndContactId(self::EXTERNAL_AUTH_SLUG, $contactIdByEmail);
                    } catch (Exception $e) {
                        $this->log(__CLASS__, __METHOD__, 'external_access_info_error', 'no external access info received', [$e, $e->getMessage(), self::EXTERNAL_AUTH_SLUG, $amazonUserId]);
                    }

                    if (empty($externalAccessInfoByContact)) {
                        $externalAccessCreatedInfo = $externalAccessRepository->create([
                            'contactId' => $contactIdByEmail,
                            'accessType' => self::EXTERNAL_AUTH_SLUG,
                            'externalContactId' => $amazonUserId,
                        ]);
                        $this->log(__CLASS__, __METHOD__, 'external_access_created', '', [$externalAccessCreatedInfo]);
                    } else {
                        $amazonUserId = $externalAccessInfoByContact->externalContactId;
                        $this->log(__CLASS__, __METHOD__, 'login_hack', '', [$externalAccessInfoByContact]);
                    }
                    $doLogin = true;
                }

            } else {
                $this->log(__CLASS__, __METHOD__, 'do_login_1', '', []);
                $doLogin = true;
            }

            if ($doLogin) {
                $this->log(__CLASS__, __METHOD__, 'do_login_2', '', [$amazonUserId, self::EXTERNAL_AUTH_SLUG]);
                try {
                    $loginResult = $externalAuthService->logInWithExternalUserId($amazonUserId, self::EXTERNAL_AUTH_SLUG);
                } catch (Exception $e) {
                    $this->log(__CLASS__, __METHOD__, 'do_login_error', '', [$e->getMessage()]);
                }

                $this->log(__CLASS__, __METHOD__, 'login_completed', '', [$loginResult]);
                $return["success"] = true;
            }
        } else {
            $this->log(__CLASS__, __METHOD__, 'no_data', 'no amazon user data given for login', ['buyer' => $buyer], true);
        }
        $this->log(__CLASS__, __METHOD__, 'login_return', '', $return);

        return $return;
    }

    public function getContactIdByEmail($email)
    {
        /** @var ContactRepositoryContract $contactRepository */
        $contactRepository = pluginApp(ContactRepositoryContract::class);

        return $contactRepository->getContactIdByEmail($email);
    }

}