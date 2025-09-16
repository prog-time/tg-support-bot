<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramSetWebhookTest extends TestCase
{
    public function test_successful_webhook_set(): void
    {
        $tgToken = config('traffic_source.settings.telegram.token');
        $appUrl = config('app.url');
        $urlWebhook = $appUrl . '/api/telegram/bot';

        // Запуск команды
        $exitCode = Artisan::call('telegram:set-webhook');

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Webhook установлен:', Artisan::output());

        $urlQuery = "https://api.telegram.org/bot{$tgToken}/getWebhookInfo";
        $response = Http::post($urlQuery);
        $resultQuery = $response->json();

        $this->assertTrue($resultQuery['ok']);
        $this->assertEquals($resultQuery['result']['url'], $urlWebhook);
    }
}
