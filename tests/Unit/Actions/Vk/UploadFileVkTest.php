<?php

namespace Tests\Unit\Actions\Vk;

use App\Actions\Telegram\GetFile;
use App\Actions\Vk\GetMessagesUploadServerVk;
use App\Actions\Vk\UploadFileVk;
use App\Helpers\TelegramHelper;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class UploadFileVkTest extends TestCase
{
    private int $chatId;

    private string $photoFileId;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = time();

        config()->set('traffic_source.settings.telegram.token', '123:ABC');

        $this->photoFileId = 'test_file_id';
    }

    public function test_upload_photo(): void
    {
        $fileName = 'documents/file.pdf';
        $tgFileUrl = "https://api.telegram.org/file/bot123:ABC/{$fileName}";
        $vkUploadFileUrl = 'https://vk.com/stub_file/123456789_987654321';
        $UploadFileVkResponse = [
            'server' => 123456,
            'file' => '{"file":"ABCD1234"}',
            'hash' => 'abcdef1234567890',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'ABC123',
                    'file_unique_id' => 'UNIQUE123',
                    'file_size' => 12345,
                    'file_path' => $fileName,
                ],
            ], 200),
        ]);

        Http::fake([
            'api.vk.com/*' => Http::response([
                'response' => [
                    'upload_url' => $vkUploadFileUrl,
                ],
            ], 200),
        ]);

        Mockery::mock('alias:App\Actions\Vk\UploadFileVk')
            ->shouldReceive('execute')
            ->with($vkUploadFileUrl, $tgFileUrl, 'photo')
            ->andReturn($UploadFileVkResponse);

        $photoFileId = $this->photoFileId;

        $fileData = GetFile::execute($photoFileId);
        $this->assertNotEmpty($fileData->rawData['result']['file_path']);

        $fullFilePath = TelegramHelper::getFileTelegramPath($photoFileId);
        $this->assertNotEmpty($fullFilePath);

        // get upload server data
        $resultData = GetMessagesUploadServerVk::execute($this->chatId, 'photos');
        $this->assertNotEmpty($resultData->response['upload_url']);

        // upload file in VK
        $urlQuery = $resultData->response['upload_url'];
        $responseData = UploadFileVk::execute($urlQuery, $fullFilePath, 'photo');

        $this->assertNotEmpty($responseData['file']);
    }
}
