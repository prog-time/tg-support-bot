<?php

namespace Tests\Unit\Modules\Admin\Services;

use App\Modules\Admin\Services\AvitoVerificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for AvitoVerificationService.
 *
 * Verifies Avito API credentials by requesting an OAuth token and resolving the
 * authenticated account (accounts/self), returning the account id used as the
 * messenger user_id. All HTTP is faked — no real API calls.
 */
class AvitoVerificationServiceTest extends TestCase
{
    public function test_verify_returns_account_id_on_success(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['id' => 12345678, 'name' => 'Shop'], 200),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'csecret');

        $this->assertTrue($result['success']);
        $this->assertSame('12345678', $result['accountId']);
        $this->assertSame('Shop', $result['accountName']);
    }

    public function test_messenger_plan_is_reported_missing_on_402(): void
    {
        // The live failure this check exists for: credentials are perfectly valid,
        // the webhook subscription is accepted, and Avito still never delivers —
        // because the paid «API мессенджера» plan is not active. The only honest
        // signal is the 402 from the messages endpoint.
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'tok'], 200),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['id' => 42, 'name' => 'Shop'], 200),
            'https://api.avito.ru/messenger/v2/accounts/42/chats*' => Http::response(['chats' => [['id' => 'u2i-x']]], 200),
            'https://api.avito.ru/messenger/v3/accounts/42/chats/u2i-x/messages*' => Http::response(
                ['code' => 402, 'message' => 'Перейдите на подписку с API мессенджера'],
                402
            ),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'csecret');

        $this->assertTrue($result['success'], 'Credentials are still valid.');
        $this->assertSame('no_subscription', $result['messengerStatus']);
        $this->assertStringContainsString('API мессенджера', (string) $result['messengerMessage']);
    }

    public function test_messenger_plan_is_reported_active_on_success(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'tok'], 200),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['id' => 42], 200),
            'https://api.avito.ru/messenger/v2/accounts/42/chats*' => Http::response(['chats' => [['id' => 'u2i-x']]], 200),
            'https://api.avito.ru/messenger/v3/accounts/42/chats/u2i-x/messages*' => Http::response(['messages' => []], 200),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'csecret');

        $this->assertSame('ok', $result['messengerStatus']);
    }

    public function test_messenger_plan_is_unknown_when_there_are_no_chats(): void
    {
        // Without a chat there is nothing to probe the paywall with. Reported as
        // unknown rather than guessed either way.
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'tok'], 200),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['id' => 42], 200),
            'https://api.avito.ru/messenger/v2/accounts/42/chats*' => Http::response(['chats' => []], 200),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'csecret');

        $this->assertSame('unknown', $result['messengerStatus']);
        $this->assertStringContainsString('нет чатов', (string) $result['messengerMessage']);
    }

    public function test_messenger_check_never_fails_the_credential_verification(): void
    {
        // A broken messenger probe must not make valid credentials look invalid.
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'tok'], 200),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['id' => 42], 200),
            'https://api.avito.ru/messenger/*' => Http::response('gateway down', 503),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'csecret');

        $this->assertTrue($result['success']);
        $this->assertSame('unknown', $result['messengerStatus']);
    }

    public function test_verify_fails_without_credentials(): void
    {
        Http::fake();

        $result = (new AvitoVerificationService())->verify('', '');

        $this->assertFalse($result['success']);
        $this->assertNull($result['accountId']);
        Http::assertNothingSent();
    }

    public function test_verify_fails_when_token_request_rejected(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'bad');

        $this->assertFalse($result['success']);
        $this->assertNull($result['accountId']);
    }

    public function test_verify_fails_when_no_access_token_returned(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['expires_in' => 3600], 200),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'csecret');

        $this->assertFalse($result['success']);
    }

    public function test_verify_fails_when_self_request_fails(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'tok'], 200),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'csecret');

        $this->assertFalse($result['success']);
        $this->assertNull($result['accountId']);
    }

    public function test_verify_uses_custom_base_url(): void
    {
        Http::fake([
            'https://sandbox.avito.ru/token' => Http::response(['access_token' => 'tok'], 200),
            'https://sandbox.avito.ru/core/v1/accounts/self' => Http::response(['id' => 9, 'name' => 'S'], 200),
        ]);

        $result = (new AvitoVerificationService())->verify('cid', 'csecret', 'https://sandbox.avito.ru');

        $this->assertTrue($result['success']);
        $this->assertSame('9', $result['accountId']);
    }
}
