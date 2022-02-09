<?php

namespace AmazonPayCheckout\Traits;

use Plenty\Plugin\Log\Loggable;

trait LoggingTrait
{
    use Loggable;

    public function log(string $class, string $method, string $shortId, string $msg = '', $arg = [], bool $error = false): void
    {
        //$error  = true; //massive debugging only
        $logger = $this->getLogger($class . '_' . $method . '_' . $shortId);
        if ($error) {
            $logger->error($msg, $arg);
        } else {
            if (!is_array($arg)) {
                $arg = [$arg];
            }
            $arg[] = $msg;
            $logger->info('AmazonPayCheckout::Logger.infoCaption', $arg);
        }
    }
}
