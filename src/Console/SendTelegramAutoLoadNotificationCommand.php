<?php

namespace TelegramKit\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramKit\Support\FormattedMessage;
use TelegramKit\TelegramMessageSender;

class SendTelegramAutoLoadNotificationCommand extends Command
{
    protected $signature = 'TelegramKit:sendTelegramAutoLoadNotification';

    protected $description = 'send Telegram AutoLoad Notification';

    public function handle(): void
    {
        $config = config('TelegramKit', []);

        if (empty($config['bot_token']) || empty($config['chat_id'])) {
            $this->warn('telegram-tool-kit: bot_token/chat_id are not configured — skipping deploy notification.');

            return;
        }

        $this->info('Sending...');

        $message = new FormattedMessage($this->buildHeader(), $this->buildCommitBody());

        if ($config['mock_mode'] ?? false) {
            Log::channel('single')->info('telegram-tool-kit (mock): '.$message->header."\n".$message->body);
            $this->info('Mock mode enabled — logged locally instead of sending to Telegram.');

            return;
        }

        try {
            $sender = new TelegramMessageSender(new Api($config['bot_token']));
            $sender->send((string) $config['chat_id'], $message, 'html');
            $this->info('Sent.');
        } catch (TelegramSDKException $exception) {
            Log::channel('single')->error('telegram-tool-kit: '.$exception->getMessage());
            $this->error('Failed to send deploy notification: '.$exception->getMessage());
        }
    }

    private function buildHeader(): string
    {
        $lines = [
            '<b>Type</b>: (Composer load)',
            '<b>App Name</b>: '.htmlspecialchars((string) config('app.name'), ENT_QUOTES, 'UTF-8'),
            '<b>URL</b>: '.htmlspecialchars((string) config('app.url'), ENT_QUOTES, 'UTF-8'),
            '<b>Time</b>: '.Carbon::now()->format('Y-m-d H:i:s'),
        ];

        return implode("\n", $lines);
    }

    private function buildCommitBody(): string
    {
        $commit = $this->currentGitCommit();

        if ($commit === null) {
            return 'No git commit information available (no .git directory, or exec() is disabled).';
        }

        return sprintf(
            "Branch: %s\nCommitter: %s\nMessage: %s\nHash: %s\nDate: %s",
            $commit['branch'],
            $commit['committer'],
            $commit['message'],
            $commit['hash'],
            $commit['date']
        );
    }

    /**
     * @return array{branch: string, committer: string, message: string, hash: string, date: string}|null
     */
    private function currentGitCommit(): ?array
    {
        if (! function_exists('exec') || ! is_dir(base_path('.git'))) {
            return null;
        }

        $branch = $this->exec('git rev-parse --abbrev-ref HEAD');
        $hash = $this->exec('git log --pretty=%h -n1 HEAD');

        if ($branch === null || $hash === null) {
            return null;
        }

        return [
            'branch' => $branch,
            'committer' => $this->exec('git log --pretty=%cn -n1 HEAD') ?? 'unknown',
            'message' => $this->exec('git log --pretty=%s -n1 HEAD') ?? '',
            'hash' => $hash,
            'date' => $this->exec('git log --pretty=%ci -n1 HEAD') ?? '',
        ];
    }

    private function exec(string $command): ?string
    {
        exec($command.' 2>/dev/null', $output, $resultCode);

        if ($resultCode !== 0 || $output === []) {
            return null;
        }

        return trim(implode("\n", $output));
    }
}
