# Telegram Tool Kit

A Telegram bot toolkit for Laravel:

- Forward Laravel `Log::` calls to Telegram — exception/stack-trace enrichment, per-level
  chat routing, queueing, burst debouncing, and multi-channel support.
- Send user notifications through the `telegram` Laravel notification channel, with
  on-demand messages, queued delivery, and chat-id routing.
- Send a deploy notification when `composer install`/`update` runs.

## Install

```
composer require mohamadmurad/telegram-tool-kit
```

## Publish the config (recommended)

```
php artisan vendor:publish --provider="TelegramKit\TelegramKitServiceProvider"
```

> Publishing isn't strictly required — the package merges its own defaults via
> `mergeConfigFrom`. But Laravel **skips that merge when `php artisan config:cache` has
> run**, which is a very common production/deploy step. If you cache config and haven't
> published `config/TelegramKit.php`, `bot_token`/`chat_id` will resolve to `null` and
> delivery will silently disable itself (with one warning logged to the `single`
> channel). Publish the config if you cache config in production.

## Add the channel to `logging.php`

```php
'telegram' => [
    'driver' => 'custom',
    'via' => \TelegramKit\TelegramLogger::class,
    'level' => 'debug',
],
```

Then either register it as your default channel, or add it to a `stack` channel
alongside `single`/`daily`.

## Configuration (`config/TelegramKit.php`)

| Key | Default | Description |
|---|---|---|
| `bot_token` | `env('TELEGRAM_BOT_TOKEN')` | Your bot's token. |
| `chat_id` | `env('TELEGRAM_CHAT_ID')` | A chat id, or a per-level array (see below). |
| `formatter` | `DefaultMessageFormatter::class` | Class implementing `TelegramKit\Contracts\MessageFormatter`. |
| `parse_mode` | `html` | `html` or `markdownv2`. |
| `queue` | `false` | Dispatch sends through the queue instead of sending inline. |
| `queue_connection` / `queue_name` | `null` | Where the queued job runs. |
| `debounce_seconds` | `0` | Collapse repeats of the same error within N seconds into one message. `0` disables it. |
| `cache_store` | `null` (default store) | Cache store used for debouncing. |
| `mock_mode` | `false` | Log locally instead of calling Telegram — handy for local/testing envs. |
| `include_context` | `true` | Include exception details and Monolog context/extra in messages. |
| `include_user` | `false` | Include the authenticated user's id/email, if available. |

Any of these can also be overridden **per log channel**, since the handler merges the
channel's own config (from `logging.php`) over the package defaults — see
[Multiple bots/channels](#multiple-botschannels).

### Per-level chat routing

```php
// config/TelegramKit.php
'chat_id' => [
    'emergency' => env('TELEGRAM_CHAT_ONCALL'),
    'critical' => env('TELEGRAM_CHAT_ONCALL'),
    'default' => env('TELEGRAM_CHAT_DEV'),
],
```

Any level without an explicit entry falls back to `default`.

### Multiple bots/channels

Define more than one `logging.php` channel using `TelegramLogger::class` — each channel's
own array is merged over the package defaults, so different channels can use different
bots, chats, or formatters:

```php
'errors' => [
    'driver' => 'custom',
    'via' => \TelegramKit\TelegramLogger::class,
    'level' => 'error',
    'bot_token' => env('TELEGRAM_ERRORS_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_ERRORS_CHAT_ID'),
],

'audit' => [
    'driver' => 'custom',
    'via' => \TelegramKit\TelegramLogger::class,
    'level' => 'info',
    'bot_token' => env('TELEGRAM_AUDIT_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_AUDIT_CHAT_ID'),
],
```

### Queueing

```php
'queue' => true,
'queue_connection' => 'redis',
'queue_name' => 'telegram-logs',
```

When enabled, sends are dispatched as `TelegramKit\Jobs\SendTelegramLogMessage` instead
of blocking the request. On a Telegram 429 (rate limit), the job releases itself with the
`retry_after` delay Telegram reports, up to 5 attempts.

### Debouncing bursts

```php
'debounce_seconds' => 60,
```

The first occurrence of a given error (same level + message + exception class) in a
60-second window sends immediately. Repeats within that window are suppressed; the
message that opens the next window notes how many were suppressed, e.g.
`+46 similar in the last 60s`.

### Custom formatter

Implement `TelegramKit\Contracts\MessageFormatter` and point `formatter` at your class:

```php
class MyFormatter implements \TelegramKit\Contracts\MessageFormatter
{
    public function format(\Monolog\LogRecord $record, array $config): \TelegramKit\Support\FormattedMessage
    {
        return new \TelegramKit\Support\FormattedMessage('short header', 'long body');
    }

    public function parseMode(): string
    {
        return 'html';
    }
}
```

`header` is always sent whole and should stay short; `body` (the log message, exception,
stack trace, context) can be arbitrary length — it's chunked on safe boundaries and, if
it's still too long after a few chunks, sent as a `.txt` file attachment instead.

### Mock mode

```php
'mock_mode' => true,
```

Writes formatted messages to the `single` log channel instead of calling Telegram. Useful
for local development so you don't spam a real chat.

## Telegram notification channel

The package also registers a `telegram` Laravel notification channel, so you can notify
users (or any chat id) without touching the logging setup.

### Quick, on-demand message

```php
use Illuminate\Support\Facades\Notification;
use TelegramKit\Notifications\TelegramNotification;

Notification::route('telegram', '123456789')
    ->notify(new TelegramNotification('Your order is ready.'));
```

`TelegramNotification` accepts an optional parse mode and an explicit chat id:

```php
new TelegramNotification('*Done*', parseMode: 'MarkdownV2', chatId: '123456789');
```

### Custom notification class

```php
use Illuminate\Notifications\Notification;
use TelegramKit\Notifications\Messages\TelegramMessage;

class OrderShipped extends Notification
{
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::make('Order shipped')
            ->line('Order #'.$notifiable->order_id)
            ->line('Thanks for shopping with us!');
    }
}
```

`toTelegram()` may also return a plain string or a `FormattedMessage`. Long content is
chunked safely across multiple Telegram messages.

Add a route on the notifiable (or use `Notification::route('telegram', $id)`):

```php
// app/Models/User.php
public function routeNotificationForTelegram(): ?string
{
    return $this->telegram_chat_id;
}
```

```php
$user->notify(new OrderShipped);
```

If the notifiable has no route, the notification falls back to `TelegramKit.chat_id` from
the config (with the `default` key used when it's a per-level array). When neither exists,
the channel skips delivery silently. `routeNotificationFor('telegram')` and a
`telegramChatId($notifiable)` method on the notification take precedence over the fallback.

### Notification config

| Key | Default | Description |
|---|---|---|
| `notifications.parse_mode` | `null` | `html` or `MarkdownV2`. Falls back to `parse_mode`, then `html`. |
| `notifications.queue` | `false` | Send through `SendTelegramNotification` instead of inline. |
| `notifications.queue_connection` / `queue_name` | `null` | Where the queued notification runs. |

The global `bot_token`, `chat_id`, and `mock_mode` settings apply to notifications too.
In mock mode notification text is written to the `single` log channel instead of Telegram.

## Deploy notification command

Send a message (app name, URL, git branch/commit) whenever `composer install`/`update`
runs, by adding this to your app's `composer.json`:

```json
{
  "scripts": {
    "post-autoload-dump": [
      "@php artisan TelegramKit:sendTelegramAutoLoadNotification"
    ]
  }
}
```

This is opt-in — the package doesn't wire it up for you. It gracefully skips the
git-commit section if `.git` isn't present (common on deploy artifacts) or if `exec()` is
disabled, and skips sending entirely (with a console warning) if `bot_token`/`chat_id`
aren't configured.

## Development

```
composer install
composer test     # Pest
composer lint     # Pint (check)
composer format   # Pint (fix)
composer analyse  # PHPStan
```
