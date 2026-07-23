<?php

namespace Tests\Unit\Livewire\Settings;

use App\Livewire\Settings\AiProviderAccessPage;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit-level tests for AiProviderAccessPage Livewire component.
 *
 * Covers mount(), save(), and connect() for all three providers
 * (openai, deepseek, gigachat). Secret fields are never pre-filled
 * and blank values do not overwrite existing secrets.
 */
class AiProviderAccessPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    /**
     * Build a mock SettingsService that returns empty strings for all non-secret
     * ai.* keys and null for integer keys (no stored value).
     *
     * @return \Mockery\MockInterface&SettingsService
     */
    private function makeEmptySettingsMock(): mixed
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);

        return $mock;
    }

    // ── mount ────────────────────────────────────────────────────────────────────

    public function test_mount_populates_openai_non_secret_fields(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('ai.openai_base_url')->andReturn('https://api.openai.com/v1');
        $mock->shouldReceive('get')->with('ai.openai_model')->andReturn('gpt-4o');
        $mock->shouldReceive('get')->with('ai.openai_max_tokens')->andReturn(500);
        $mock->shouldReceive('get')->with('ai.openai_temperature')->andReturn('0.5');
        // DeepSeek fields
        $mock->shouldReceive('get')->with('ai.deepseek_client_id')->andReturn('');
        $mock->shouldReceive('get')->with('ai.deepseek_base_url')->andReturn('');
        $mock->shouldReceive('get')->with('ai.deepseek_model')->andReturn('');
        $mock->shouldReceive('get')->with('ai.deepseek_max_tokens')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.deepseek_temperature')->andReturn('');
        // GigaChat fields
        $mock->shouldReceive('get')->with('ai.gigachat_client_id')->andReturn('');
        $mock->shouldReceive('get')->with('ai.gigachat_base_url')->andReturn('');
        $mock->shouldReceive('get')->with('ai.gigachat_model')->andReturn('');
        $mock->shouldReceive('get')->with('ai.gigachat_max_tokens')->andReturn(null);
        $mock->shouldReceive('get')->with('ai.gigachat_temperature')->andReturn('');
        $mock->shouldReceive('get')->with('ai.gigachat_path_cert')->andReturn('');
        $mock->shouldReceive('get')->with('ai.gigachat_scope')->andReturn('');

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);

        $this->assertSame('https://api.openai.com/v1', $component->openai_base_url);
        $this->assertSame('gpt-4o', $component->openai_model);
        $this->assertSame(500, $component->openai_max_tokens);
        $this->assertSame('0.5', $component->openai_temperature);
        // Secret field must be null
        $this->assertNull($component->openai_api_key);
    }

    public function test_mount_sets_provider_from_argument(): void
    {
        $component = new AiProviderAccessPage();
        $component->mount('deepseek', $this->makeEmptySettingsMock());

        $this->assertSame('deepseek', $component->provider);
    }

    public function test_mount_secret_fields_are_always_null(): void
    {
        $component = new AiProviderAccessPage();
        $component->mount('gigachat', $this->makeEmptySettingsMock());

        $this->assertNull($component->gigachat_client_secret);
        $this->assertNull($component->openai_api_key);
        $this->assertNull($component->deepseek_client_secret);
    }

    // ── save — OpenAI ─────────────────────────────────────────────────────────────

    public function test_save_openai_persists_non_secret_fields(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.openai_base_url', 'https://api.openai.com/v1')->once();
        $mock->shouldReceive('set')->with('ai.openai_model', 'gpt-4o')->once();
        $mock->shouldReceive('set')->with('ai.openai_temperature', '0.7')->once();

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);
        $component->openai_base_url = 'https://api.openai.com/v1';
        $component->openai_model = 'gpt-4o';
        $component->openai_temperature = '0.7';
        $component->openai_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_openai_persists_api_key_when_non_empty(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.openai_api_key', 'sk-test123')->once();
        $mock->shouldReceive('set')->with(Mockery::not('ai.openai_api_key'), Mockery::any());

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);
        $component->openai_api_key = 'sk-test123';
        $component->openai_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_openai_does_not_overwrite_api_key_when_empty(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set')->with('ai.openai_api_key', Mockery::any());
        $mock->shouldReceive('set')->with(Mockery::not('ai.openai_api_key'), Mockery::any());

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);
        $component->openai_api_key = '';
        $component->openai_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_openai_rejects_zero_max_tokens(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set');

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);
        $component->openai_max_tokens = 0;
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('openai_max_tokens', $component->formErrors);
    }

    // ── save — DeepSeek ───────────────────────────────────────────────────────────

    public function test_save_deepseek_persists_non_secret_fields(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.deepseek_client_id', 'my-client')->once();
        $mock->shouldReceive('set')->with('ai.deepseek_base_url', 'https://api.deepseek.com')->once();
        $mock->shouldReceive('set')->with('ai.deepseek_model', 'deepseek-chat')->once();
        $mock->shouldReceive('set')->with('ai.deepseek_temperature', '0.8')->once();

        $component = new AiProviderAccessPage();
        $component->mount('deepseek', $mock);
        $component->deepseek_client_id = 'my-client';
        $component->deepseek_base_url = 'https://api.deepseek.com';
        $component->deepseek_model = 'deepseek-chat';
        $component->deepseek_temperature = '0.8';
        $component->deepseek_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_deepseek_does_not_overwrite_client_secret_when_empty(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set')->with('ai.deepseek_client_secret', Mockery::any());
        $mock->shouldReceive('set')->with(Mockery::not('ai.deepseek_client_secret'), Mockery::any());

        $component = new AiProviderAccessPage();
        $component->mount('deepseek', $mock);
        $component->deepseek_client_secret = '';
        $component->deepseek_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_deepseek_persists_client_secret_when_non_empty(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.deepseek_client_secret', 'secret-value')->once();
        $mock->shouldReceive('set')->with(Mockery::not('ai.deepseek_client_secret'), Mockery::any());

        $component = new AiProviderAccessPage();
        $component->mount('deepseek', $mock);
        $component->deepseek_client_secret = 'secret-value';
        $component->deepseek_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_deepseek_rejects_zero_max_tokens(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set');

        $component = new AiProviderAccessPage();
        $component->mount('deepseek', $mock);
        $component->deepseek_max_tokens = 0;
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('deepseek_max_tokens', $component->formErrors);
    }

    // ── save — GigaChat ───────────────────────────────────────────────────────────

    public function test_save_gigachat_persists_all_non_secret_fields(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.gigachat_client_id', 'gc-id')->once();
        $mock->shouldReceive('set')->with('ai.gigachat_base_url', 'https://gigachat.sber.ru')->once();
        $mock->shouldReceive('set')->with('ai.gigachat_model', 'GigaChat-Pro')->once();
        $mock->shouldReceive('set')->with('ai.gigachat_temperature', '0.6')->once();
        $mock->shouldReceive('set')->with('ai.gigachat_scope', 'GIGACHAT_API_PERS')->once();
        // path_cert is NOT persisted from a text field — it is set only when a
        // certificate file is uploaded (no upload here, so no set call expected).
        $mock->shouldNotReceive('set')->with('ai.gigachat_path_cert', Mockery::any());

        $component = new AiProviderAccessPage();
        $component->mount('gigachat', $mock);
        $component->gigachat_client_id = 'gc-id';
        $component->gigachat_base_url = 'https://gigachat.sber.ru';
        $component->gigachat_model = 'GigaChat-Pro';
        $component->gigachat_temperature = '0.6';
        $component->gigachat_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_gigachat_persists_selected_scope(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.gigachat_scope', 'GIGACHAT_API_B2B')->once();
        $mock->shouldReceive('set')->with(Mockery::not('ai.gigachat_scope'), Mockery::any());

        $component = new AiProviderAccessPage();
        $component->mount('gigachat', $mock);
        $component->gigachat_scope = 'GIGACHAT_API_B2B';
        $component->gigachat_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_gigachat_rejects_unknown_scope(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set');

        $component = new AiProviderAccessPage();
        $component->mount('gigachat', $mock);
        $component->gigachat_scope = 'GIGACHAT_API_BOGUS';
        $component->gigachat_max_tokens = null;
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('gigachat_scope', $component->formErrors);
    }

    public function test_save_gigachat_does_not_overwrite_client_secret_when_empty(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set')->with('ai.gigachat_client_secret', Mockery::any());
        $mock->shouldReceive('set')->with(Mockery::not('ai.gigachat_client_secret'), Mockery::any());

        $component = new AiProviderAccessPage();
        $component->mount('gigachat', $mock);
        $component->gigachat_client_secret = '';
        $component->gigachat_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_gigachat_persists_client_secret_when_non_empty(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.gigachat_client_secret', 'gc-secret')->once();
        $mock->shouldReceive('set')->with(Mockery::not('ai.gigachat_client_secret'), Mockery::any());

        $component = new AiProviderAccessPage();
        $component->mount('gigachat', $mock);
        $component->gigachat_client_secret = 'gc-secret';
        $component->gigachat_max_tokens = null;
        $component->save($mock);

        $this->assertTrue($component->saved);
    }

    public function test_save_gigachat_rejects_zero_max_tokens(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set');

        $component = new AiProviderAccessPage();
        $component->mount('gigachat', $mock);
        $component->gigachat_max_tokens = 0;
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('gigachat_max_tokens', $component->formErrors);
    }

    // ── save — unknown provider ───────────────────────────────────────────────────

    public function test_save_sets_error_for_unknown_provider(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set');

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);
        // Forcibly override provider after mount
        $component->provider = 'unknown';
        $component->save($mock);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('provider', $component->formErrors);
    }

    // ── connect() — verify-before-save ─────────────────────────────────────────────

    public function test_connect_openai_persists_when_verification_succeeds(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('set')->with('ai.openai_api_key', 'sk-test')->once();
        $mock->shouldReceive('set'); // other non-secret keys

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);
        $component->openai_api_key = 'sk-test';
        $component->openai_base_url = 'https://api.openai.com/v1';
        $component->openai_model = 'gpt-4o-mini';

        $component->connect($mock, new \App\Modules\Ai\Services\AiProviderVerificationService());

        $this->assertTrue($component->saved);
        $this->assertNull($component->verifyError);
    }

    public function test_connect_openai_blocks_save_when_verification_fails(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([], 401)]);

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        // Nothing must be persisted when verification fails.
        $mock->shouldNotReceive('set');

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);
        $component->openai_api_key = 'bad-key';
        $component->openai_base_url = 'https://api.openai.com/v1';
        $component->openai_model = 'gpt-4o-mini';

        $component->connect($mock, new \App\Modules\Ai\Services\AiProviderVerificationService());

        $this->assertFalse($component->saved);
        $this->assertNotNull($component->verifyError);
        $this->assertStringContainsString('401', (string) $component->verifyError);
    }

    public function test_connect_openai_uses_stored_secret_when_field_blank(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['choices' => []], 200)]);

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        // Stored API key is used because the field is left blank.
        $mock->shouldReceive('get')->with('ai.openai_api_key')->andReturn('stored-key');
        $mock->shouldReceive('get')->andReturn(null);
        // Non-secret keys are persisted; the blank api_key field is NOT (blank-secret guard).
        $mock->shouldReceive('set');

        $component = new AiProviderAccessPage();
        $component->mount('openai', $mock);
        $component->openai_api_key = null; // blank → fall back to stored
        $component->openai_base_url = 'https://api.openai.com/v1';
        $component->openai_model = 'gpt-4o-mini';

        $component->connect($mock, new \App\Modules\Ai\Services\AiProviderVerificationService());

        $this->assertTrue($component->saved);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer stored-key'));
    }

    public function test_connect_gigachat_blocks_when_certificate_missing(): void
    {
        \Illuminate\Support\Facades\Http::fake();

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldNotReceive('set');

        $component = new AiProviderAccessPage();
        $component->mount('gigachat', $mock);
        $component->gigachat_client_secret = 'base64secret';
        // No cert uploaded and none stored → verification must fail before any HTTP call.

        $component->connect($mock, new \App\Modules\Ai\Services\AiProviderVerificationService());

        $this->assertFalse($component->saved);
        $this->assertNotNull($component->verifyError);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
