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
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract as WebshopContactRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\ExternalAuth\Contracts\ExternalAccessRepositoryContract;
use Plenty\Plugin\ExternalAuth\Services\ExternalAuthService;

class AccountHelper
{
    const EXTERNAL_AUTH_SLUG = 'AmazonCV2';

    use LoggingTrait;

    public function __construct()
    {

    }

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
        $shippingAddressObject    = null;
        try {
            $email = null;
            if ($configHelper->getConfigurationValue('useEmailInShippingAddress') === 'true') {
                $email = $checkoutSession->buyer->email;
            }
            $formattedShippingAddress = $this->reformatAmazonAddress($checkoutSession->shippingAddress, $email);
            $shippingAddressObject    = $this->createAddress($formattedShippingAddress, 'delivery');
            /** @var \Plenty\Modules\Frontend\Contracts\Checkout $checkout */
            $checkout = pluginApp(Checkout::class);
            $checkout->setCustomerShippingAddressId($shippingAddressObject->id);
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'error', 'setShippingAddress failed', [$e, $e->getMessage()], true);
        }

        $this->log(__CLASS__, __METHOD__, 'completed', '', [
            'shippingAddressArray' => $formattedShippingAddress,
            'shippingAddress'      => $shippingAddressObject
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
            'options' => []
        ];
        $name         = $address->name;
        $t            = explode(' ', $name);
        $lastName     = array_pop($t);
        $firstName    = implode(' ', $t);

        if ($address->addressLine3) {
            $street  = trim($address->addressLine3);
            $company = trim($address->addressLine1 . ' ' . $address->addressLine2);
        } elseif ($address->addressLine2) {
            $street  = trim($address->addressLine2);
            $company = trim($address->addressLine1);
        } else {
            $company = '';
            $street  = trim($address->addressLine1);
        }
        $houseNo     = '';
        $streetParts = explode(' ', $street); //TODO: replace with preg_split('/[\s]+/', $street);
        if (count($streetParts) > 1) {
            $houseNoKey = max(array_keys($streetParts));
            if (strlen($streetParts[$houseNoKey]) <= 5) {
                $houseNo = $streetParts[$houseNoKey];
                unset($streetParts[$houseNoKey]);
                $street = implode(' ', $streetParts);
            }
        }
        $city        = $address->city;
        $postcode    = $address->postalCode;
        $countryCode = $address->countryCode;
        $phone       = $address->phoneNumber;

        $finalAddress["name1"]    = $company;
        $finalAddress["name2"]    = $firstName;
        $finalAddress["name3"]    = $lastName;
        $finalAddress["address1"] = $street;

        if (!empty($houseNo)) {
            $finalAddress["address2"] = $houseNo;
        }

        $finalAddress["postalCode"] = $postcode;
        $finalAddress["town"]       = $city;
        $finalAddress["countryId"]  = $this->getCountryId($countryCode);
        if (!empty($phone)) {
            $finalAddress["phone"]     = $phone;
            $finalAddress["options"][] = [
                'typeId' => 4,
                'value'  => $phone
            ];

        }
        if (!empty($email)) {
            $finalAddress["email"]     = $email;
            $finalAddress["options"][] = [
                'typeId' => 5,
                'value'  => $email
            ];
        }
        $this->log(__CLASS__, __METHOD__, 'completed', '', [$finalAddress]);

        return $finalAddress;
    }

    protected function getCountryId($countryIso2)
    {
        /** @var CountryRepositoryContract $countryContract */
        $countryContract = pluginApp(CountryRepositoryContract::class);
        $country         = $countryContract->getCountryByIso($countryIso2, 'isoCode2');
        $this->log(__CLASS__, __METHOD__, 'result', '', [$countryIso2, $country]);

        return (!empty($country) ? $country->id : 1);
    }

    protected function createAddress($data, $type)
    {
        $addressObject = null;
        $contactId     = $this->getContactId();
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
        $billingAddressObject    = null;
        try {
            $email                   = $checkoutSession->buyer->email;
            $formattedBillingAddress = $this->reformatAmazonAddress($checkoutSession->billingAddress, $email);
            $billingAddressObject    = $this->createAddress($formattedBillingAddress, 'billing');
            /** @var \Plenty\Modules\Frontend\Contracts\Checkout $checkout */
            $checkout = pluginApp(Checkout::class);
            $checkout->setCustomerInvoiceAddressId($billingAddressObject->id);
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'error', 'setBillingAddress failed', [$e, $e->getMessage()], true);
        }

        $this->log(__CLASS__, __METHOD__, 'completed', '', [
            'shippingAddressArray' => $formattedBillingAddress,
            'shippingAddress'      => $billingAddressObject
        ]);
    }

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function createGuestSession($checkoutSession)
    {
        $loginResult = $this->createAccountSession($checkoutSession->buyer, false);
        if(!$loginResult['success']) {
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
            'success' => false
        ];

        $email        = $buyer->email;
        $name         = $buyer->name;
        $amazonUserId = $buyer->buyerId;
        if (!empty($buyer->buyerId) && !empty($buyer->email)) {
            $doLogin            = false;
            $externalAccessInfo = null;
            try {
                $externalAccessInfo = $externalAccessRepository->findForTypeAndExternalId(self::EXTERNAL_AUTH_SLUG, $amazonUserId);
            } catch (Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'login_error', 'no external access info received', [$e, $e->getMessage(), self::EXTERNAL_AUTH_SLUG, $amazonUserId]);
            }
            $this->log(__CLASS__, __METHOD__, 'external_access_info', '', [$externalAccessInfo, self::EXTERNAL_AUTH_SLUG, $amazonUserId]);
            if (!is_object($externalAccessInfo) || empty($externalAccessInfo->contactId)) {
                $contactIdByEmail = $this->getContactIdByEmail($email);
                if (empty($contactIdByEmail)) {
                    if ($createAccount) {
                        /** @var ContactRepositoryContract $contactRepository */
                        $contactRepository = pluginApp(ContactRepositoryContract::class);

                        $contactData = [
                            'typeId'     => 1,
                            'fullName'   => $name,
                            'email'      => $email,
                            'referrerId' => 1,
                            'options'    => [
                                [
                                    'typeId'    => 2,
                                    'subTypeId' => 4,
                                    'value'     => $email,
                                    'priority'  => 0
                                ],
                                [
                                    'typeId'    => 8,
                                    'subTypeId' => 4,
                                    'value'     => $name,
                                    'priority'  => 0
                                ]
                            ]
                        ];

                        $contact = $contactRepository->createContact($contactData);
                        $this->log(__CLASS__, __METHOD__, 'contact_created', '', [$contact, $contactData]);
                        $contactId                 = $contact->id;
                        /*$externalAccessCreatedInfo = $externalAccessRepository->create([
                            'contactId'         => $contactId,
                            'accessType'        => self::EXTERNAL_AUTH_SLUG,
                            'externalContactId' => $amazonUserId,
                        ]);*/
                        //$this->log(__CLASS__, __METHOD__, 'external_access_created', '', [$externalAccessCreatedInfo]);
                        //$doLogin = true;
                    }
                } else {
                    /*$externalAccessCreatedInfo = $externalAccessRepository->create([
                        'contactId'         => $contactIdByEmail,
                        'accessType'        => self::EXTERNAL_AUTH_SLUG,
                        'externalContactId' => $amazonUserId,
                    ]);*/
                    //$this->log(__CLASS__, __METHOD__, 'external_access_created', '', [$externalAccessCreatedInfo]);
                    //$doLogin           = true;
                }

            } else {
                $doLogin = true;
            }

            if ($doLogin) {
                $loginResult = $externalAuthService->logInWithExternalUserId($amazonUserId, self::EXTERNAL_AUTH_SLUG);
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
