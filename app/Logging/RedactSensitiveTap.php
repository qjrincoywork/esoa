<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Log channel tap that registers the {@see RedactSensitiveData} processor on a
 * channel's underlying Monolog instance. Wire it via the channel's `tap` array
 * in config/logging.php.
 */
class RedactSensitiveTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new RedactSensitiveData());
    }
}
