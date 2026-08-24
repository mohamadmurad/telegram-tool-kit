<?php

namespace TelegramKit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramKit\Support\FormattedMessage;
use TelegramKit\TelegramMessageSender;

class SendTelegramLogMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    private const DEFAULT_RETRY_DELAY = 30;

    public function __construct(
        private readonly string $botToken,
        private readonly string $chatId,
        private readonly string $header,
        private readonly string $body,
        private readonly string $parseMode,
    ) {}

    public function handle(): void
    {
        $sender = new TelegramMessageSender(new Api($this->botToken));

        try {
            $sender->send($this->chatId, new FormattedMessage($this->header, $this->body), $this->parseMode);
        } catch (TelegramResponseException $exception) {
            if ($exception->getHttpStatusCode() === 429) {
                $retryAfter = (int) ($exception->get('parameters')['retry_after'] ?? self::DEFAULT_RETRY_DELAY);
                $this->release($retryAfter);

                return;
            }

            $this->logFailure($exception);
        } catch (TelegramSDKException $exception) {
            $this->logFailure($exception);
        }
    }

    private function logFailure(TelegramSDKException $exception): void
    {
        Log::channel('single')->error('telegram-tool-kit: failed to deliver queued Telegram log message: '.$exception->getMessage());
    }
}
