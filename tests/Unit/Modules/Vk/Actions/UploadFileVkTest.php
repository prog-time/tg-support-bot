<?php

namespace Tests\Unit\Modules\Vk\Actions;

use App\Helpers\TelegramHelper;
use App\Modules\Telegram\Actions\GetFile;
use App\Modules\Vk\Actions\GetMessagesUploadServerVk;
use App\Modules\Vk\Actions\UploadFileVk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class UploadFileVkTest extends TestCase
{
    use RefreshDatabase;

    private int $chatId;

    private string $photoFileId;

    protected string $botToken;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = time();

        $this->photoFileId = 'test_file_id';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_upload_photo(): void
    {
        $fileName = 'documents/file.pdf';
        $tgFileUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$fileName}";
        $vkUploadFileUrl = 'https://vk.com/stub_file/123456789_987654321';
        $uploadFileVkResponse = [
            'server' => 123456,
            'file' => '{"file":"ABCD1234"}',
            'hash' => 'abcdef1234567890',
        ];

        Http::fake([
            // getFile
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'ABC123',
                    'file_unique_id' => 'UNIQUE123',
                    'file_size' => 12345,
                    'file_path' => $fileName,
                ],
            ], 200),

            // tg file data
            $tgFileUrl => Http::response(
                'FAKE_BINARY_CONTENT', // тут можно любой контент
                200,
                ['Content-Type' => 'image/jpeg']
            ),

            // get upload server
            'https://api.vk.com/method/photos.getMessagesUploadServer' => Http::response([
                'response' => [
                    'upload_url' => $vkUploadFileUrl,
                ],
            ], 200),

            // upload file to vk
            'https://vk.com/stub_file/*' => Http::response($uploadFileVkResponse, 200),

            // save file
            'https://api.vk.com/method/photos.saveMessagesPhoto*' => Http::response([
                'response' => [
                    [
                        'id' => 1,
                        'owner_id' => 1,
                    ],
                ],
            ], 200),
        ]);

        $photoFileId = $this->photoFileId;

        $fileData = app(GetFile::class)->execute($photoFileId);
        $this->assertNotEmpty($fileData->rawData['result']['file_path']);

        $fullFilePath = TelegramHelper::getFileTelegramPath($photoFileId);
        $this->assertNotEmpty($fullFilePath);

        // get upload server data
        $resultData = app(GetMessagesUploadServerVk::class)->execute($this->chatId, 'photos');
        $this->assertNotEmpty($resultData->response['upload_url']);

        // upload file in VK
        $urlQuery = $resultData->response['upload_url'];
        $responseData = app(UploadFileVk::class)->execute($urlQuery, $fullFilePath, 'photo');

        $this->assertNotEmpty($responseData['file']);
    }

    public function test_only_telegram_download_goes_through_telegram_proxy(): void
    {
        config(['traffic_source.telegram.proxy' => 'socks5h://10.0.0.1:1080']);
        $proxiesByHost = [];

        Http::fake(function ($request, array $options) use (&$proxiesByHost) {
            $proxiesByHost[parse_url($request->url(), PHP_URL_HOST)] = $options['proxy'] ?? null;

            return str_contains($request->url(), 'api.telegram.org')
                ? Http::response('FILE_CONTENT')
                : Http::response(['file' => 'ABCD1234']);
        });

        $result = app(UploadFileVk::class)->execute(
            'https://pu.vk.com/upload',
            'https://api.telegram.org/file/botTOKEN/documents/file.pdf',
        );

        $this->assertSame('ABCD1234', $result['file']);
        $this->assertSame('socks5h://10.0.0.1:1080', $proxiesByHost['api.telegram.org']);
        $this->assertNull($proxiesByHost['pu.vk.com']);
    }
}
