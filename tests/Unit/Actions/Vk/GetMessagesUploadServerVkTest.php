<?php

namespace Tests\Unit\Actions\Vk;

use App\Actions\Vk\GetMessagesUploadServerVk;
use Tests\TestCase;

class GetMessagesUploadServerVkTest extends TestCase
{
    private int $chatId;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = (int)config('testing.vk_private.chat_id');
    }

    public function test_error_type_get_upload_server(): void
    {
        $result = GetMessagesUploadServerVk::execute($this->chatId, 'plomb');

        $this->assertEquals(500, $result->response_code);
        $this->assertEquals('METHOD_PASSED', $result->error_type);
    }

    public function test_get_upload_server_docs(): void
    {
        $result = GetMessagesUploadServerVk::execute($this->chatId, 'docs');

        $this->assertEquals(200, $result->response_code);
        $this->assertNotEmpty($result->response['upload_url']);
    }
}
