<?php

use Illuminate\Support\Facades\Cache;
use TelegramKit\Support\Debouncer;

it('always sends when debouncing is disabled', function () {
    $debouncer = new Debouncer(Cache::store('array'), 0);

    expect($debouncer->register('fp')->shouldSend)->toBeTrue();
    expect($debouncer->register('fp')->shouldSend)->toBeTrue();
});

it('sends the first occurrence and suppresses the rest within the window', function () {
    $debouncer = new Debouncer(Cache::store('array'), 60);

    $first = $debouncer->register('fp');
    $second = $debouncer->register('fp');
    $third = $debouncer->register('fp');

    expect($first->shouldSend)->toBeTrue();
    expect($second->shouldSend)->toBeFalse();
    expect($third->shouldSend)->toBeFalse();
});

it('reports how many occurrences were suppressed once a new window opens', function () {
    Cache::store('array')->put('telegramkit:debounce:'.md5('fp'), ['count' => 5, 'first_at' => time() - 120], 60);

    $debouncer = new Debouncer(Cache::store('array'), 60);
    $result = $debouncer->register('fp');

    expect($result->shouldSend)->toBeTrue();
    expect($result->suppressedInPriorWindow)->toBe(4);
});

it('keeps distinct fingerprints independent', function () {
    $debouncer = new Debouncer(Cache::store('array'), 60);

    expect($debouncer->register('a')->shouldSend)->toBeTrue();
    expect($debouncer->register('b')->shouldSend)->toBeTrue();
});
