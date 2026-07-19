<?php

namespace Tests\Unit\Modules\Avito\DTOs;

use App\Modules\Avito\DTOs\AvitoUpdateDto;
use Illuminate\Http\Request;
use Tests\TestCase;

class AvitoUpdateDtoTest extends TestCase
{
    private function request(array $payload): Request
    {
        return Request::create(
            '/api/avito/bot',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($payload),
        );
    }

    public function test_parses_v3_message_payload(): void
    {
        $dto = AvitoUpdateDto::fromRequest($this->request([
            'id' => 'evt-1',
            'version' => 'v3.0.0',
            'timestamp' => 1700000000,
            'payload' => [
                'type' => 'message',
                'value' => [
                    'id' => 'msg-1',
                    'chat_id' => 'u2i-abc',
                    'user_id' => 555,
                    'author_id' => 777,
                    'type' => 'text',
                    'content' => ['text' => 'Здравствуйте'],
                ],
            ],
        ]));

        $this->assertNotNull($dto);
        $this->assertSame('evt-1', $dto->event_id);
        $this->assertSame('message', $dto->type);
        $this->assertSame('u2i-abc', $dto->chatId);
        $this->assertSame(777, $dto->author_id);
        $this->assertSame('msg-1', $dto->message_id);
        $this->assertSame('Здравствуйте', $dto->text);
    }

    public function test_returns_null_without_chat_id(): void
    {
        $this->assertNull(AvitoUpdateDto::fromRequest($this->request([
            'id' => 'evt-2',
            'payload' => ['type' => 'message', 'value' => ['content' => ['text' => 'x']]],
        ])));
    }

    public function test_returns_null_for_unparseable_payload(): void
    {
        $request = Request::create(
            '/api/avito/bot',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'not-json',
        );

        $this->assertNull(AvitoUpdateDto::fromRequest($request));
    }
}
