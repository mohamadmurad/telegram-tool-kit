<?php

namespace TelegramLogger;

use Illuminate\Support\ServiceProvider;
use TelegramLogger\Console\sendTelegramAutoLoadNotification;

class TelegramLoggerServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'telegramLogger');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('telegramLogger.php'),
            ], 'config');

            $this->commands([
                sendTelegramAutoLoadNotification::class,
            ]);

        }
    }
}