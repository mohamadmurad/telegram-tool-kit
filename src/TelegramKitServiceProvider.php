<?php

namespace TelegramKit;

use Illuminate\Support\ServiceProvider;
use TelegramKit\Console\sendTelegramAutoLoadNotification;

class TelegramKitServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'TelegramKit');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('TelegramKit.php'),
            ], 'config');

            $this->commands([
                sendTelegramAutoLoadNotification::class,
            ]);

        }
    }
}