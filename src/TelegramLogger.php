<?php

namespace TelegramKit;

use Monolog\Logger;

class TelegramLogger
{
    /**
     * Create a custom Monolog instance.
     *
     * @return Logger
     */
    public function __invoke(array $config)
    {

        return new Logger(
            config('app.name'),
            [
                new TelegramLoggerHandler($config),
            ]
        );
    }
}
