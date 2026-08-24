<?php

namespace TelegramKit;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramKit\Contracts\MessageFormatter;
use TelegramKit\Formatters\DefaultMessageFormatter;
use TelegramKit\Jobs\SendTelegramLogMessage;
use TelegramKit\Support\ChatIdResolver;
use TelegramKit\Support\Debouncer;
use TelegramKit\Support\FormattedMessage;
use Throwable;

class TelegramLoggerHandler extends AbstractProcessingHandler
{
    /**
     * Per-channel config (from logging.php) merged over the package's
     * global config('TelegramKit') defaults — this is what allows
     * multiple Telegram log channels with different bots/chats.
     *
     * @var array<string, mixed>
     */
    private array $config;

    private bool $configured;

    public function __construct(array $config)
    {
        $level = Logger::toMonologLevel($config['level'] ?? 'debug');
        parent::__construct($level, true);

        $this->config = array_merge(config('TelegramKit', []), $config);
        $this->configured = ! empty($this->config['bot_token']) && ! empty($this->config['chat_id']);

        if (! $this->configured) {
            Log::channel('single')->warning(
                'telegram-tool-kit: bot_token/chat_id are not configured — Telegram log delivery is disabled. '.
                "This commonly happens when 'php artisan config:cache' has run without the config being published first."
            );
        }
    }

    protected function write(LogRecord $record): void
    {
        if (! $this->configured) {
            return;
        }

        try {
            $formatter = $this->resolveFormatter();
            $message = $formatter->format($record, $this->config);

            $chatId = ChatIdResolver::resolve($this->config['chat_id'], $record['level_name']);

            $debounceSeconds = (int) ($this->config['debounce_seconds'] ?? 0);

            if ($debounceSeconds > 0) {
                $debouncer = new Debouncer(Cache::store($this->config['cache_store'] ?? null), $debounceSeconds);
                $result = $debouncer->register($this->fingerprint($record));

                if (! $result->shouldSend) {
                    return;
                }

                if ($result->suppressedInPriorWindow > 0) {
                    $message = new FormattedMessage(
                        $message->header."\n<b>Note</b>: +{$result->suppressedInPriorWindow} similar in the last {$debounceSeconds}s",
                        $message->body
                    );
                }
            }

            if ($this->config['mock_mode'] ?? false) {
                Log::channel('single')->info('telegram-tool-kit (mock): '.$message->header."\n".$message->body);

                return;
            }

            $this->dispatch($chatId, $message, $formatter->parseMode());
        } catch (Exception $exception) {
            Log::channel('single')->error('telegram-tool-kit: '.$exception->getMessage());
        }
    }

    private function dispatch(string $chatId, FormattedMessage $message, string $parseMode): void
    {
        if ($this->config['queue'] ?? false) {
            $dispatched = SendTelegramLogMessage::dispatch($this->config['bot_token'], $chatId, $message->header, $message->body, $parseMode);

            if (! empty($this->config['queue_connection'])) {
                $dispatched->onConnection($this->config['queue_connection']);
            }

            if (! empty($this->config['queue_name'])) {
                $dispatched->onQueue($this->config['queue_name']);
            }

            return;
        }

        try {
            $sender = new TelegramMessageSender(new Api($this->config['bot_token']));
            $sender->send($chatId, $message, $parseMode);
        } catch (TelegramSDKException $exception) {
            Log::channel('single')->error('telegram-tool-kit: Telegram API error while sending log message: '.$exception->getMessage());
        }
    }

    private function resolveFormatter(): MessageFormatter
    {
        $class = $this->config['formatter'] ?? DefaultMessageFormatter::class;

        return app($class);
    }

    private function fingerprint(LogRecord $record): string
    {
        $exceptionClass = '';

        if (isset($record->context['exception']) && $record->context['exception'] instanceof Throwable) {
            $exceptionClass = get_class($record->context['exception']);
        }

        return $record['level_name'].'|'.$record->message.'|'.$exceptionClass;
    }
}
