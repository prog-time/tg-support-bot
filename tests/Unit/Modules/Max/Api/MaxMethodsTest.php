<?php

namespace Tests\Unit\Modules\Max\Api;

use App\Modules\Max\Api\MaxMethods;
use App\Modules\Max\DTOs\MaxAnswerDto;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaxMethodsTest extends TestCase
{
    public function test_send_query_returns_error_dto_for_unknown_method(): void
    {
        $methods = new MaxMethods();

        $result = $methods->sendQuery('unknownMethod', ['user_id' => 123]);

        $this->assertInstanceOf(MaxAnswerDto::class, $result);
        $this->assertSame(500, $result->response_code);
        $this->assertStringContainsString('Unknown method', $result->error_message ?? '');
    }

    public function test_send_query_returns_error_dto_when_send_image_fails(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response('Bad Request', 400),
        ]);

        $methods = new MaxMethods();

        $result = $methods->sendQuery('sendImage', [
            'user_id' => 123,
            'file_token' => 'token-abc',
            'text' => 'caption',
        ]);

        $this->assertInstanceOf(MaxAnswerDto::class, $result);
        $this->assertSame(500, $result->response_code);
    }

    public function test_send_query_returns_error_dto_when_send_file_fails(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response('Server Error', 500),
        ]);

        $methods = new MaxMethods();

        $result = $methods->sendQuery('sendFile', [
            'user_id' => 123,
            'file_token' => 'token-xyz',
            'text' => '',
        ]);

        $this->assertInstanceOf(MaxAnswerDto::class, $result);
        $this->assertSame(500, $result->response_code);
    }

    public function test_send_query_returns_success_dto_when_send_image_succeeds(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response([
                'message' => [
                    'body' => ['mid' => 'msg-image-001'],
                ],
            ], 200),
        ]);

        $methods = new MaxMethods();

        $result = $methods->sendQuery('sendImage', [
            'user_id' => 123,
            'file_token' => 'token-abc',
            'text' => 'photo caption',
        ]);

        $this->assertInstanceOf(MaxAnswerDto::class, $result);
        $this->assertSame(200, $result->response_code);
        $this->assertSame('msg-image-001', $result->response);
    }

    public function test_send_query_returns_success_dto_when_send_file_succeeds(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response([
                'message' => [
                    'body' => ['mid' => 'msg-file-002'],
                ],
            ], 200),
        ]);

        $methods = new MaxMethods();

        $result = $methods->sendQuery('sendFile', [
            'user_id' => 456,
            'file_token' => 'token-xyz',
            'text' => '',
        ]);

        $this->assertInstanceOf(MaxAnswerDto::class, $result);
        $this->assertSame(200, $result->response_code);
        $this->assertSame('msg-file-002', $result->response);
    }
}
