<?php

namespace Tests\Unit\Livewire\Settings;

use App\Livewire\Settings\IntegrationChannelPage;
use App\Modules\Admin\Services\ChannelStatusService;
use App\Modules\Admin\Services\WebhookRegistrationService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit-level tests for the IntegrationChannelPage Livewire component.
 *
 * Exercises mount(), save(), and connect() in isolation using a
 * mocked SettingsService / ChannelStatusService / WebhookRegistrationService
 * — no DB or Livewire rendering required for the core logic assertions.
 */
class IntegrationChannelPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * SettingsService mock whose get() returns '' for any key by default.
     *
     * @return \Mockery\MockInterface&SettingsService
     */
    private function settingsMock(): SettingsService
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->andReturn('');

        return $mock;
    }

    /**
     * @return \Mockery\MockInterface&ChannelStatusService
     */
    private function statusMock(bool $telegramConnected = false, bool $telegramAiConnected = false): ChannelStatusService
    {
        /** @var \Mockery\MockInterface&ChannelStatusService $mock */
        $mock = Mockery::mock(ChannelStatusService::class);
        $mock->shouldReceive('all')->andReturn([
            'telegram' => ['connected' => $telegramConnected, 'label' => 'x'],
            'telegram_ai' => ['connected' => $telegramAiConnected, 'label' => 'x'],
            'vk' => ['connected' => false, 'label' => 'x'],
            'max' => ['connected' => false, 'label' => 'x'],
        ]);

        return $mock;
    }

    // ── Telegram (main bot) tests ─────────────────────────────────────────────

    public function test_mount_loads_fields_and_connection_status(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $settings */
        $settings = Mockery::mock(SettingsService::class);
        // telegram.group_id is no longer loaded on this page — it moved to GeneralSettingsPage.
        $settings->shouldReceive('get')->with('telegram.token')->andReturn('tok');
        $settings->shouldReceive('get')->with('telegram.secret_key')->andReturn('sec');
        $settings->shouldReceive('get')->with('telegram.webhook_ip_address')->andReturn('');
        $settings->shouldReceive('get')->with('telegram_ai.token')->andReturn('');
        $settings->shouldReceive('get')->with('telegram_ai.secret')->andReturn('');
        $settings->shouldReceive('get')->with('vk.token')->andReturn('');
        $settings->shouldReceive('get')->with('vk.secret_key')->andReturn('');
        $settings->shouldReceive('get')->with('vk.confirm_code')->andReturn('');
        $settings->shouldReceive('get')->with('max.token')->andReturn('');
        $settings->shouldReceive('get')->with('max.secret_key')->andReturn('');
        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock(true));

        $this->assertSame('telegram', $component->channel);
        $this->assertSame('tok', $component->telegram_token);
        $this->assertTrue($component->channelConnected);
    }

    public function test_save_telegram_persists_nonempty_secrets(): void
    {
        // telegram.group_id is no longer persisted on this page — it moved to GeneralSettingsPage.
        $settings = $this->settingsMock();
        $settings->shouldNotReceive('set')->with('telegram.group_id', Mockery::any());
        $settings->shouldReceive('set')->with('telegram.token', 'newtok')->once();
        $settings->shouldReceive('set')->with('telegram.secret_key', 'newsec')->once();
        $settings->shouldReceive('set')->with('telegram.webhook_ip_address', '')->once();

        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock());
        $component->telegram_token = 'newtok';
        $component->telegram_secret_key = 'newsec';
        $component->save($settings);

        $this->assertTrue($component->saved);
        $this->assertEmpty($component->formErrors);
    }

    public function test_save_telegram_persists_webhook_ip_address(): void
    {
        $settings = $this->settingsMock();
        $settings->shouldReceive('set')->with('telegram.token', 'newtok')->once();
        $settings->shouldReceive('set')->with('telegram.secret_key', 'newsec')->once();
        $settings->shouldReceive('set')->with('telegram.webhook_ip_address', '149.154.166.110')->once();

        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock());
        $component->telegram_token = 'newtok';
        $component->telegram_secret_key = 'newsec';
        $component->telegram_webhook_ip_address = '149.154.166.110';
        $component->save($settings);

        $this->assertTrue($component->saved);
        $this->assertEmpty($component->formErrors);
    }

    public function test_connect_telegram_rejects_invalid_webhook_ip_address(): void
    {
        $settings = $this->settingsMock();
        $settings->shouldNotReceive('set')->with('telegram.token', Mockery::any());

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        $webhook->shouldNotReceive('verifyTelegram');

        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock());
        $component->telegram_token = 'tok';
        $component->telegram_secret_key = 'sec';
        $component->telegram_webhook_ip_address = 'not-an-ip';
        $component->connect($settings, $webhook);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('telegram_webhook_ip_address', $component->formErrors);
    }

    public function test_save_telegram_skips_blank_secrets(): void
    {
        $settings = $this->settingsMock();
        $settings->shouldReceive('set')->with('telegram.token', Mockery::any())->never();
        $settings->shouldReceive('set')->with('telegram.secret_key', Mockery::any())->never();
        $settings->shouldReceive('set')->with('telegram.webhook_ip_address', '')->once();

        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock());
        $component->telegram_token = '';
        $component->telegram_secret_key = '';
        $component->save($settings);

        $this->assertTrue($component->saved);
    }

    // ── Telegram AI bot tests ─────────────────────────────────────────────────

    public function test_mount_telegram_ai_channel_sets_channel_slug(): void
    {
        $settings = $this->settingsMock();

        $component = new IntegrationChannelPage();
        $component->mount('telegram_ai', $settings, $this->statusMock(false, true));

        $this->assertSame('telegram_ai', $component->channel);
        $this->assertTrue($component->channelConnected);
    }

    public function test_save_telegram_ai_persists_nonempty_secrets(): void
    {
        $settings = $this->settingsMock();
        $settings->shouldReceive('set')->with('telegram_ai.token', 'ai-tok')->once();
        $settings->shouldReceive('set')->with('telegram_ai.secret', 'ai-sec')->once();
        // username/id are NOT set via save() — they come from getMe in connect().
        $settings->shouldNotReceive('set')->with('telegram_ai.username', Mockery::any());
        $settings->shouldNotReceive('set')->with('telegram_ai.id', Mockery::any());

        $component = new IntegrationChannelPage();
        $component->mount('telegram_ai', $settings, $this->statusMock());
        $component->telegram_ai_token = 'ai-tok';
        $component->telegram_ai_secret = 'ai-sec';
        $component->save($settings);

        $this->assertTrue($component->saved);
        $this->assertEmpty($component->formErrors);
    }

    public function test_save_telegram_ai_skips_blank_secrets(): void
    {
        $settings = $this->settingsMock();
        $settings->shouldReceive('set')->with('telegram_ai.token', Mockery::any())->never();
        $settings->shouldReceive('set')->with('telegram_ai.secret', Mockery::any())->never();

        $component = new IntegrationChannelPage();
        $component->mount('telegram_ai', $settings, $this->statusMock());
        $component->telegram_ai_token = '';
        $component->telegram_ai_secret = '';
        $component->save($settings);

        $this->assertTrue($component->saved);
    }

    public function test_connect_telegram_ai_saves_but_does_not_call_webhook_service(): void
    {
        $settings = $this->settingsMock();
        $settings->shouldReceive('get')->with('telegram_ai.token')->andReturn('');
        $settings->shouldReceive('set')->with('telegram_ai.token', 'ai-tok')->once();
        $settings->shouldReceive('set')->with('telegram_ai.secret', 'ai-sec')->once();
        // Bot id/@username are captured automatically from getMe.
        $settings->shouldReceive('set')->with('telegram_ai.id', 4242)->once();
        $settings->shouldReceive('set')->with('telegram_ai.username', '@ai_bot')->once();

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        $webhook->shouldReceive('verifyTelegram')->with('ai-tok', \Mockery::any())->once()
            ->andReturn(['success' => true, 'message' => 'OK', 'botId' => 4242, 'botUsername' => 'ai_bot']);
        $webhook->shouldNotReceive('registerTelegram');
        $webhook->shouldNotReceive('registerVk');
        $webhook->shouldNotReceive('registerMax');

        $component = new IntegrationChannelPage();
        $component->mount('telegram_ai', $settings, $this->statusMock());
        // Token + secret are required; username/id are derived from getMe.
        $component->telegram_ai_token = 'ai-tok';
        $component->telegram_ai_secret = 'ai-sec';
        $component->connect($settings, $webhook);

        $this->assertTrue($component->saved);
        $this->assertTrue($component->webhookSuccess);
        $this->assertNotNull($component->webhookMessage);
    }

    public function test_connect_telegram_ai_requires_token_and_secret(): void
    {
        $settings = $this->settingsMock();

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        // Validation fails before any verification / persistence.
        $webhook->shouldNotReceive('verifyTelegram');
        $settings->shouldNotReceive('set');

        $component = new IntegrationChannelPage();
        $component->mount('telegram_ai', $settings, $this->statusMock());
        $component->telegram_ai_token = 'ai-tok';
        $component->telegram_ai_secret = ''; // missing
        $component->connect($settings, $webhook);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('telegram_ai_secret', $component->formErrors);
        $this->assertArrayNotHasKey('telegram_ai_token', $component->formErrors);
    }

    // ── VK tests ──────────────────────────────────────────────────────────────

    public function test_save_vk_persists_only_nonempty_fields(): void
    {
        $settings = $this->settingsMock();
        $settings->shouldReceive('set')->with('vk.token', 'vktok')->once();
        $settings->shouldReceive('set')->with('vk.secret_key', Mockery::any())->never();
        $settings->shouldReceive('set')->with('vk.confirm_code', 'cc')->once();

        $component = new IntegrationChannelPage();
        $component->mount('vk', $settings, $this->statusMock());
        $component->vk_token = 'vktok';
        $component->vk_secret_key = '';
        $component->vk_confirm_code = 'cc';
        $component->save($settings);

        $this->assertTrue($component->saved);
    }

    public function test_connect_vk_requires_all_fields(): void
    {
        $settings = $this->settingsMock();
        // Validation fails before any verification / persistence.
        $settings->shouldNotReceive('set');

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        $webhook->shouldNotReceive('verifyVk');

        $component = new IntegrationChannelPage();
        $component->mount('vk', $settings, $this->statusMock());
        $component->vk_token = 'vktok';
        $component->vk_secret_key = '';   // missing
        $component->vk_confirm_code = ''; // missing
        $component->connect($settings, $webhook);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('vk_secret_key', $component->formErrors);
        $this->assertArrayHasKey('vk_confirm_code', $component->formErrors);
        $this->assertArrayNotHasKey('vk_token', $component->formErrors);
    }

    public function test_connect_max_requires_all_fields(): void
    {
        $settings = $this->settingsMock();
        // Validation fails before any verification / persistence.
        $settings->shouldNotReceive('set');

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        $webhook->shouldNotReceive('verifyMax');

        $component = new IntegrationChannelPage();
        $component->mount('max', $settings, $this->statusMock());
        $component->max_token = 'maxtok';
        $component->max_secret_key = ''; // missing
        $component->connect($settings, $webhook);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('max_secret_key', $component->formErrors);
        $this->assertArrayNotHasKey('max_token', $component->formErrors);
    }

    // ── connect() tests ───────────────────────────────────────────────────────

    public function test_connect_saves_then_registers_webhook_for_telegram(): void
    {
        // telegram.group_id is no longer verified or persisted here — it moved to GeneralSettingsPage.
        $settings = $this->settingsMock();
        $settings->shouldReceive('get')->with('telegram.token')->andReturn('');
        $settings->shouldNotReceive('set')->with('telegram.group_id', Mockery::any());
        $settings->shouldReceive('set')->with('telegram.token', 'tok123')->once();
        $settings->shouldReceive('set')->with('telegram.secret_key', 'sec123')->once();
        $settings->shouldReceive('set')->with('telegram.webhook_ip_address', '')->once();

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        $webhook->shouldReceive('verifyTelegram')->with('tok123', null)->once()->andReturn(['success' => true, 'message' => 'Verified']);
        $webhook->shouldReceive('registerTelegram')->with()->once()->andReturn(['success' => true, 'message' => 'OK']);

        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock());
        $component->telegram_token = 'tok123';
        $component->telegram_secret_key = 'sec123';
        $component->connect($settings, $webhook);

        $this->assertTrue($component->saved);
        $this->assertTrue($component->webhookSuccess);
        $this->assertSame('OK', $component->webhookMessage);
    }

    public function test_connect_does_not_verify_or_register_webhook_when_validation_fails(): void
    {
        // With token blank (and no stored fallback) the token error fires before verify.
        $settings = $this->settingsMock();
        $settings->shouldNotReceive('set');

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        $webhook->shouldNotReceive('verifyTelegram');
        $webhook->shouldNotReceive('registerTelegram');

        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock());
        $component->telegram_token = '';
        $component->telegram_secret_key = '';
        $component->connect($settings, $webhook);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('telegram_token', $component->formErrors);
    }

    public function test_connect_does_not_save_when_verification_fails(): void
    {
        $settings = $this->settingsMock();
        $settings->shouldNotReceive('set');

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        $webhook->shouldReceive('verifyTelegram')->with('bad_tok', null)->once()->andReturn(['success' => false, 'message' => 'Неверный токен Telegram.']);
        $webhook->shouldNotReceive('registerTelegram');

        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock());
        $component->telegram_token = 'bad_tok';
        $component->telegram_secret_key = 'sec';
        $component->connect($settings, $webhook);

        $this->assertFalse($component->saved);
        $this->assertFalse($component->webhookSuccess);
        $this->assertSame('Неверный токен Telegram.', $component->webhookMessage);
    }

    public function test_connect_telegram_requires_token_and_secret_key(): void
    {
        // token and secret_key are the only required fields on this page now.
        $settings = $this->settingsMock();
        $settings->shouldNotReceive('set');

        /** @var \Mockery\MockInterface&WebhookRegistrationService $webhook */
        $webhook = Mockery::mock(WebhookRegistrationService::class);
        $webhook->shouldNotReceive('verifyTelegram');
        $webhook->shouldNotReceive('registerTelegram');

        $component = new IntegrationChannelPage();
        $component->mount('telegram', $settings, $this->statusMock());
        $component->telegram_token = '';        // missing
        $component->telegram_secret_key = '';   // missing
        $component->connect($settings, $webhook);

        $this->assertFalse($component->saved);
        $this->assertArrayHasKey('telegram_token', $component->formErrors);
        $this->assertArrayHasKey('telegram_secret_key', $component->formErrors);
        // group_id is no longer validated on this page.
        $this->assertArrayNotHasKey('telegram_group_id', $component->formErrors);
    }
}
