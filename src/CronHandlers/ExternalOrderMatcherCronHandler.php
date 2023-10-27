<?php
namespace AmazonPayCheckout\CronHandlers;

use AmazonPayCheckout\Helpers\ExternalOrderHelper;
use AmazonPayCheckout\Traits\LoggingTrait;

class ExternalOrderMatcherCronHandler extends \Plenty\Modules\Cron\Contracts\CronHandler
{
    use LoggingTrait;


    public function handle()
    {
        $this->log(__CLASS__, __METHOD__, 'cron_started', '', []);
        pluginApp(ExternalOrderHelper::class)->process();

    }


}