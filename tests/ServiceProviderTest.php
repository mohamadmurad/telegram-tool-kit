<?php

use TelegramKit\Console\SendTelegramAutoLoadNotificationCommand;
use TelegramKit\Formatters\DefaultMessageFormatter;

it('merges the package config', function () {
    expect(config('TelegramKit.formatter'))->toBe(DefaultMessageFormatter::class);
    expect(config('TelegramKit.parse_mode'))->toBe('html');
});

it('registers the deploy notification command', function () {
    expect(Artisan::all())->toHaveKey('TelegramKit:sendTelegramAutoLoadNotification');
});

it('resolves the deploy notification command to the renamed class', function () {
    expect(Artisan::all()['TelegramKit:sendTelegramAutoLoadNotification'])
        ->toBeInstanceOf(SendTelegramAutoLoadNotificationCommand::class);
});
