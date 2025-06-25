<?php

namespace App\Console\Commands;

use App\TelegramBot\TelegramMethods;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';
    protected $description = 'Устанавливает Telegram Webhook для бота';

    public function handle()
    {
        $url = env('APP_URL') . '/api/telegram/bot';
        $secret = env('TELEGRAM_SECRET_KEY');

        $queryParams = [
            'url' => $url,
            'max_connections' => 40,
            'drop_pending_updates' => true,
            'secret_token' => $secret,
        ];

        $result = TelegramMethods::sendQueryTelegram('setWebhook', $queryParams);

        if (isset($result->rawData)) {
            $this->info('Webhook установлен:');
            $this->line(json_encode($result->rawData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error('Ошибка при установке webhook');
        }

        return Command::SUCCESS;
    }
}
