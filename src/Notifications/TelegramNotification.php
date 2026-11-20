<?php

namespace TelegramKit\Notifications;

use Illuminate\Notifications\Notification;
use TelegramKit\Notifications\Messages\TelegramMessage;

class TelegramNotification extends Notification
{
    public function __construct(
        private readonly string|TelegramMessage $message,
        private readonly ?string $parseMode = null,
        private readonly string|int|null $chatId = null,
    ) {}

    public function via($notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $message = $this->message instanceof TelegramMessage
            ? $this->message
            : TelegramMessage::make($this->message);

        if ($this->parseMode !== null) {
            $message->parseMode($this->parseMode);
        }

        return $message;
    }

    public function telegramChatId($notifiable): string|int|null
    {
        return $this->chatId;
    }
}
