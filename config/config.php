<?php

use TelegramKit\Formatters\DefaultMessageFormatter;

return [
    // Telegram bot token used to send messages.
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    // Telegram chat id. Either a single chat id, or an array keyed by
    // Monolog level name (plus a 'default' key) to route different
    // levels to different chats, e.g.:
    // 'chat_id' => ['critical' => '-100...', 'default' => '-100...'],
    'chat_id' => env('TELEGRAM_CHAT_ID'),

    // Class implementing \TelegramKit\Contracts\MessageFormatter.
    'formatter' => DefaultMessageFormatter::class,

    // 'html' or 'markdownv2'.
    'parse_mode' => env('TELEGRAM_PARSE_MODE', 'html'),

    // Dispatch sends through the queue instead of sending inline.
    'queue' => env('TELEGRAM_LOG_QUEUE', false),
    'queue_connection' => env('TELEGRAM_LOG_QUEUE_CONNECTION'),
    'queue_name' => env('TELEGRAM_LOG_QUEUE_NAME'),

    // Collapse repeated identical log entries within this many seconds
    // into a single message. 0 disables debouncing.
    'debounce_seconds' => env('TELEGRAM_LOG_DEBOUNCE_SECONDS', 0),

    // Cache store used for debouncing. Null uses the app's default store.
    'cache_store' => env('TELEGRAM_LOG_CACHE_STORE'),

    // When true, messages are written to the 'single' log channel
    // instead of being sent to Telegram. Useful for local/testing envs.
    'mock_mode' => env('TELEGRAM_LOG_MOCK_MODE', false),

    // Include Monolog context/extra data (exception, request id, ...) in messages.
    'include_context' => env('TELEGRAM_LOG_INCLUDE_CONTEXT', true),

    // Include the authenticated user's id/email, if available. Off by
    // default for privacy.
    'include_user' => env('TELEGRAM_LOG_INCLUDE_USER', false),
];
