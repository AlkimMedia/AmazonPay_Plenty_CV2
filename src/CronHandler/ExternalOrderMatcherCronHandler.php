<?php

use AmazonLoginAndPay\Models\AmzTransaction;
use AmazonPayCheckout\Traits\LoggingTrait;

class ExternalOrderMatcherCronHandler extends \Plenty\Modules\Cron\Contracts\CronHandler
{
    use LoggingTrait;


    public function handle()
    {
        $this->log(__CLASS__, __METHOD__, 'cron_started', '', []);



    }


}