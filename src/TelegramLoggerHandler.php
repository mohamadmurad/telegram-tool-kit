<?php


namespace TelegramLogger;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

class TelegramLoggerHandler extends AbstractProcessingHandler
{

    /**
     * @var array
     */
    private array $config;
    /**
     * @var string
     */
    private string $appName;
    /**
     * @var string
     */
    private string $appEnv;
    /**
     * @var string
     */
    private string $appUrl;
    /**
     * @var Api
     */
    private Api $telegramApi;

    public function __construct(array $config)
    {
        $level = Logger::toMonologLevel($config['level']);
        parent::__construct($level, true);
        $this->config = $config;

        // define variables for text message
        $this->appName = config('app.name');
        $this->appEnv = config('app.env');
        $this->appUrl = config('app.url');
        $this->telegramApi = new Api(config('telegramLogger.bot_token'));

    }

    /**
     * @throws TelegramSDKException
     */
    protected function write($record): void
    {


        try {
            $message = $this->messageFormatter($record);
            $chunks = str_split($message, 4096);


            foreach ($chunks as $chunk) {
                $this->sendTelegramMessage($chunk);
            }
        } catch (Exception $exception) {
            \Log::channel('single')->error($exception->getMessage());
        }


    }

    private function sendTelegramMessage($message)
    {
        $response = $this->telegramApi->sendMessage([
            'chat_id' => config('telegramLogger.chat_id'),
            'parse_mode' => 'html',
            'text' => $message
        ]);

    }


    private function messageFormatter($record): string
    {
        $name = '<b>App Name</b>: ' . $this->appName;
        $level_name = '<b>Type</b>: (' . $record['level_name'] . ')';
        $url = '<b>URL</b>: ' . $this->appUrl;
        $errorLog = "<b>Error Log</b>: \n" . $record['formatted'];
        $date = "<b>Time</b>: \n" . Carbon::parse($record['datetime'])->format('Y-m-d H:i:s');


        return sprintf("%s\n%s\n%s\n\n%s\n\n%s", $level_name, $name, $url,$errorLog,$date);
    }
}