<?php

use Monolog\Level;
use Monolog\LogRecord;
use TelegramKit\Formatters\DefaultMessageFormatter;

function makeRecord(string $message = 'Something broke', array $context = [], Level $level = Level::Error): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable('2026-08-24 12:00:00'),
        channel: 'test',
        level: $level,
        message: $message,
        context: $context,
    );
}

it('escapes dynamic content so raw HTML in the log message cannot break parsing', function () {
    config(['app.name' => 'My <App> & Co']);

    $formatter = new DefaultMessageFormatter;
    $message = $formatter->format(makeRecord('<script>alert(1)</script> & "quoted"'), config('TelegramKit'));

    expect($message->header)->not->toContain('<App>')
        ->and($message->header)->toContain('My &lt;App&gt; &amp; Co')
        ->and($message->body)->not->toContain('<script>')
        ->and($message->body)->toContain('&lt;script&gt;');
});

it('includes exception class, message, and trace when present in context', function () {
    $exception = new RuntimeException('boom');

    $formatter = new DefaultMessageFormatter;
    $message = $formatter->format(makeRecord('failure', ['exception' => $exception]), config('TelegramKit'));

    expect($message->body)
        ->toContain('RuntimeException')
        ->toContain('boom')
        ->toContain('Stack trace');
});

it('omits context/extra details when include_context is disabled', function () {
    $formatter = new DefaultMessageFormatter;
    $config = array_merge(config('TelegramKit'), ['include_context' => false]);

    $message = $formatter->format(makeRecord('failure', ['exception' => new RuntimeException('boom')]), $config);

    expect($message->body)->not->toContain('RuntimeException');
});

it('reports html as its parse mode', function () {
    expect((new DefaultMessageFormatter)->parseMode())->toBe('html');
});
