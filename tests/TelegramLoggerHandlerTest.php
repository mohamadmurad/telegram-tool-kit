<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Monolog\Level;
use Monolog\LogRecord;
use TelegramKit\Jobs\SendTelegramLogMessage;
use TelegramKit\TelegramLoggerHandler;

function record(string $message = 'oops', array $context = [], Level $level = Level::Error): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: $level,
        message: $message,
        context: $context,
    );
}

it('warns once and disables delivery when bot_token/chat_id are missing', function () {
    Log::shouldReceive('channel')->with('single')->andReturnSelf();
    Log::shouldReceive('warning')->once()->with(Mockery::pattern('/bot_token\/chat_id are not configured/'));

    $handler = new TelegramLoggerHandler(['level' => 'debug', 'bot_token' => null, 'chat_id' => null]);
    $handler->handle(record());
});

it('writes to the local log instead of Telegram when mock_mode is enabled', function () {
    config(['TelegramKit.mock_mode' => true]);

    Log::shouldReceive('channel')->with('single')->andReturnSelf();
    Log::shouldReceive('info')->once()->with(Mockery::pattern('/telegram-tool-kit \(mock\)/'));

    $handler = new TelegramLoggerHandler(['level' => 'debug']);
    $handler->handle(record('mocked message'));
});

it('dispatches a queued job instead of sending inline when queue is enabled', function () {
    Queue::fake();
    config(['TelegramKit.queue' => true]);

    $handler = new TelegramLoggerHandler(['level' => 'debug']);
    $handler->handle(record('queued message'));

    Queue::assertPushed(SendTelegramLogMessage::class);
});

it('suppresses repeat occurrences within the debounce window', function () {
    config([
        'TelegramKit.mock_mode' => true,
        'TelegramKit.debounce_seconds' => 60,
        'TelegramKit.cache_store' => 'array',
    ]);

    Log::shouldReceive('channel')->with('single')->andReturnSelf();
    Log::shouldReceive('info')->once();

    $handler = new TelegramLoggerHandler(['level' => 'debug']);
    $handler->handle(record('repeated'));
    $handler->handle(record('repeated'));
    $handler->handle(record('repeated'));
});
