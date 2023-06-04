<?php

namespace TelegramKit\Console;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class sendTelegramAutoLoadNotification extends Command
{
    protected $signature = 'TelegramKit:sendTelegramAutoLoadNotification';

    protected $description = 'send Telegram AutoLoad Notification';

    public function handle()
    {
        $this->info('Sending...');
        try {
            $telegramApi = new Api(config('TelegramKit.bot_token'));

            $response = $telegramApi->sendMessage([
                'chat_id' => config('TelegramKit.chat_id'),
                'parse_mode' => 'html',
                'text' => $this->getMessage(),
            ]);
        } catch (Exception $exception) {
            Log::channel('single')->error($exception->getMessage());
        }


    }


    private function getMessage()
    {
        $this->get_current_git_commit();
        $name = '<b>App Name</b>: ' . config('app.name');
        $level_name = '<b>Type</b>: (Composer load)';
        $url = '<b>URL</b>: ' . config('app.url');

        $date = "<b>Time</b>: " . Carbon::now()->format('Y-m-d H:i:s');

        return sprintf("%s\n%s\n%s\n%s\n\n%s", $level_name, $name, $url,$date, $this->get_current_git_commit());
    }

    function get_current_git_commit($branch = 'master')
    {

        $commitHash ='<b>Commit Hash:</b> ' . trim(exec('git log --pretty="%h" -n1 HEAD'));
        $commitSubject = '<b>Commit Message:</b> ' . trim(exec('git log --pretty="%s" -n1 HEAD'));
        $committerName = '<b>Committer Name:</b> ' . trim(exec('git log --pretty="%cn" -n1 HEAD'));
        $commitDate = '<b>Commit Date:</b> ' . (new \DateTime(trim(exec('git log -n1 --pretty=%ci HEAD'))))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');



        return sprintf("%s\n%s\n%s\n%s", $committerName, $commitSubject, $commitHash, $commitDate);

    }
}