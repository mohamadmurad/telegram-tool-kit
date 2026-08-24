<?php

use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Objects\Message as MessageObject;
use TelegramKit\Support\FormattedMessage;
use TelegramKit\TelegramMessageSender;

it('sends a single message when the body is small', function () {
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('sendMessage')->once()->withArgs(function (array $params) {
        return $params['chat_id'] === '123'
            && $params['parse_mode'] === 'html'
            && str_contains($params['text'], '<pre>hello</pre>');
    })->andReturn(new MessageObject([]));

    (new TelegramMessageSender($api))->send('123', new FormattedMessage('HEADER', 'hello'), 'html');
});

it('never splits an HTML chunk inside an entity', function () {
    $api = Mockery::mock(Api::class);
    $seenTexts = [];

    $api->shouldReceive('sendMessage')->andReturnUsing(function (array $params) use (&$seenTexts) {
        $seenTexts[] = $params['text'];

        return new MessageObject([]);
    });

    // Body built entirely of escaped '&amp;' entities so any naive fixed-offset
    // split is virtually guaranteed to land inside one unless the guard works.
    $body = str_repeat('&amp;', 1000);

    (new TelegramMessageSender($api))->send('123', new FormattedMessage('HEADER', $body), 'html');

    expect($seenTexts)->not->toBeEmpty();

    foreach ($seenTexts as $text) {
        preg_match('/<pre>(.*)<\/pre>/s', $text, $m);
        expect($m)->not->toBeEmpty();

        // A broken split would produce a stray '&' not part of a full entity.
        expect(preg_match('/&(?!amp;)/', $m[1]))->toBe(0);
    }
});

it('falls back to a document attachment when the body needs too many chunks', function () {
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('sendMessage')->times(2)->andReturn(new MessageObject([]));
    $api->shouldReceive('sendDocument')->once()->withArgs(function (array $params) {
        return $params['document'] instanceof InputFile;
    })->andReturn(new MessageObject([]));

    $body = str_repeat("line of stack trace\n", 2000);

    (new TelegramMessageSender($api))->send('123', new FormattedMessage('HEADER', $body), 'html');
});
