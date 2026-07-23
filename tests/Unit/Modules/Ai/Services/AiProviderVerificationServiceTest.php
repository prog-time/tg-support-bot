<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ai\Services;

use App\Modules\Ai\Services\AiProviderVerificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for AiProviderVerificationService.
 *
 * All provider calls are stubbed with Http::fake — no real network access.
 */
class AiProviderVerificationServiceTest extends TestCase
{
    private AiProviderVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiProviderVerificationService();
    }

    // ── OpenAI ─────────────────────────────────────────────────────────────────

    public function test_verify_openai_succeeds_on_2xx(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);

        $result = $this->service->verifyOpenai('sk-test', 'https://api.openai.com/v1', 'gpt-4o-mini');

        $this->assertTrue($result['success']);
    }

    public function test_verify_openai_fails_on_401(): void
    {
        Http::fake(['*' => Http::response([], 401)]);

        $result = $this->service->verifyOpenai('bad-key', 'https://api.openai.com/v1', 'gpt-4o-mini');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('401', $result['message']);
    }

    public function test_verify_openai_requires_key(): void
    {
        Http::fake();

        $result = $this->service->verifyOpenai('', 'https://api.openai.com/v1', 'gpt-4o-mini');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_verify_openai_requires_base_url_and_model(): void
    {
        Http::fake();

        $result = $this->service->verifyOpenai('sk-test', '', '');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_verify_openai_returns_failure_on_transport_error(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });

        $result = $this->service->verifyOpenai('sk-test', 'https://api.openai.com/v1', 'gpt-4o-mini');

        $this->assertFalse($result['success']);
    }

    // ── DeepSeek ───────────────────────────────────────────────────────────────

    public function test_verify_deepseek_succeeds_on_2xx(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);

        $result = $this->service->verifyDeepseek('secret', 'https://api.deepseek.com/chat/completions', 'deepseek-chat');

        $this->assertTrue($result['success']);
    }

    public function test_verify_deepseek_fails_on_402(): void
    {
        Http::fake(['*' => Http::response([], 402)]);

        $result = $this->service->verifyDeepseek('secret', 'https://api.deepseek.com/chat/completions', 'deepseek-chat');

        $this->assertFalse($result['success']);
    }

    public function test_verify_deepseek_requires_secret(): void
    {
        Http::fake();

        $result = $this->service->verifyDeepseek('', 'https://api.deepseek.com/chat/completions', 'deepseek-chat');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    // ── GigaChat ───────────────────────────────────────────────────────────────

    public function test_verify_gigachat_succeeds_when_token_returned(): void
    {
        Http::fake(['*' => Http::response(['access_token' => 'abc', 'expires_at' => time() + 1800], 200)]);

        $result = $this->service->verifyGigachat('base64secret', '/tmp/ca.crt');

        $this->assertTrue($result['success']);
    }

    public function test_verify_gigachat_fails_when_no_token(): void
    {
        Http::fake(['*' => Http::response([], 401)]);

        $result = $this->service->verifyGigachat('base64secret', '/tmp/ca.crt');

        $this->assertFalse($result['success']);
    }

    public function test_verify_gigachat_requires_secret(): void
    {
        Http::fake();

        $result = $this->service->verifyGigachat('', '/tmp/ca.crt');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_verify_gigachat_requires_certificate(): void
    {
        Http::fake();

        $result = $this->service->verifyGigachat('base64secret', '');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_verify_gigachat_sends_provided_scope(): void
    {
        Http::fake(['*' => Http::response(['access_token' => 'abc', 'expires_at' => time() + 1800], 200)]);

        $this->service->verifyGigachat('base64secret', '/tmp/ca.crt', 'GIGACHAT_API_B2B');

        Http::assertSent(fn ($request) => ($request['scope'] ?? null) === 'GIGACHAT_API_B2B');
    }

    public function test_verify_gigachat_surfaces_error_detail_on_failure(): void
    {
        Http::fake(['*' => Http::response(['message' => 'invalid scope'], 400)]);

        $result = $this->service->verifyGigachat('base64secret', '/tmp/ca.crt', 'GIGACHAT_API_PERS');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('400', $result['message']);
        $this->assertStringContainsString('invalid scope', $result['message']);
    }
}
