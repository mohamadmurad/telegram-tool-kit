<?php

namespace TelegramKit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use TelegramKit\Jobs\Concerns\HandlesTelegramRateLimits;
use TelegramKit\Support\FormattedMessage;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, HandlesTelegramRateLimits, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        private readonly string $botToken,
        private readonly string $chatId,
        private readonly string $header,
        private readonly string $body,
        private readonly string $parseMode,
    ) {}

    public function handle(): void
    {
        $this->sendWithRetry(
            $this->botToken,
            $this->chatId,
            new FormattedMessage($this->header, $this->body),
            $this->parseMode
        );
    }
}
