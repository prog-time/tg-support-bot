<?php

namespace Tests\Unit\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\OkIntegrationPage;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OkIntegrationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_valid_token_is_saved(): void
    {
        Http::fake(['*' => Http::response(['subscriptions' => []])]);

        Livewire::test(OkIntegrationPage::class)
            ->set('token', 'new-token')
            ->set('webhook_secret', 'new-secret')
            ->call('connect')
            ->assertSet('verifySuccess', true)
            ->assertSet('token', '')
            ->assertSet('hasToken', true);

        $settings = app(SettingsService::class);
        $this->assertSame('new-token', $settings->get('ok.token'));
        $this->assertSame('new-secret', $settings->get('ok.webhook_secret'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/graph/me/subscribe?access_token=new-token')
            && $request['url'] === rtrim((string) config('app.url'), '/') . '/api/ok/bot/new-secret');
    }

    public function test_rejected_token_saves_nothing_and_shows_the_error(): void
    {
        Http::fake(['*' => Http::response(['error_code' => 102, 'error_msg' => 'invalid'])]);

        Livewire::test(OkIntegrationPage::class)
            ->set('token', 'bad')
            ->set('webhook_secret', 'secret')
            ->call('connect')
            ->assertSet('verifySuccess', false)
            ->assertSee('Одноклассники не приняли токен');

        $this->assertDatabaseMissing('settings', ['key' => 'ok.token']);
        $this->assertDatabaseMissing('settings', ['key' => 'ok.webhook_secret']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/subscribe?'));
    }

    public function test_token_and_secret_are_required_on_first_save(): void
    {
        Http::fake();

        Livewire::test(OkIntegrationPage::class)
            ->call('connect')
            ->assertSet('formErrors.token', 'Укажите токен группы.')
            ->assertSet('formErrors.webhook_secret', 'Укажите секретный ключ вебхука.');

        Http::assertNothingSent();
    }

    public function test_blank_fields_keep_stored_secrets_and_verify_the_stored_token(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('ok.token', 'stored-token');
        $settings->set('ok.webhook_secret', 'stored-secret');
        Http::fake(['*' => Http::response(['subscriptions' => []])]);

        Livewire::test(OkIntegrationPage::class)
            ->call('connect')
            ->assertSet('verifySuccess', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'access_token=stored-token'));
        $this->assertSame('stored-token', $settings->get('ok.token'));
    }

    public function test_stored_secrets_are_not_sent_to_the_browser(): void
    {
        app(SettingsService::class)->set('ok.token', 'stored-token');

        Livewire::test(OkIntegrationPage::class)
            ->assertSet('token', '')
            ->assertDontSee('stored-token');
    }

    public function test_non_admin_is_redirected(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        Livewire::test(OkIntegrationPage::class)->assertRedirect(route('admin.settings.general'));
    }

    public function test_changed_token_resubscribes_without_another_step(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('ok.token', 'old-token');
        $settings->set('ok.webhook_secret', 'stored-secret');
        Http::fake(['*' => Http::response(['subscriptions' => [], 'success' => true])]);

        Livewire::test(OkIntegrationPage::class)
            ->set('token', 'new-token')
            ->call('connect')
            ->assertSet('verifySuccess', true)
            ->assertSee('вебхук подписан');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/graph/me/subscribe?access_token=new-token'));
    }

    public function test_failed_subscription_keeps_settings_and_shows_the_error(): void
    {
        Http::fake([
            '*/me/subscriptions*' => Http::response(['subscriptions' => []]),
            '*/me/subscribe*' => Http::response(['error_code' => 5, 'error_msg' => 'bad url']),
        ]);

        Livewire::test(OkIntegrationPage::class)
            ->set('token', 'new-token')
            ->set('webhook_secret', 'new-secret')
            ->call('connect')
            ->assertSet('verifySuccess', false)
            ->assertSee('вебхук не подписан');

        $this->assertSame('new-token', app(SettingsService::class)->get('ok.token'));
    }
}
