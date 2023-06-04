# Telegram Logger

## install via composer

```
composer require mohamadmurad/telegram-logger
```

## publish the config

```
php artisan vendor:publish --provider="TelegramLogger\TelegramLoggerServiceProvider"
```

## add this channel to logging.php config file

```php
   'telegram' => [
            'driver' => 'custom',
            'via' => \TelegramLogger\TelegramLogger::class,
            'level' => 'debug',
        ],
```


if you want to send notification every composer load add this script to your composer.json

```json
{
  "scripts": {
    "post-autoload-dump": [
      "@php artisan telegramLogger:sendTelegramAutoLoadNotification"
    ]
  }
}
```