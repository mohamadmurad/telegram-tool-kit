<?php

use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message as MessageObject;
use TelegramKit\Jobs\SendTelegramNotification;
use TelegramKit\Notifications\Channels\TelegramChannel;
use TelegramKit\Notifications\Messages\TelegramMessage;
use TelegramKit\Notifications\TelegramNotification;
use TelegramKit\Support\TelegramSenderFactory;
use TelegramKit\TelegramMessageSender;

function fakeTelegramSender($api = null): TelegramMessageSender
{
    $api ??= Mockery::mock(Api::class);
    $api->shouldReceive('sendMessage')->andReturn(new MessageObject([]));

    return new TelegramMessageSender($api);
}

function bindSenderFactory(TelegramMessageSender $sender): void
{
    $factory = Mockery::mock(TelegramSenderFactory::class);
    $factory->shouldReceive('make')->with('test-token')->andReturn($sender);

    app()->instance(TelegramSenderFactory::class, $factory);
}

it('registers the telegram notification channel', function () {
    expect(app(ChannelManager::class)->driver('telegram'))->toBeInstanceOf(TelegramChannel::class);
});

it('sends an on-demand notification to the routed chat', function () {
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('sendMessage')->once()->withArgs(function (array $params) {
        return $params['chat_id'] === '999'
            && $params['text'] === 'Hello from Telegram'
            && $params['parse_mode'] === 'html';
    })->andReturn(new MessageObject([]));

    bindSenderFactory(new TelegramMessageSender($api));

    Notification::route('telegram', '999')->notify(new TelegramNotification('Hello from Telegram'));
});

it('uses the notifiable telegram route', function () {
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('sendMessage')->once()->withArgs(function (array $params) {
        return $params['chat_id'] === '555' && $params['text'] === 'For the user';
    })->andReturn(new MessageObject([]));

    bindSenderFactory(new TelegramMessageSender($api));

    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForTelegram(): string
        {
            return '555';
        }
    };

    $notifiable->notify(new TelegramNotification('For the user'));
});

it('prefers an explicit chat id on the notification', function () {
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('sendMessage')->once()->withArgs(function (array $params) {
        return $params['chat_id'] === '777';
    })->andReturn(new MessageObject([]));

    bindSenderFactory(new TelegramMessageSender($api));

    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForTelegram(): string
        {
            return '555';
        }
    };

    $notifiable->notify(new TelegramNotification('Direct', chatId: '777'));
});

it('falls back to the configured chat id when the notifiable has no route', function () {
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('sendMessage')->once()->withArgs(function (array $params) {
        return $params['chat_id'] === '12345';
    })->andReturn(new MessageObject([]));

    bindSenderFactory(new TelegramMessageSender($api));

    $notifiable = new class
    {
        use Notifiable;
    };

    $notifiable->notify(new TelegramNotification('Config fallback'));
});

it('skips sending when no chat id can be resolved', function () {
    config(['TelegramKit.chat_id' => null]);

    $api = Mockery::mock(Api::class);
    $api->shouldNotReceive('sendMessage');
    bindSenderFactory(new TelegramMessageSender($api));

    $notifiable = new class
    {
        use Notifiable;
    };

    $notifiable->notify(new TelegramNotification('Nowhere to go'));
});

it('warns and skips sending when the bot token is missing', function () {
    config(['TelegramKit.bot_token' => null]);

    Log::shouldReceive('channel')->with('single')->andReturnSelf();
    Log::shouldReceive('warning')->once()->with(Mockery::pattern('/bot_token is not configured/'));

    Notification::route('telegram', '999')->notify(new TelegramNotification('No token'));
});

it('logs notification text locally when mock mode is enabled', function () {
    config(['TelegramKit.mock_mode' => true]);

    Log::shouldReceive('channel')->with('single')->andReturnSelf();
    Log::shouldReceive('info')->once()->with(Mockery::pattern('/notification mock.*Mocked notification/s'));

    Notification::route('telegram', '999')->notify(new TelegramNotification('Mocked notification'));
});

it('joins TelegramMessage lines and applies a per-message parse mode', function () {
    $api = Mockery::mock(Api::class);
    $api->shouldReceive('sendMessage')->once()->withArgs(function (array $params) {
        return $params['parse_mode'] === 'MarkdownV2'
            && $params['text'] === "Title\nFirst\nSecond";
    })->andReturn(new MessageObject([]));

    bindSenderFactory(new TelegramMessageSender($api));

    $message = TelegramMessage::make('Title')->line('First')->line('Second')->parseMode('MarkdownV2');

    Notification::route('telegram', '999')->notify(new TelegramNotification($message));
});

it('dispatches a queued notification with the configured connection and queue', function () {
    Queue::fake();
    config([
        'TelegramKit.notifications.queue' => true,
        'TelegramKit.notifications.queue_connection' => 'redis',
        'TelegramKit.notifications.queue_name' => 'telegram-notifications',
    ]);

    Notification::route('telegram', '999')->notify(new TelegramNotification('Queued hello'));

    Queue::assertPushed(SendTelegramNotification::class, function (SendTelegramNotification $job) {
        return $job->connection === 'redis' && $job->queue === 'telegram-notifications';
    });
});

it('rejects notifications without a toTelegram method', function () {
    $notification = new class extends Illuminate\Notifications\Notification
    {
        public function via($notifiable): array
        {
            return ['telegram'];
        }
    };

    expect(fn () => Notification::route('telegram', '999')->notify($notification))
        ->toThrow(InvalidArgumentException::class, 'must define a toTelegram() method');
});
