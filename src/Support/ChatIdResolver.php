<?php

namespace TelegramKit\Support;

use InvalidArgumentException;

/**
 * Resolves the Telegram chat id to send to for a given Monolog level.
 *
 * `chat_id` config may be a plain string/int (used for every level) or
 * an array keyed by level name (e.g. 'critical', 'debug') plus a
 * 'default' key used for any level not explicitly listed.
 */
class ChatIdResolver
{
    /**
     * @param  string|int|array<string, string|int>  $chatId
     */
    public static function resolve(string|int|array $chatId, string $levelName): string
    {
        if (! is_array($chatId)) {
            return (string) $chatId;
        }

        $levelName = strtolower($levelName);

        if (isset($chatId[$levelName]) && $chatId[$levelName] !== '') {
            return (string) $chatId[$levelName];
        }

        if (isset($chatId['default']) && $chatId['default'] !== '') {
            return (string) $chatId['default'];
        }

        throw new InvalidArgumentException(
            "No Telegram chat_id configured for log level [{$levelName}] and no 'default' fallback was provided."
        );
    }
}
