<?php

namespace TelegramKit\Formatters;

use Carbon\Carbon;
use Monolog\LogRecord;
use TelegramKit\Contracts\MessageFormatter;
use TelegramKit\Support\FormattedMessage;
use Throwable;

class MarkdownV2MessageFormatter implements MessageFormatter
{
    /**
     * Characters MarkdownV2 requires escaping outside of code blocks.
     * https://core.telegram.org/bots/api#markdownv2-style
     */
    private const SPECIAL_CHARS = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

    public function format(LogRecord $record, array $config): FormattedMessage
    {
        return new FormattedMessage(
            $this->buildHeader($record, $config),
            $this->buildBody($record, $config)
        );
    }

    public function parseMode(): string
    {
        return 'MarkdownV2';
    }

    protected function buildHeader(LogRecord $record, array $config): string
    {
        $lines = [
            '*Type*: ('.$this->escape($record['level_name']).')',
            '*App Name*: '.$this->escape((string) config('app.name')),
            '*Environment*: '.$this->escape((string) config('app.env')),
            '*Host*: '.$this->escape((string) gethostname()),
            '*Base Host*: '.$this->escape((string) config('app.url')),
            '*URL*: '.$this->escape($this->currentUrl()),
        ];

        if (($config['include_user'] ?? false) && function_exists('auth') && auth()->check()) {
            $user = auth()->user();
            $lines[] = '*User*: '.$this->escape((string) ($user->id ?? '')).' / '.$this->escape((string) ($user->email ?? ''));
        }

        $lines[] = '*Time*: '.$this->escape(Carbon::parse($record['datetime'])->format('Y-m-d H:i:s'));

        return implode("\n", $lines);
    }

    protected function buildBody(LogRecord $record, array $config): string
    {
        $parts = [];

        $parts[] = 'Error Log:'."\n".$this->escapeCode((string) $record['message']);

        $context = $record['context'] ?? [];

        if (($config['include_context'] ?? true) && isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $parts[] = $this->formatException($context['exception']);
            unset($context['exception']);
        }

        if (($config['include_context'] ?? true) && (! empty($context) || ! empty($record['extra']))) {
            $extra = array_filter([
                'context' => $context ?: null,
                'extra' => $record['extra'] ?: null,
            ]);

            if ($extra !== []) {
                $parts[] = "Context:\n".$this->escapeCode(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        return implode("\n\n", $parts);
    }

    protected function formatException(Throwable $exception): string
    {
        return sprintf(
            "Exception: %s\nMessage: %s\nAt: %s:%d\n\nStack trace:\n%s",
            $this->escapeCode(get_class($exception)),
            $this->escapeCode($exception->getMessage()),
            $this->escapeCode($exception->getFile()),
            $exception->getLine(),
            $this->escapeCode($exception->getTraceAsString())
        );
    }

    protected function currentUrl(): string
    {
        if (function_exists('request') && request()->server->has('REQUEST_URI')) {
            return request()->fullUrl();
        }

        return (string) config('app.url');
    }

    protected function escape(string $value): string
    {
        return str_replace(self::SPECIAL_CHARS, array_map(fn ($c) => '\\'.$c, self::SPECIAL_CHARS), $value);
    }

    /**
     * Inside a MarkdownV2 code block only "`" and "\" need escaping.
     */
    protected function escapeCode(string $value): string
    {
        return str_replace(['\\', '`'], ['\\\\', '\\`'], $value);
    }
}
