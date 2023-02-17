<?php

namespace AmazonPayCheckout\Controllers;

use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Helpers\ConfigHelper;
use AmazonPayCheckout\Helpers\TransactionHelper;
use AmazonPayCheckout\Models\Transaction;
use AmazonPayCheckout\Traits\LoggingTrait;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Templates\Twig;

class IpnController extends Controller
{
    use LoggingTrait;

    /**
     * @var Response
     */
    private $response;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var ApiHelper
     */
    private $apiHelper;
    /**
     * @var TransactionHelper
     */
    private $transactionHelper;

    public function __construct(Response $response, Request $request, ApiHelper $apiHelper, TransactionHelper $transactionHelper)
    {
        parent::__construct();
        $this->response = $response;
        $this->request = $request;
        $this->apiHelper = $apiHelper;
        $this->transactionHelper = $transactionHelper;
    }

    public function index(Twig $twig)
    {
        $requestBody = $this->request->getContent();
        $requestData = json_decode($requestBody, true);
        $message = json_decode($requestData['Message'], true);

        $this->log(__CLASS__, __METHOD__, 'request_data', '', [$requestData, $message]);

        if (!$this->isIpnValid($requestBody)) {
            $this->log(__CLASS__, __METHOD__, 'invalid_ipn', '', [$requestBody], true);
            return $twig->render('AmazonPayCheckout::content.output', ['output' => 'invalid ipn']);
        }

        switch ($message['ObjectType']) {
            case 'CHARGE':
                $chargeId = $message['ObjectId'];
                $charge = $this->apiHelper->getCharge($chargeId);
                if (!empty($charge->chargePermissionId)) {
                    $chargePermission = $this->apiHelper->getChargePermission($charge->chargePermissionId);

                    // this is to prevent IPN race conditions
                    // we check for an existing charge transaction entity, which should be created after completeCheckoutSession
                    // if no one appears after some time, we still register the charge just in case
                    // this is only applied to payments initiated with this plugin

                    if (strpos($chargePermission->merchantMetadata->customInformation, ConfigHelper::CUSTOM_INFORMATION_STRING) !== false) {
                        $sleepCounter = 0;
                        while ($sleepCounter++ < 5) {
                            $existingChargeTransactionEntity = $this->transactionHelper->getTransaction($charge->chargeId, Transaction::TRANSACTION_TYPE_CHARGE);
                            if (!empty($existingChargeTransactionEntity->id)) {
                                $this->log(__CLASS__, __METHOD__, 'no_race');
                                break;
                            }
                            $this->log(__CLASS__, __METHOD__, 'race', 'caught race condition ' . $sleepCounter, [
                                'charge' => $charge,
                            ]);
                            sleep(1);
                        }
                    }
                    $this->transactionHelper->updateCharge($charge);
                } else {
                    $this->log(__CLASS__, __METHOD__, 'data_error', 'charge data seems to be incomplete', ['charge' => $charge]);
                }
                break;
            case 'REFUND':
                $this->log(__CLASS__, __METHOD__, 'refund', '', []);
                $refundId = $message['ObjectId'];
                $refund = $this->apiHelper->getRefund($refundId);
                $this->log(__CLASS__, __METHOD__, 'refund_details', '', [$refund]);
                $this->transactionHelper->updateRefund($refund);
                break;
            default:
                $this->log(__CLASS__, __METHOD__, 'unknown', 'unknwon ipn type', [$message]);
                break;
        }
        return $twig->render('AmazonPayCheckout::content.output', ['output' => 'OK']);
    }

    protected function isIpnValid($messageBody): bool
    {
        /** @var LibraryCallContract $libCaller */
        $libCaller = pluginApp(LibraryCallContract::class);

        $result = $libCaller->call(
            'AmazonPayCheckout::ipn_validator',
            [
                'messageBody' => $messageBody,
            ]
        );
        $this->log(__CLASS__, __METHOD__, 'ipn_validator_result', '', ['message' => $messageBody, 'result' => $result]);

        return (bool)$result['isValid'];
    }

}