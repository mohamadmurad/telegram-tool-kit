<?php

namespace TelegramKit\Contracts;

use Monolog\LogRecord;
use TelegramKit\Support\FormattedMessage;

interface MessageFormatter
{
    /**
     * Format a Monolog record for Telegram delivery.
     *
     * @param  array<string, mixed>  $config
     */
    public function format(LogRecord $record, array $config): FormattedMessage;

    /**
     * The Telegram `parse_mode` this formatter produces output for,
     * e.g. 'html' or 'MarkdownV2'.
     */
    public function parseMode(): string;
}
