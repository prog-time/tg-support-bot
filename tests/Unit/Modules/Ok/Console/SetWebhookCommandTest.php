<?php

namespace Tests\Unit\Modules\Ok\Console;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetWebhookCommandTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config(['app.url' => 'https://app.test']);
        app(SettingsService::class)->set('ok.token', 'ok-token-value');
        app(SettingsService::class)->set('ok.webhook_secret', 'ok-secret-value');
    }

    public function test_subscribes_the_url_and_hides_credentials(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->assertSame(0, Artisan::call('ok:set-webhook'));

        Http::assertSent(fn ($request) => ($request->data()['url'] ?? null) === 'https://app.test/api/ok/bot/ok-secret-value');
        $output = Artisan::output();
        $this->assertStringNotContainsString('ok-secret-value', $output);
        $this->assertStringNotContainsString('ok-token-value', $output);
    }

    public function test_api_error_fails_without_exposing_credentials(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['error_code' => 102, 'error_msg' => 'bad token'])]);

        $this->assertSame(1, Artisan::call('ok:set-webhook'));

        $this->assertStringNotContainsString('ok-token-value', Artisan::output());
    }

    public function test_missing_settings_fail_without_a_request(): void
    {
        Http::fake();

        $this->assertSame(1, Artisan::call('ok:set-webhook'));

        Http::assertNothingSent();
    }
}
