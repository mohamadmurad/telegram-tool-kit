<?php

namespace TelegramKit\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Stringable;
use TelegramKit\Jobs\SendTelegramNotification;
use TelegramKit\Notifications\Messages\TelegramMessage;
use TelegramKit\Support\ChatIdResolver;
use TelegramKit\Support\FormattedMessage;
use TelegramKit\Support\TelegramSenderFactory;

class TelegramChannel
{
    public function __construct(private TelegramSenderFactory $senderFactory) {}

    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            throw new InvalidArgumentException(
                'Telegram notification ['.get_class($notification).'] must define a toTelegram() method.'
            );
        }

        $chatId = $this->resolveChatId($notifiable, $notification);

        if ($chatId === null) {
            return;
        }

        $config = config('TelegramKit', []);

        if (empty($config['bot_token'])) {
            Log::channel('single')->warning(
                'telegram-tool-kit: bot_token is not configured — Telegram notification delivery is disabled.'
            );

            return;
        }

        $message = $notification->toTelegram($notifiable);
        $formatted = $this->toFormattedMessage($message);
        $parseMode = $this->parseMode($message, $config);

        if ($config['mock_mode'] ?? false) {
            Log::channel('single')->info(
                'telegram-tool-kit (notification mock): '.$formatted->header.$formatted->body
            );

            return;
        }

        if ($config['notifications']['queue'] ?? false) {
            $dispatched = SendTelegramNotification::dispatch(
                (string) $config['bot_token'],
                $chatId,
                $formatted->header,
                $formatted->body,
                $parseMode
            );

            if (! empty($config['notifications']['queue_connection'])) {
                $dispatched->onConnection($config['notifications']['queue_connection']);
            }

            if (! empty($config['notifications']['queue_name'])) {
                $dispatched->onQueue($config['notifications']['queue_name']);
            }

            return;
        }

        $this->senderFactory->make((string) $config['bot_token'])->send($chatId, $formatted, $parseMode);
    }

    private function resolveChatId($notifiable, Notification $notification): ?string
    {
        if (method_exists($notification, 'telegramChatId')) {
            $chatId = $notification->telegramChatId($notifiable);

            if ($chatId !== null && $chatId !== '') {
                return (string) $chatId;
            }
        }

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $chatId = $notifiable->routeNotificationFor('telegram', $notification);

            if ($chatId !== null && $chatId !== '') {
                return (string) $chatId;
            }
        }

        if (method_exists($notifiable, 'routeNotificationForTelegram')) {
            $chatId = $notifiable->routeNotificationForTelegram();

            if ($chatId !== null && $chatId !== '') {
                return (string) $chatId;
            }
        }

        $configured = config('TelegramKit.chat_id');

        if (is_array($configured)) {
            return ChatIdResolver::resolve($configured, 'notification');
        }

        return $configured === null || $configured === '' ? null : (string) $configured;
    }

    private function toFormattedMessage(string|TelegramMessage|FormattedMessage|Stringable $message): FormattedMessage
    {
        return match (true) {
            $message instanceof FormattedMessage => $message,
            $message instanceof TelegramMessage => new FormattedMessage('', $message->content),
            default => new FormattedMessage('', (string) $message),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function parseMode(string|TelegramMessage|FormattedMessage|Stringable $message, array $config): string
    {
        return ($message instanceof TelegramMessage ? $message->parseMode : null)
            ?: ($config['notifications']['parse_mode'] ?? null)
            ?: ($config['parse_mode'] ?? null)
            ?: 'html';
    }
}
