<?php

namespace AmazonPayCheckout\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * Class Transaction
 *
 * @property int $id
 * @property string $orderReference
 * @property string $type
 * @property string $time
 * @property string $expiration
 * @property float $amount
 * @property float $amountRefunded
 * @property string $status
 * @property string $reference
 * @property string $amzId
 * @property string $lastChange
 * @property string $lastUpdate
 * @property string $order
 * @property int $paymentId
 * @property boolean $customerInformed
 * @property boolean $adminInformed
 * @property string $merchantId
 * @property string $mode
 * @property string $currency
 */

class Transaction extends Model
{
    const TRANSACTION_TYPE_CHARGE = 'Charge';
    const TRANSACTION_TYPE_CHARGE_PERMISSION = 'ChargePermission';
    const TRANSACTION_TYPE_REFUND = 'Refund';
    /**
     * @var int
     */
    public $id = 0;
    public $orderReference = '';
    public $type = '';
    public $time = '';
    public $expiration = '';
    public $amount = 0.0;
    public $amountRefunded = 0.0;
    public $status = '';
    public $reference = '';
    public $amzId = '';
    public $lastChange = '';
    public $lastUpdate = '';
    public $order = '';
    public $paymentId = 0;
    public $customerInformed = false;
    public $adminInformed = false;
    public $merchantId = '';
    public $mode = '';
    public $currency = '';

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'AmazonPayCheckout::Transaction';
    }
}