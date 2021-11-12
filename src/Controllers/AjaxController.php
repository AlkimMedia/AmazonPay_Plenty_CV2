<?php

namespace AmazonPayCheckout\Controllers;

use AmazonPayCheckout\Contracts\TransactionRepositoryContract;
use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Templates\Twig;

class AjaxController extends Controller
{
    use LoggingTrait;

    /**
     * @var Response
     */
    public $response;

    public function __construct(Response $response)
    {
        parent::__construct();
        $this->response = $response;
    }

    public function createCheckoutSession(Twig $twig)
    {
        $this->log(__CLASS__, __METHOD__, 'start', '👩 create checkout session');
        /** @var \AmazonPayCheckout\Helpers\ApiHelper $apiHelper */
        $apiHelper = pluginApp(ApiHelper::class);
        $response = [];
        try {
            $response['amazonCheckoutSessionId'] = $apiHelper->createCheckoutSession();
        } catch (Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'error', $e->getMessage());
        }
        $this->log(__CLASS__, __METHOD__, 'checkoutsessionid', '', $response);
        $this->response->json($response, 200, ['Content-Type: application/json']);
        return $twig->render('AmazonPayCheckout::content.output', ['output' => json_encode($response)]);
    }

    public function getTable(Twig $twig, TransactionRepositoryContract $transactionRepository)
    {
        $transactions = $transactionRepository->getTransactions([['id', '>', 0]]);
        $html = '<table>';
        foreach ($transactions as $transaction) {
            $html .= '<tr>';
            foreach ($transaction as $k => $v) {
                $html .= '<td>' . $v . '</td>';
            }
            $html .= '</tr>';
        }

        return $twig->render('AmazonPayCheckout::content.output', ['output' => $html]);
    }

}