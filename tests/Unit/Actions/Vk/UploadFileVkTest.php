<?php

namespace Tests\Unit\Actions\Vk;

use App\Actions\Telegram\GetFile;
use App\Actions\Vk\GetMessagesUploadServerVk;
use App\Actions\Vk\UploadFileVk;
use App\Helpers\TelegramHelper;
use Tests\TestCase;

class UploadFileVkTest extends TestCase
{
    private int $chatId;

    private string $photoFileId;

    private string $documentFileId;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = (int)config('testing.vk_private.chat_id');

        $this->photoFileId = config('testing.tg_file.photo');
        $this->documentFileId = config('testing.tg_file.document');
    }

    public function test_upload_photo(): void
    {
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

        $this->assertNotEmpty($responseData['photo']);
    }

    public function test_upload_docs(): void
    {
        $docFileId = $this->documentFileId;

        $fileData = GetFile::execute($docFileId);
        $this->assertNotEmpty($fileData->rawData['result']['file_path']);

        $fullFilePath = TelegramHelper::getFileTelegramPath($docFileId);
        $this->assertNotEmpty($fullFilePath);

        // get upload server data
        $resultData = GetMessagesUploadServerVk::execute($this->chatId, 'docs');
        $this->assertNotEmpty($resultData->response['upload_url']);

        // upload file in VK
        $urlQuery = $resultData->response['upload_url'];
        $responseData = UploadFileVk::execute($urlQuery, $fullFilePath, 'doc');

        $this->assertNotEmpty($responseData['file']);
    }
}
