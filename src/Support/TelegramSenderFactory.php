<?php

namespace TelegramKit\Support;

use Telegram\Bot\Api;
use TelegramKit\TelegramMessageSender;

class TelegramSenderFactory
{
    public function make(string $botToken): TelegramMessageSender
    {
        return new TelegramMessageSender(new Api($botToken));
    }
}
