<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Admin\Services;

use App\Modules\Admin\Services\ChannelStatusService;
use App\Services\Settings\SettingsService;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for ChannelStatusService.
 *
 * Uses a mock SettingsService so no DB or cache is involved.
 */
class ChannelStatusServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    // ── all() ────────────────────────────────────────────────────────────────

    public function test_all_returns_all_five_channels(): void
    {
        $settings = $this->makeSettings([]);
        $service = new ChannelStatusService($settings);

        $result = $service->all();

        $this->assertArrayHasKey('telegram', $result);
        $this->assertArrayHasKey('telegram_ai', $result);
        $this->assertArrayHasKey('vk', $result);
        $this->assertArrayHasKey('max', $result);
        $this->assertArrayHasKey('email', $result);
    }

    // ── telegram() ───────────────────────────────────────────────────────────

    public function test_telegram_connected_when_token_and_secret_key_set(): void
    {
        // telegram.group_id is no longer part of the connection check —
        // it was moved to the «Основные» general settings screen.
        $settings = $this->makeSettings([
            'telegram.token' => 'tok',
            'telegram.secret_key' => 'sec',
        ]);
        $service = new ChannelStatusService($settings);

        $status = $service->telegram();

        $this->assertTrue($status['connected']);
        $this->assertSame('Подключён', $status['label']);
    }

    public function test_telegram_connected_regardless_of_group_id(): void
    {
        // group_id is not part of the Telegram integration check — confirmed by service.
        $settings = $this->makeSettings([
            'telegram.token' => 'tok',
            'telegram.secret_key' => 'sec',
            'telegram.group_id' => '',
        ]);
        $service = new ChannelStatusService($settings);

        $this->assertTrue($service->telegram()['connected']);
    }

    public function test_telegram_not_connected_when_token_missing(): void
    {
        $settings = $this->makeSettings([
            'telegram.token' => '',
            'telegram.secret_key' => 'sec',
        ]);
        $service = new ChannelStatusService($settings);

        $status = $service->telegram();

        $this->assertFalse($status['connected']);
        $this->assertSame('Не настроен', $status['label']);
    }

    public function test_telegram_not_connected_when_secret_key_missing(): void
    {
        $settings = $this->makeSettings([
            'telegram.token' => 'tok',
            'telegram.secret_key' => '',
        ]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->telegram()['connected']);
    }

    public function test_telegram_not_connected_when_all_keys_missing(): void
    {
        $settings = $this->makeSettings([]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->telegram()['connected']);
    }

    // ── telegramAi() ─────────────────────────────────────────────────────────

    public function test_telegram_ai_connected_when_token_set(): void
    {
        $settings = $this->makeSettings([
            'telegram_ai.token' => 'ai-tok',
        ]);
        $service = new ChannelStatusService($settings);

        $status = $service->telegramAi();

        $this->assertTrue($status['connected']);
        $this->assertSame('Подключён', $status['label']);
    }

    public function test_telegram_ai_not_connected_when_token_empty(): void
    {
        $settings = $this->makeSettings([
            'telegram_ai.token' => '',
        ]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->telegramAi()['connected']);
        $this->assertSame('Не настроен', $service->telegramAi()['label']);
    }

    public function test_telegram_ai_not_connected_when_token_missing(): void
    {
        $settings = $this->makeSettings([]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->telegramAi()['connected']);
    }

    // ── vk() ─────────────────────────────────────────────────────────────────

    public function test_vk_connected_when_all_required_keys_set(): void
    {
        $settings = $this->makeSettings([
            'vk.token' => 'tok',
            'vk.secret_key' => 'sec',
            'vk.confirm_code' => 'code',
        ]);
        $service = new ChannelStatusService($settings);

        $status = $service->vk();

        $this->assertTrue($status['connected']);
        $this->assertSame('Подключён', $status['label']);
    }

    public function test_vk_not_connected_when_confirm_code_missing(): void
    {
        $settings = $this->makeSettings([
            'vk.token' => 'tok',
            'vk.secret_key' => 'sec',
            'vk.confirm_code' => '',
        ]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->vk()['connected']);
    }

    public function test_vk_not_connected_when_token_missing(): void
    {
        $settings = $this->makeSettings([
            'vk.token' => null,
            'vk.secret_key' => 'sec',
            'vk.confirm_code' => 'code',
        ]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->vk()['connected']);
    }

    // ── max() ─────────────────────────────────────────────────────────────────

    public function test_max_connected_when_all_required_keys_set(): void
    {
        $settings = $this->makeSettings([
            'max.token' => 'tok',
            'max.secret_key' => 'sec',
        ]);
        $service = new ChannelStatusService($settings);

        $status = $service->max();

        $this->assertTrue($status['connected']);
        $this->assertSame('Подключён', $status['label']);
    }

    public function test_max_not_connected_when_token_missing(): void
    {
        $settings = $this->makeSettings([
            'max.token' => '',
            'max.secret_key' => 'sec',
        ]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->max()['connected']);
    }

    public function test_max_not_connected_when_secret_missing(): void
    {
        $settings = $this->makeSettings([
            'max.token' => 'tok',
            'max.secret_key' => null,
        ]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->max()['connected']);
    }

    // ── email() ──────────────────────────────────────────────────────────────

    public function test_email_connected_when_all_required_keys_set(): void
    {
        $settings = $this->makeSettings([
            'email.imap_host' => 'imap.example.com',
            'email.smtp_host' => 'smtp.example.com',
            'email.username' => 'support@example.com',
            'email.password' => 'secret',
        ]);
        $service = new ChannelStatusService($settings);

        $status = $service->email();

        $this->assertTrue($status['connected']);
        $this->assertSame('Подключён', $status['label']);
    }

    public function test_email_not_connected_when_smtp_host_missing(): void
    {
        $settings = $this->makeSettings([
            'email.imap_host' => 'imap.example.com',
            'email.smtp_host' => '',
            'email.username' => 'support@example.com',
            'email.password' => 'secret',
        ]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->email()['connected']);
        $this->assertSame('Не настроен', $service->email()['label']);
    }

    public function test_email_not_connected_when_all_keys_missing(): void
    {
        $settings = $this->makeSettings([]);
        $service = new ChannelStatusService($settings);

        $this->assertFalse($service->email()['connected']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a SettingsService mock that returns given key→value pairs.
     * Keys not present in the map return null.
     *
     * @param array<string, mixed> $values
     */
    private function makeSettings(array $values): SettingsService
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);

        foreach ($values as $key => $value) {
            $mock->shouldReceive('get')->with($key)->andReturn($value);
        }

        // Keys not in the map return null
        $mock->shouldReceive('get')->andReturn(null);

        return $mock;
    }
}
