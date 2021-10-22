<?php

namespace AmazonPayCheckout\Migrations;

use AmazonPayCheckout\Models\Transaction;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreateTransactionTable
{

    public function run(Migrate $migrate)
    {
        $migrate->createTable(Transaction::class);
        $migrate->updateTable(Transaction::class);
    }
}