<?php

namespace TelegramKit;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use TelegramKit\Console\SendTelegramAutoLoadNotificationCommand;
use TelegramKit\Notifications\Channels\TelegramChannel;

class TelegramKitServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'TelegramKit');
    }

    public function boot()
    {
        $this->registerNotificationChannel();

        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('TelegramKit.php'),
            ], 'config');

            $this->commands([
                SendTelegramAutoLoadNotificationCommand::class,
            ]);
        }
    }

    private function registerNotificationChannel(): void
    {
        $register = function (ChannelManager $manager): void {
            $manager->extend('telegram', fn () => $this->app->make(TelegramChannel::class));
        };

        if ($this->app->resolved(ChannelManager::class)) {
            $register($this->app->make(ChannelManager::class));

            return;
        }

        $this->app->afterResolving(ChannelManager::class, $register);
    }
}
