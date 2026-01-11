<?php

namespace Tests\Unit\Actions\Vk;

use App\Actions\Vk\GetMessagesUploadServerVk;
use App\Models\BotUser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GetMessagesUploadServerVkTest extends TestCase
{
    private int $chatId;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = time();

        $this->botUser = BotUser::getUserByChatId($this->chatId, 'vk');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_error_type_get_upload_server(): void
    {
        Http::fake([
            'api.vk.com/*' => Http::response([
                'error' => [
                    'error_code' => 100,
                    'error_msg' => 'Unknown method passed',
                ],
            ], 500),
        ]);

        $result = GetMessagesUploadServerVk::execute($this->chatId, 'plomb');

        $this->assertEquals(500, $result->response_code);
        $this->assertEquals('METHOD_PASSED', $result->error_type);
    }

    public function test_get_upload_server_docs(): void
    {
        Http::fake([
            'api.vk.com/*' => Http::response([
                'response' => [
                    'upload_url' => 'https://vk.com/upload/123',
                ],
            ], 200),
        ]);

        $result = GetMessagesUploadServerVk::execute($this->chatId, 'docs');

        $this->assertEquals(200, $result->response_code);
        $this->assertNotEmpty($result->response['upload_url']);
    }
}
