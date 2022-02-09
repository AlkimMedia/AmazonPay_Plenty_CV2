<?php

namespace AmazonPayCheckout\Controllers;

use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Helpers\TransactionHelper;
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
                $this->transactionHelper->updateCharge($charge);
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
