<?php

namespace TelegramKit\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TelegramKit\TelegramKitServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TelegramKitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Test App');
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');

        $app['config']->set('TelegramKit.bot_token', 'test-token');
        $app['config']->set('TelegramKit.chat_id', '12345');
    }
}
