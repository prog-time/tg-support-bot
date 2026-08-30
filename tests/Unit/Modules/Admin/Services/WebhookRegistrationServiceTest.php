<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Admin\Services;

use App\Modules\Admin\Services\WebhookRegistrationService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for WebhookRegistrationService.
 *
 * Uses Http::fake() to intercept outgoing API calls and a mocked SettingsService.
 * No real network calls are made.
 */
class WebhookRegistrationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    // ── registerTelegram() ───────────────────────────────────────────────────

    public function test_register_telegram_returns_error_when_token_empty(): void
    {
        $settings = $this->makeSettings(['telegram.token' => '', 'telegram.secret_key' => '']);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerTelegram();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('не задан', $result['message']);
    }

    public function test_register_telegram_returns_success_on_ok_response(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'description' => 'Webhook was set',
            ], 200),
        ]);

        $settings = $this->makeSettings([
            'telegram.token' => 'bot123:token',
            'telegram.secret_key' => 'secret',
        ]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerTelegram();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('зарегистрирован', $result['message']);
    }

    public function test_register_telegram_returns_error_on_api_failure(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Unauthorized',
                'error_code' => 401,
            ], 401),
        ]);

        $settings = $this->makeSettings([
            'telegram.token' => 'invalid_token',
            'telegram.secret_key' => 'secret',
        ]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerTelegram();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Ошибка', $result['message']);
    }

    // ── registerVk() ─────────────────────────────────────────────────────────

    public function test_register_vk_returns_error_when_token_empty(): void
    {
        $settings = $this->makeSettings(['vk.token' => '']);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerVk();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('не задан', $result['message']);
    }

    public function test_register_vk_returns_success_on_valid_api_response(): void
    {
        Http::fake([
            'https://api.vk.com/*' => Http::response([
                'response' => [['id' => 123, 'name' => 'Test Group']],
            ], 200),
        ]);

        $settings = $this->makeSettings(['vk.token' => 'vk_token_abc']);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerVk();

        $this->assertTrue($result['success']);
    }

    public function test_register_vk_returns_error_on_vk_api_error(): void
    {
        Http::fake([
            'https://api.vk.com/*' => Http::response([
                'error' => ['error_code' => 5, 'error_msg' => 'User authorization failed'],
            ], 200),
        ]);

        $settings = $this->makeSettings(['vk.token' => 'vk_token_abc']);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerVk();

        $this->assertFalse($result['success']);
    }

    // ── registerMax() ────────────────────────────────────────────────────────

    public function test_register_max_returns_error_when_token_empty(): void
    {
        $settings = $this->makeSettings(['max.token' => '']);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerMax();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('не задан', $result['message']);
    }

    public function test_register_max_returns_success_on_200_response(): void
    {
        Http::fake([
            'https://platform-api.max.ru/*' => Http::response(['result' => 'ok'], 200),
        ]);

        $settings = $this->makeSettings(['max.token' => 'max_token']);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerMax();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('зарегистрирован', $result['message']);
    }

    public function test_register_max_returns_error_on_non_200_response(): void
    {
        Http::fake([
            'https://platform-api.max.ru/*' => Http::response(['error' => 'invalid token'], 401),
        ]);

        $settings = $this->makeSettings(['max.token' => 'bad_token']);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerMax();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('401', $result['message']);
    }

    public function test_register_max_sends_secret_in_subscription_payload(): void
    {
        Http::fake([
            'https://platform-api.max.ru/*' => Http::response(['result' => 'ok'], 200),
        ]);

        $settings = $this->makeSettings([
            'max.token' => 'max_token',
            'max.secret_key' => 'max_secret',
        ]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->registerMax();

        $this->assertTrue($result['success']);

        // MAX must receive the secret so it echoes it back in X-Max-Bot-Api-Secret
        // (otherwise MaxQuery rejects every incoming webhook with 403).
        Http::assertSent(function ($request) {
            return $request->url() === 'https://platform-api.max.ru/subscriptions'
                && ($request['secret'] ?? null) === 'max_secret'
                && str_contains((string) ($request['url'] ?? ''), '/api/max/bot');
        });
    }

    // ── verifyTelegram() ─────────────────────────────────────────────────────

    public function test_verify_telegram_returns_error_when_token_empty(): void
    {
        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyTelegram('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('не задан', $result['message']);
    }

    public function test_verify_telegram_returns_success_when_get_me_ok(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['id' => 123, 'is_bot' => true, 'first_name' => 'TestBot'],
            ], 200),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyTelegram('bot123:validtoken');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('прошёл проверку', $result['message']);
    }

    public function test_verify_telegram_returns_error_when_get_me_fails(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Unauthorized',
                'error_code' => 401,
            ], 401),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyTelegram('bad_token');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Неверный токен Telegram', $result['message']);
    }

    public function test_verify_telegram_logs_error_when_get_me_fails(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Unauthorized',
                'error_code' => 401,
            ], 401),
        ]);

        Log::shouldReceive('channel')->with('app')->andReturnSelf();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'getMe')
                    && ($context['description'] ?? null) === 'Unauthorized';
            });

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $service->verifyTelegram('bad_token');
    }

    public function test_verify_telegram_returns_error_when_group_inaccessible(): void
    {
        Http::fake([
            'https://api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true]], 200),
            'https://api.telegram.org/*/getChat' => Http::response(['ok' => false, 'description' => 'chat not found', 'error_code' => 400], 400),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyTelegram('bot123:validtoken', '-100999999');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('группы', $result['message']);
    }

    public function test_verify_telegram_logs_error_when_group_inaccessible(): void
    {
        Http::fake([
            'https://api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true]], 200),
            'https://api.telegram.org/*/getChat' => Http::response(['ok' => false, 'description' => 'chat not found', 'error_code' => 400], 400),
        ]);

        Log::shouldReceive('channel')->with('app')->andReturnSelf();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'getChat')
                    && ($context['group_id'] ?? null) === '-100999999';
            });

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $service->verifyTelegram('bot123:validtoken', '-100999999');
    }

    public function test_verify_telegram_logs_warning_when_bot_not_admin_in_group(): void
    {
        Http::fake([
            'https://api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true]], 200),
            'https://api.telegram.org/*/getChat' => Http::response(['ok' => true, 'result' => ['id' => -1001234567890, 'type' => 'supergroup']], 200),
            'https://api.telegram.org/*/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'member']], 200),
        ]);

        Log::shouldReceive('channel')->with('app')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'not admin')
                    && ($context['status'] ?? null) === 'member';
            });

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $service->verifyTelegram('bot123:validtoken', '-1001234567890');
    }

    public function test_verify_telegram_returns_success_with_valid_group(): void
    {
        Http::fake([
            'https://api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true]], 200),
            'https://api.telegram.org/*/getChat' => Http::response(['ok' => true, 'result' => ['id' => -1001234567890, 'type' => 'supergroup']], 200),
            'https://api.telegram.org/*/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'administrator']], 200),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyTelegram('bot123:validtoken', '-1001234567890');

        $this->assertTrue($result['success']);
    }

    public function test_verify_telegram_returns_error_when_bot_not_admin_in_group(): void
    {
        Http::fake([
            'https://api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true]], 200),
            'https://api.telegram.org/*/getChat' => Http::response(['ok' => true, 'result' => ['id' => -1001234567890, 'type' => 'supergroup']], 200),
            'https://api.telegram.org/*/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'member']], 200),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyTelegram('bot123:validtoken', '-1001234567890');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('администратор', $result['message']);
    }

    // ── verifyVk() ───────────────────────────────────────────────────────────

    public function test_verify_vk_returns_error_when_token_empty(): void
    {
        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyVk('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('не задан', $result['message']);
    }

    public function test_verify_vk_returns_success_on_valid_response(): void
    {
        Http::fake([
            'https://api.vk.com/*' => Http::response([
                'response' => [['id' => 1, 'name' => 'TestGroup']],
            ], 200),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyVk('vk1.a.valid_token');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('прошёл проверку', $result['message']);
    }

    public function test_verify_vk_returns_error_on_api_error_response(): void
    {
        Http::fake([
            'https://api.vk.com/*' => Http::response([
                'error' => ['error_code' => 5, 'error_msg' => 'User authorization failed'],
            ], 200),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyVk('bad_vk_token');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Ошибка VK API', $result['message']);
    }

    // ── verifyMax() ──────────────────────────────────────────────────────────

    public function test_verify_max_returns_error_when_token_empty(): void
    {
        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyMax('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('не задан', $result['message']);
    }

    public function test_verify_max_returns_success_on_200_response(): void
    {
        Http::fake([
            'https://platform-api.max.ru/*' => Http::response(['id' => 1, 'name' => 'TestBot'], 200),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyMax('max_valid_token');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('прошёл проверку', $result['message']);
    }

    public function test_verify_max_returns_error_on_non_200_response(): void
    {
        Http::fake([
            'https://platform-api.max.ru/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $settings = $this->makeSettings([]);
        $service = new WebhookRegistrationService($settings);

        $result = $service->verifyMax('bad_max_token');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('401', $result['message']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
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
