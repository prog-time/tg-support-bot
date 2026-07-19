<?php

namespace App\Modules\Avito\Console;

use App\Modules\Avito\Api\AvitoMethods;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

/**
 * Subscribe the Avito Messenger webhook to this app's endpoint and print the
 * resolved Avito account. Mirrors the host's max-bot:set-webhook command.
 */
class SetWebhookCommand extends Command
{
    /** @var string */
    protected $signature = 'avito:set-webhook {url? : Webhook URL (defaults to <app_url>/api/avito/bot)}';

    /** @var string */
    protected $description = 'Subscribe the Avito Messenger webhook and show the resolved account id';

    /**
     * @param AvitoMethods $avito
     *
     * @return int
     */
    public function handle(AvitoMethods $avito): int
    {
        // Embed the webhook secret in the path so AvitoQuery can authenticate
        // the callback (Avito calls back the exact URL we register here).
        $secret = (string) app(SettingsService::class)->get('avito.webhook_secret');
        $base = rtrim(url('/api/avito/bot'), '/');
        $default = $secret !== '' ? $base . '/' . rawurlencode($secret) : $base;

        $url = (string) ($this->argument('url') ?: $default);

        try {
            $self = $avito->getSelf();
            $this->info('Avito account: ' . json_encode($self, JSON_UNESCAPED_UNICODE));

            $result = $avito->subscribeWebhook($url);
            $this->info('Webhook subscribed: ' . $url);
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
