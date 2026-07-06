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
