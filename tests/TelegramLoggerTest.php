<?php

use Monolog\Logger;
use TelegramKit\TelegramLogger;
use TelegramKit\TelegramLoggerHandler;

it('builds a Monolog logger backed by the Telegram handler', function () {
    $logger = (new TelegramLogger)(['level' => 'debug']);

    expect($logger)->toBeInstanceOf(Logger::class);
    expect($logger->getHandlers())->toHaveCount(1);
    expect($logger->getHandlers()[0])->toBeInstanceOf(TelegramLoggerHandler::class);
});
