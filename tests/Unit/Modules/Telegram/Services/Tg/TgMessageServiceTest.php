<?php

namespace Tests\Unit\Modules\Telegram\Services\Tg;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Services\Tg\TgMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TgMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $tgChatId = time();

        $this->botUser = BotUser::getUserByChatId($tgChatId, 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['message_thread_id'] = $this->botUser->topic_id;
        $this->basicPayload = $payload;

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => time(),
                    'from' => [
                        'id' => time(),
                        'is_bot' => true,
                        'first_name' => 'Prog-Time |Администратор сайта',
                        'username' => 'prog_time_bot',
                    ],
                    'chat' => [
                        'id' => $tgChatId,
                        'first_name' => 'Test',
                        'last_name' => 'Testov',
                        'username' => 'usertest',
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'text' => 'Тестовое сообщение',
                ],
            ]),
        ]);
    }

    public function test_send_text_message(): void
    {
        $dto = TelegramUpdateDtoMock::getDto($this->basicPayload);

        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_photo(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['photo'] = [
            [
                'file_id' => 'test_file_id',
                'file_unique_id' => 'AQAD854DoEp9',
                'file_size' => 59609,
                'width' => 684,
                'height' => 777,
            ],
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendPhoto', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_document(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['document'] = [
            'file_id' => 'test_file_id',
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendDocument', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_sticker(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['sticker'] = [
            'file_id' => 'test_file_id',
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendSticker', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_location(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['location'] = [
            'latitude' => 55.728387,
            'longitude' => 37.611953,
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendLocation', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_video_note(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['video_note'] = [
            'file_id' => 'test_file_id',
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendVideoNote', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_voice(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['voice'] = [
            'file_id' => 'test_file_id',
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendVoice', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_contact(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['contact'] = [
            'phone_number' => '79999999999',
            'first_name' => 'Тестовый',
            'last_name' => 'Тест',
            'user_id' => time(),
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    /**
     * issue #46 — a Telegram content type this bot doesn't handle (video,
     * GIF, audio, poll, dice, venue, game) must produce a placeholder
     * notice instead of an empty dispatched job.
     */
    public function test_send_video_produces_unsupported_type_notice(): void
    {
        $payload = $this->basicPayload;
        unset($payload['message']['text']);
        $payload['message']['video'] = [
            'file_id' => 'test_video_id',
            'duration' => 10,
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertNotEmpty($firstJob->queryParams->text);
        $this->assertStringContainsString('видео', $firstJob->queryParams->text);
    }

    /**
     * issue #46 — same as above for a poll, which (unlike video) has
     * neither a caption-bearing field nor a file_id at all.
     */
    public function test_send_poll_produces_unsupported_type_notice(): void
    {
        $payload = $this->basicPayload;
        unset($payload['message']['text']);
        $payload['message']['poll'] = [
            'id' => 'poll1',
            'question' => 'Нравится?',
            'options' => [],
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertStringContainsString('опрос', $firstJob->queryParams->text);
    }

    public function test_send_message_with_inline_keyboard_from_supergroup(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['chat']['type'] = 'supergroup';
        $payload['message']['chat']['id'] = '-100000000000';
        $payload['message']['text'] = "Выберите действие\n[[Открыть сайт|url:https://example.com]]\n[[Назад|callback:back]]";

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals('Выберите действие', $firstJob->queryParams->text);
        $this->assertNotNull($firstJob->queryParams->reply_markup);
        $this->assertArrayHasKey('inline_keyboard', $firstJob->queryParams->reply_markup);
        $this->assertCount(2, $firstJob->queryParams->reply_markup['inline_keyboard']);
    }

    public function test_send_message_with_reply_keyboard_from_supergroup(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['chat']['type'] = 'supergroup';
        $payload['message']['chat']['id'] = '-100000000000';
        $payload['message']['text'] = "Поделитесь контактом\n[[Отправить номер|phone]]";

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals('Поделитесь контактом', $firstJob->queryParams->text);
        $this->assertNotNull($firstJob->queryParams->reply_markup);
        $this->assertArrayHasKey('keyboard', $firstJob->queryParams->reply_markup);
    }

    public function test_send_message_without_buttons_from_private(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['text'] = 'Текст с кнопками [[Кнопка|callback:test]]';

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        // Для private сообщений кнопки не должны парситься
        $this->assertEquals('Текст с кнопками [[Кнопка|callback:test]]', $firstJob->queryParams->text);
        $this->assertNull($firstJob->queryParams->reply_markup);
    }

    public function test_send_photo_with_keyboard_from_supergroup(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['chat']['type'] = 'supergroup';
        $payload['message']['chat']['id'] = '-100000000000';
        $payload['message']['photo'] = [
            [
                'file_id' => 'test_file_id',
                'file_unique_id' => 'AQAD854DoEp9',
                'file_size' => 59609,
                'width' => 684,
                'height' => 777,
            ],
        ];
        $payload['message']['caption'] = "Фото с кнопкой\n[[Подробнее|url:https://example.com]]";

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendPhoto', $firstJob->queryParams->methodQuery);
        $this->assertEquals('Фото с кнопкой', $firstJob->queryParams->caption);
        $this->assertNotNull($firstJob->queryParams->reply_markup);
        $this->assertArrayHasKey('inline_keyboard', $firstJob->queryParams->reply_markup);
    }

    public function test_reply_to_message_sets_reply_parameters(): void
    {
        // Создаем входящее сообщение в базе (от пользователя в группу)
        $userMessageId = 12345;
        $groupMessageId = 67890;

        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'to_id' => $userMessageId, // ID сообщения у пользователя
            'from_id' => $groupMessageId,  // ID сообщения в группе
        ]);

        // Создаем payload для ответа из группы с reply_to_message
        $payload = $this->basicPayload;
        $payload['message']['chat']['type'] = 'supergroup';
        $payload['message']['chat']['id'] = '-100000000000';
        $payload['message']['text'] = 'Ответ на ваше сообщение';
        $payload['message']['reply_to_message'] = [
            'message_id' => $groupMessageId,
            'from' => [
                'id' => 123,
                'is_bot' => false,
                'first_name' => 'User',
            ],
            'chat' => [
                'id' => '-100000000000',
                'type' => 'supergroup',
            ],
            'text' => 'Оригинальное сообщение',
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);

        $this->assertEquals($userMessageId, $firstJob->queryParams->reply_parameters['message_id']);
    }

    public function test_reply_to_message_without_original_message_in_db(): void
    {
        // Создаем payload для ответа из группы, но без записи в базе
        $payload = $this->basicPayload;
        $payload['message']['chat']['type'] = 'supergroup';
        $payload['message']['chat']['id'] = '-100000000000';
        $payload['message']['text'] = 'Ответ на несуществующее сообщение';
        $payload['message']['reply_to_message'] = [
            'message_id' => 99999,
            'from' => [
                'id' => 123,
                'is_bot' => false,
                'first_name' => 'User',
            ],
            'chat' => [
                'id' => '-100000000000',
                'type' => 'supergroup',
            ],
            'text' => 'Несуществующее сообщение',
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        // reply_parameters должен быть null, если сообщение не найдено
        $this->assertNull($firstJob->queryParams->reply_parameters);
    }

    public function test_no_reply_parameters_for_private_messages(): void
    {
        // Создаем сообщение в базе
        $userMessageId = 11111;
        $groupMessageId = 22222;

        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => $userMessageId,
            'to_id' => $groupMessageId,
        ]);

        // Payload для private (входящее сообщение от пользователя)
        $payload = $this->basicPayload;
        $payload['message']['text'] = 'Сообщение от пользователя';
        $payload['message']['reply_to_message'] = [
            'message_id' => $groupMessageId,
            'text' => 'Какое-то сообщение',
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = $pushed[0]['job'];
        // Для private сообщений reply_parameters не должен устанавливаться
        $this->assertNull($firstJob->queryParams->reply_parameters);
    }
}
