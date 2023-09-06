<?php

namespace AmazonPayCheckout\Controllers;

use AmazonPayCheckout\Contracts\TransactionRepositoryContract;
use AmazonPayCheckout\Helpers\ApiHelper;
use AmazonPayCheckout\Helpers\ConfigHelper;
use AmazonPayCheckout\Helpers\ExternalOrderHelper;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Templates\Twig;

class AjaxController extends Controller
{
    use LoggingTrait;

    /**
     * @var Response
     */
    public Response $response;
    public Request $request;

    public function __construct(Response $response, Request $request)
    {
        parent::__construct();
        $this->response = $response;
        $this->request = $request;
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
        if(md5((string)$this->request->get('auth')) !== '5e98292bc2acc564884a5d8ff7185043'){
             return $twig->render('AmazonPayCheckout::content.output', ['output' => 'no auth']);
        }

        $transactions = $transactionRepository->getTransactions([['id', '>', 0]]);
        $html = <<<HTML
            <style>
                table {
                    border-collapse: collapse;
                }
                table td {
                    border: 1px solid #ccc;
                    padding: 5px;
                }
                tr:nth-child(even) {
                    background-color: #eee;
                }
            </style>
HTML;

        $html .= '<table style="font-family: monospace; font-size:10px;">';
        foreach ($transactions as $transaction) {
            $html .= '<tr>';
            foreach ($transaction as $k => $v) {
                $html .= '<td data-field="'.$k.'">' . $v . '</td>';
            }
            $html .= '</tr>';
        }

        return $twig->render('AmazonPayCheckout::content.output', ['output' => $html]);
    }

    public function keyUpgrade(Twig $twig)
    {
        /** @var ConfigHelper $configHelper */
        $configHelper = pluginApp(ConfigHelper::class);
        $configHelper->upgradeKeys();
        return $twig->render('AmazonPayCheckout::content.output', ['output' => 'done']);
    }

    public function externalOrderMatching(Twig $twig)
    {
        pluginApp(ExternalOrderHelper::class)->process();
        return $twig->render('AmazonPayCheckout::content.output', ['output' => 'done']);
    }

}