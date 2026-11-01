<?php

namespace Tests\Unit\Modules\Admin\Services;

use App\Modules\Admin\Services\OkVerificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OkVerificationServiceTest extends TestCase
{
    public function test_valid_token_succeeds_with_a_read_only_request(): void
    {
        Http::fake(['*' => Http::response(['subscriptions' => []])]);

        $this->assertTrue((new OkVerificationService())->verify('token')['success']);

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.ok.ru/graph/me/subscriptions'));
    }

    public function test_error_code_in_a_200_response_fails(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(fn ($message, $context) => !str_contains((string) json_encode($context), 'bad-token'));
        Http::fake(['*' => Http::response(['error_code' => 102, 'error_msg' => 'invalid'])]);

        $this->assertFalse((new OkVerificationService())->verify('bad-token')['success']);
    }

    public function test_network_error_fails_without_quoting_it(): void
    {
        Http::fake(fn ($request) => throw new ConnectionException('cURL error for ' . $request->url()));

        $result = (new OkVerificationService())->verify('secret-token');

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('secret-token', $result['message']);
    }

    public function test_blank_token_fails_without_a_request(): void
    {
        Http::fake();

        $this->assertFalse((new OkVerificationService())->verify('')['success']);

        Http::assertNothingSent();
    }
}
