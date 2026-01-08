<?php

namespace Tests\Unit\Helpers;

use App\Helpers\TelegramHelper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramHelperTest extends TestCase
{
    public function test_get_file_path(): void
    {
        $telegramToken = config('traffic_source.settings.telegram.token');
        $localFilePath = 'storage/test.jpg';
        $successValue = "https://api.telegram.org/file/bot{$telegramToken}/{$localFilePath}";

        $filePath = TelegramHelper::getFilePath($localFilePath);

        $this->assertNotEmpty($filePath);
        $this->assertEquals($successValue, $filePath);
    }

    public function test_get_file_public_path(): void
    {
        $appUrl = trim(config('app.url'), '/');
        $fileId = 'test_file_id';
        $successValue = "{$appUrl}/api/files/{$fileId}";

        $filePath = TelegramHelper::getFilePublicPath($fileId);

        $this->assertNotEmpty($filePath);
        $this->assertEquals($successValue, $filePath);

        $response = Http::get($filePath);

        $this->assertTrue($response->successful());
    }

    /**
     * Тестируем извлечение file_id из фото (берём последний элемент массива)
     */
    public function test_extract_file_id_from_photo(): void
    {
        $data = [
            'message' => [
                'photo' => [
                    ['file_id' => 'photo_small_id'],
                    ['file_id' => 'photo_medium_id'],
                    ['file_id' => 'photo_large_id'], // ← должен взять этот
                ],
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertEquals('photo_large_id', $fileId);
    }

    /**
     * Тестируем извлечение file_id из документа
     */
    public function test_extract_file_id_from_document(): void
    {
        $data = [
            'message' => [
                'document' => [
                    'file_id' => 'doc_12345',
                ],
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertEquals('doc_12345', $fileId);
    }

    /**
     * Тестируем извлечение file_id из голосового сообщения
     */
    public function test_extract_file_id_from_voice(): void
    {
        $data = [
            'message' => [
                'voice' => [
                    'file_id' => 'voice_abcde',
                ],
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertEquals('voice_abcde', $fileId);
    }

    /**
     * Тестируем извлечение file_id из стикера
     */
    public function test_extract_file_id_from_sticker(): void
    {
        $data = [
            'message' => [
                'sticker' => [
                    'file_id' => 'sticker_xyz',
                ],
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertEquals('sticker_xyz', $fileId);
    }

    /**
     * Тестируем извлечение file_id из видеосообщения (кругляш)
     */
    public function test_extract_file_id_from_video_note(): void
    {
        $data = [
            'message' => [
                'video_note' => [
                    'file_id' => 'videonote_999',
                ],
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertEquals('videonote_999', $fileId);
    }

    /**
     * Тестируем, когда ни один тип не присутствует → null
     */
    public function test_returns_null_when_no_supported_type(): void
    {
        $data = [
            'message' => [
                'text' => 'Hello world',
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertNull($fileId);
    }

    /**
     * Тестируем, когда message отсутствует → null
     */
    public function test_returns_null_when_no_message(): void
    {
        $data = [];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertNull($fileId);
    }

    /**
     * Тестируем, когда photo пустой → переходим к следующему условию (но его нет) → null
     */
    public function test_returns_null_when_photo_is_empty_array(): void
    {
        $data = [
            'message' => [
                'photo' => [],
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertNull($fileId);
    }

    /**
     * Тестируем, когда photo есть, но последний элемент не содержит file_id → null
     */
    public function test_returns_null_when_last_photo_has_no_file_id(): void
    {
        $data = [
            'message' => [
                'photo' => [
                    ['file_id' => 'first'],
                    ['not_file_id' => 'something'], // ← последний, но нет file_id
                ],
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertNull($fileId);
    }

    /**
     * Тестируем приоритет: если есть и photo и document — должен взять photo (первое условие)
     */
    public function test_photo_has_priority_over_document(): void
    {
        $data = [
            'message' => [
                'photo' => [
                    ['file_id' => 'photo_id'],
                ],
                'document' => [
                    'file_id' => 'doc_id',
                ],
            ],
        ];

        $fileId = TelegramHelper::extractFileId($data);

        $this->assertEquals('photo_id', $fileId);
    }
}
