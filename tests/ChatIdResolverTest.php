<?php

use TelegramKit\Support\ChatIdResolver;

it('returns a plain string chat id unchanged regardless of level', function () {
    expect(ChatIdResolver::resolve('-100123', 'debug'))->toBe('-100123');
});

it('routes a level to its configured chat id', function () {
    $chatIds = ['critical' => '-100critical', 'default' => '-100default'];

    expect(ChatIdResolver::resolve($chatIds, 'critical'))->toBe('-100critical')
        ->and(ChatIdResolver::resolve($chatIds, 'CRITICAL'))->toBe('-100critical');
});

it('falls back to default when the level has no explicit chat id', function () {
    $chatIds = ['critical' => '-100critical', 'default' => '-100default'];

    expect(ChatIdResolver::resolve($chatIds, 'debug'))->toBe('-100default');
});

it('throws when no chat id and no default are configured for a level', function () {
    ChatIdResolver::resolve(['critical' => '-100critical'], 'debug');
})->throws(InvalidArgumentException::class);
