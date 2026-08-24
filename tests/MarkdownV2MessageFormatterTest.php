<?php

use Monolog\Level;
use Monolog\LogRecord;
use TelegramKit\Formatters\MarkdownV2MessageFormatter;

it('escapes MarkdownV2 special characters in the header', function () {
    config(['app.name' => 'App.Name! (v2)']);

    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-08-24 12:00:00'),
        channel: 'test',
        level: Level::Error,
        message: 'plain message',
    );

    $message = (new MarkdownV2MessageFormatter)->format($record, config('TelegramKit'));

    expect($message->header)->toContain('App\\.Name\\! \\(v2\\)');
});

it('reports MarkdownV2 as its parse mode', function () {
    expect((new MarkdownV2MessageFormatter)->parseMode())->toBe('MarkdownV2');
});
