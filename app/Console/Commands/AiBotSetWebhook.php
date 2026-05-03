<?php

namespace App\Console\Commands;

use App\Modules\Telegram\Api\TelegramMethods;
use Illuminate\Console\Command;

class AiBotSetWebhook extends Command
{
    protected $signature = 'ai-bot:set-webhook';

    protected $description = 'Register the AI bot webhook with Telegram (TELEGRAM_AI_BOT_TOKEN)';

    /**
     * Register the AI bot webhook via the Telegram setWebhook API.
     *
     * @return int
     */
    public function handle(): int
    {
        $appUrl = config('app.url');
        $url = $appUrl . '/api/ai-bot/webhook';
        $token = config('traffic_source.settings.telegram_ai.token');
        $secret = config('traffic_source.settings.telegram_ai.secret');

        if (empty($token)) {
            $this->error('TELEGRAM_AI_BOT_TOKEN is not set.');

            return Command::FAILURE;
        }

        if (empty($secret)) {
            $this->error('TELEGRAM_AI_BOT_SECRET is not set.');

            return Command::FAILURE;
        }

        $queryParams = [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ];

        $result = TelegramMethods::sendQueryTelegram('setWebhook', $queryParams, $token);

        if ($result->ok === true) {
            $this->info('AI bot webhook registered successfully:');
            $this->line(json_encode($result->rawData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error('Failed to register AI bot webhook:');
            $this->line(json_encode($result->rawData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return Command::SUCCESS;
    }
}
