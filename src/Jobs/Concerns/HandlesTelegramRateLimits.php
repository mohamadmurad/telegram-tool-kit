<?php

namespace TelegramKit\Jobs\Concerns;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramKit\Support\FormattedMessage;
use TelegramKit\TelegramMessageSender;

trait HandlesTelegramRateLimits
{
    private const DEFAULT_RETRY_DELAY = 30;

    abstract public function release($delay = 0);

    private function sendWithRetry(string $botToken, string $chatId, FormattedMessage $message, string $parseMode): void
    {
        try {
            (new TelegramMessageSender(new Api($botToken)))->send($chatId, $message, $parseMode);
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
        Log::channel('single')->error(
            'telegram-tool-kit: failed to deliver queued Telegram message: '.$exception->getMessage()
        );
    }
}
