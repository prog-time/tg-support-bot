<?php

namespace Tests\Unit\Actions\VK;

use App\Actions\Telegram\GetFile;
use App\Actions\VK\GetMessagesUploadServerVk;
use App\Actions\VK\UploadFileVk;
use App\Helpers\TelegramHelper;
use Tests\TestCase;

class UploadFileVkTest extends TestCase
{
    private int $chatId;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = (int)config('testing.vk_private.chat_id');
    }

    public function test_upload_photo(): void
    {
        $photoFileId = 'AgACAgIAAxkBAAIHO2i-0nqM0rxqaqBPjrcf9937EzNRAAJw-jEbLrv5SSpf9j0qc59iAQADAgADeQADNgQ';

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
        $photoFileId = 'AgACAgIAAxkBAAIHO2i-0nqM0rxqaqBPjrcf9937EzNRAAJw-jEbLrv5SSpf9j0qc59iAQADAgADeQADNgQ';

        $fileData = GetFile::execute($photoFileId);
        $this->assertNotEmpty($fileData->rawData['result']['file_path']);

        $fullFilePath = TelegramHelper::getFileTelegramPath($photoFileId);
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
