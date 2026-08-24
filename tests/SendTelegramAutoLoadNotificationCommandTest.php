<?php

use Illuminate\Support\Facades\Log;

it('skips sending when bot_token/chat_id are not configured', function () {
    config(['TelegramKit.bot_token' => null, 'TelegramKit.chat_id' => null]);

    $this->artisan('TelegramKit:sendTelegramAutoLoadNotification')
        ->expectsOutputToContain('skipping deploy notification')
        ->assertExitCode(0);
});

it('logs locally instead of calling Telegram when mock_mode is enabled', function () {
    config(['TelegramKit.mock_mode' => true]);

    Log::shouldReceive('channel')->with('single')->andReturnSelf();
    Log::shouldReceive('info')->once()->with(Mockery::pattern('/telegram-tool-kit \(mock\)/'));

    $this->artisan('TelegramKit:sendTelegramAutoLoadNotification')
        ->expectsOutputToContain('Mock mode enabled')
        ->assertExitCode(0);
});
