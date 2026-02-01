<?php

namespace Tests\Unit\VkBot;

use App\DTOs\Vk\VkAnswerDto;
use App\VkBot\VkMethods;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VkMethodsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('traffic_source.settings.vk.token', 'fake_token');
    }

    public function test_send_query_vk_success(): void
    {
        Http::fake([
            'api.vk.com/*' => Http::response([
                'response' => ['message_id' => 1],
            ], 200),
        ]);

        $dto = VkMethods::sendQueryVk('messages.send', [
            'user_id' => 1,
            'message' => 'test',
        ]);

        $this->assertInstanceOf(VkAnswerDto::class, $dto);
        $this->assertEquals(0, $dto->error_message);
    }

    public function test_send_query_vk_vk_error(): void
    {
        Http::fake([
            'api.vk.com/*' => Http::response([
                'error' => [
                    'error_msg' => 'Access denied',
                ],
            ], 200),
        ]);

        $dto = VkMethods::sendQueryVk('messages.send', []);

        $this->assertEquals(500, $dto->response_code);
        $this->assertEquals('Access denied', $dto->error_message);
    }

    public function test_send_query_vk_http_failure(): void
    {
        Http::fake([
            'api.vk.com/*' => Http::response(null, 500),
        ]);

        $dto = VkMethods::sendQueryVk('messages.send', []);

        $this->assertEquals(500, $dto->response_code);
        $this->assertEquals('Request sending error', $dto->error_message);
    }
}
