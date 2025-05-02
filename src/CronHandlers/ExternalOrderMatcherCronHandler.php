<?php

namespace AmazonPayCheckout\CronHandlers;

use AmazonPayCheckout\Helpers\ExternalOrderHelper;
use AmazonPayCheckout\Traits\LoggingTrait;
use Plenty\Modules\Cron\Contracts\CronHandler;

class ExternalOrderMatcherCronHandler extends CronHandler
{
    use LoggingTrait;

    public function handle()
    {
        $this->log(__CLASS__, __METHOD__, 'cron_started', '', []);
        pluginApp(ExternalOrderHelper::class)->process();
    }
}