<?php

namespace Tests\Unit\Modules\Ok\DTOs;

use App\Modules\Ok\DTOs\OkAttachmentDto;
use App\Modules\Ok\DTOs\OkUpdateDto;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OkUpdateDtoTest extends TestCase
{
    private function fromStub(string $name): ?OkUpdateDto
    {
        $data = json_decode((string) file_get_contents(base_path("tests/Stubs/Ok/{$name}.json")), true);

        return OkUpdateDto::fromRequest(Request::create('/', 'POST', $data));
    }

    public function test_parses_a_text_message(): void
    {
        $dto = $this->fromStub('message_created');

        $this->assertSame('MESSAGE_CREATED', $dto->type);
        $this->assertSame('chat:C3ecb9d02a600', $dto->chatId);
        $this->assertSame('mid.0001', $dto->event_id);
        $this->assertSame('Здравствуйте', $dto->text);
        $this->assertSame('Тест Тестов', $dto->senderName);
        $this->assertSame(123456789012, $dto->numericSenderId());
        $this->assertSame([], $dto->attachments);
    }

    public function test_parses_an_attachment_only_message(): void
    {
        $dto = $this->fromStub('message_attachment_only');

        $this->assertNull($dto->text);
        $this->assertCount(1, $dto->attachments);
        $this->assertSame('sendPhoto', $dto->attachments[0]->method);
        $this->assertSame('https://i.okcdn.ru/i?id=1', $dto->attachments[0]->url);
    }

    public function test_system_event_gets_a_derived_event_id(): void
    {
        $dto = $this->fromStub('chat_started');

        $this->assertSame('CHAT_STARTED', $dto->systemType);
        $this->assertSame('CHAT_SYSTEM:chat:C3ecb9d02a600:1793547168000', $dto->event_id);
    }

    public function test_returns_null_without_a_chat(): void
    {
        $this->assertNull(OkUpdateDto::fromRequest(Request::create('/', 'POST', ['webhookType' => 'MESSAGE_CREATED'])));
    }

    public function test_non_numeric_sender_id_becomes_zero(): void
    {
        $dto = OkUpdateDto::fromRequest(Request::create('/', 'POST', [
            'recipient' => ['chat_id' => 'chat:C1'],
            'sender' => ['user_id' => 'user:abc'],
        ]));

        $this->assertSame(0, $dto->numericSenderId());
    }

    /**
     * @return array<string, array{array<string, mixed>, string, ?string, string}>
     */
    public static function attachments(): array
    {
        $url = 'https://cdn.test/f';

        return [
            'image' => [['type' => 'IMAGE', 'payload' => ['url' => $url]], 'sendPhoto', 'photo', $url],
            'sticker' => [['type' => 'STICKER', 'payload' => ['url' => $url]], 'sendPhoto', 'sticker', $url],
            'video' => [['type' => 'VIDEO', 'payload' => ['url' => $url]], 'sendVideo', 'video_note', $url],
            'audio' => [['type' => 'AUDIO', 'payload' => ['url' => $url]], 'sendAudio', 'audio_message', $url],
            'file' => [['type' => 'FILE', 'payload' => ['url' => $url]], 'sendDocument', 'document', $url],
            'contact' => [['type' => 'CONTACT', 'payload' => ['name' => 'Иван', 'phone' => '+7900']], 'sendMessage', null, 'Телефон: +7900'],
            'location' => [['type' => 'LOCATION', 'payload' => ['latitude' => 55.7, 'longitude' => 37.6]], 'sendMessage', null, 'q=55.7,37.6'],
            'share' => [['type' => 'SHARE', 'payload' => ['url' => $url]], 'sendMessage', null, $url],
            'share without url' => [['type' => 'SHARE', 'payload' => ['id' => '157']], 'sendMessage', null, 'поделился материалом из OK (ID 157)'],
            'music' => [['type' => 'MUSIC', 'payload' => ['url' => $url]], 'sendMessage', null, $url],
            'music without url' => [['type' => 'MUSIC', 'payload' => ['id' => '9']], 'sendMessage', null, 'музыку из OK (ID 9)'],
            'call' => [['type' => 'CALL', 'payload' => ['type' => 'VIDEO', 'duration' => 5]], 'sendMessage', null, 'VIDEO'],
            'present' => [['type' => 'PRESENT', 'payload' => ['id' => '1']], 'sendMessage', null, 'подарок'],
            'unknown' => [['type' => 'NEW_TYPE', 'payload' => []], 'sendMessage', null, 'NEW_TYPE'],
            'file without url' => [['type' => 'VIDEO', 'payload' => ['id' => '1']], 'sendMessage', null, 'Вложение VIDEO без ссылки (ID 1)'],
        ];
    }

    /**
     * @param array<string, mixed> $attachment
     */
    #[DataProvider('attachments')]
    public function test_maps_every_attachment_type(array $attachment, string $method, ?string $fileType, string $textOrUrl): void
    {
        $dto = OkAttachmentDto::fromArray($attachment);

        $this->assertSame($method, $dto->method);
        $this->assertSame($fileType, $dto->fileType);
        $this->assertStringContainsString($textOrUrl, (string) $dto->text);
    }
}
