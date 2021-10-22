<?php

namespace AmazonPayCheckout\Contracts;

use AmazonPayCheckout\Models\Transaction;

interface TransactionRepositoryContract
{
    /**
     * Add a new transaction
     *
     * @param array $data
     *
     * @return Transaction
     */
    public function createTransaction(array $data);

    /**
     * List all transactions
     *
     * @param array $criteria
     *
     * @return Transaction[]
     */
    public function getTransactions(array $criteria);

    /**
     * Update transaction
     *
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public function updateTransaction(Transaction $transaction);

    /**
     * Save transaction
     *
     * @param Transaction $transaction
     *
     * @return Transaction
     */
    public function saveTransaction(Transaction $transaction);

}