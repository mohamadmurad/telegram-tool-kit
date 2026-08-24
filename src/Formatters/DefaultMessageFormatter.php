<?php

namespace TelegramKit\Formatters;

use Carbon\Carbon;
use Monolog\LogRecord;
use TelegramKit\Contracts\MessageFormatter;
use TelegramKit\Support\FormattedMessage;
use Throwable;

class DefaultMessageFormatter implements MessageFormatter
{
    public function format(LogRecord $record, array $config): FormattedMessage
    {
        $header = $this->buildHeader($record, $config);
        $body = $this->buildBody($record, $config);

        return new FormattedMessage($header, $body);
    }

    public function parseMode(): string
    {
        return 'html';
    }

    protected function buildHeader(LogRecord $record, array $config): string
    {
        $lines = [
            '<b>Type</b>: ('.$this->escape($record['level_name']).')',
            '<b>App Name</b>: '.$this->escape((string) config('app.name')),
            '<b>Environment</b>: '.$this->escape((string) config('app.env')),
            '<b>Host</b>: '.$this->escape((string) gethostname()),
            '<b>URL</b>: '.$this->escape((string) config('app.url')),
        ];

        if (($config['include_user'] ?? false) && function_exists('auth') && auth()->check()) {
            $user = auth()->user();
            $lines[] = '<b>User</b>: '.$this->escape((string) ($user->id ?? '')).' / '.$this->escape((string) ($user->email ?? ''));
        }

        $lines[] = '<b>Time</b>: '.$this->escape(Carbon::parse($record['datetime'])->format('Y-m-d H:i:s'));

        return implode("\n", $lines);
    }

    protected function buildBody(LogRecord $record, array $config): string
    {
        $parts = [];

        $parts[] = 'Error Log:'."\n".$this->escape((string) $record['message']);

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
                $parts[] = "Context:\n".$this->escape(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        return implode("\n\n", $parts);
    }

    protected function formatException(Throwable $exception): string
    {
        return sprintf(
            "Exception: %s\nMessage: %s\nAt: %s:%d\n\nStack trace:\n%s",
            $this->escape(get_class($exception)),
            $this->escape($exception->getMessage()),
            $this->escape($exception->getFile()),
            $exception->getLine(),
            $this->escape($exception->getTraceAsString())
        );
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
