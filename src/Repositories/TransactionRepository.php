<?php

namespace AmazonPayCheckout\Repositories;

use AmazonPayCheckout\Contracts\TransactionRepositoryContract;
use AmazonPayCheckout\Models\Transaction;
use AmazonPayCheckout\Traits\LoggingTrait;
use Exception;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class TransactionRepository implements TransactionRepositoryContract
{
use LoggingTrait;
    /**
     * @param array $data
     *
     * @return Transaction
     */
    public function createTransaction(array $data)
    {
        /** @var Transaction $transaction */
        $transaction                   = pluginApp(Transaction::class);
        $transaction->orderReference   = (string)$data["orderReference"];
        $transaction->type             = (string)$data["type"];
        $transaction->status           = (string)$data["status"];
        $transaction->reference        = (string)$data["reference"];
        $transaction->expiration       = (string)$data["expiration"];
        $transaction->time             = (string)$data["time"];
        $transaction->amzId            = (string)$data["amzId"];
        $transaction->lastChange       = (string)$data["lastChange"];
        $transaction->lastUpdate       = (string)$data["lastUpdate"];
        $transaction->customerInformed = (bool)$data["customerInformed"];
        $transaction->adminInformed    = (bool)$data["adminInformed"];
        $transaction->merchantId       = (string)$data["merchantId"];
        $transaction->mode             = (string)$data["mode"];
        $transaction->amount           = (float)$data["amount"];
        $transaction->amountRefunded   = (float)$data["amountRefunded"];
        $transaction->order            = (string)$data["order"];
        $transaction->paymentId        = (int)$data["paymentId"];
        $transaction->currency         = (string)$data["currency"];

        return $this->saveTransaction($transaction);
    }

    /**
     * @param Transaction $transaction
     *
     * @return Transaction|null
     */
    public function saveTransaction(Transaction $transaction)
    {
        /**
         * @var DataBase $database
         */
        $database = pluginApp(DataBase::class);
        $response = null;
        try {
            /** @var Transaction $response */
            $response = $database->save($transaction);
        } catch (Exception $e) {
            //TODO log
        }

        return $response;
    }

    /**
     * @param array $criteria
     *
     * @return Transaction[]
     */
    public function getTransactions(array $criteria)
    {
        /** @var DataBase $database */
        $database = pluginApp(DataBase::class);
        $stmt     = $database->query(Transaction::class);

        foreach ($criteria as $c) {
            $stmt->where($c[0], $c[1], $c[2]);
        }

        $result = $stmt->get();
        $this->log(__CLASS__, __METHOD__, 'result', '', ['criteria'=>$criteria, 'result'=>$result]);
        return $result;
    }

    public function updateTransaction(Transaction $transaction)
    {
        $transaction->lastUpdate = date('Y-m-d H:i:s');

        return $this->saveTransaction($transaction);
    }

}