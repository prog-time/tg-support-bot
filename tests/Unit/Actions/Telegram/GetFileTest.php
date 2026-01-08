<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\GetFile;
use App\DTOs\TelegramAnswerDto;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class GetFileTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_returns_telegram_answer_dto(): void
    {
        $fileId = 'abc123';

        $data = [
            'ok' => true,
            'result' => [
                'file_id' => $fileId,
                'file_path' => 'path/to/file.jpg',
            ],
        ];
        $expectedDto = TelegramAnswerDto::fromData($data);

        Http::fake([
            'https://api.telegram.org/*/getFile*' => Http::response($data, 200),
        ]);

        $result = GetFile::execute($fileId);

        $this->assertInstanceOf(TelegramAnswerDto::class, $result);
        $this->assertEquals($expectedDto->rawData['result']['file_path'], $result->rawData['result']['file_path']);
    }
}
