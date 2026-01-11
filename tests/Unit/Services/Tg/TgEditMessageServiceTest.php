<?php

namespace Tests\Unit\Services\Tg;

use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\Tg\TgEditMessageService;
use App\Services\Tg\TgMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TgEditMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        BotUser::truncate();
        Message::truncate();
        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();

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
                        'id' => time(),
                        'first_name' => 'Test',
                        'last_name' => 'Testov',
                        'username' => 'testuser',
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'text' => 'Тестовое сообщение',
                ],
            ]),
        ]);
    }

    public function test_edit_caption_message(): void
    {
        $photo = [
            [
                'file_id' => 'test_file_id',
                'file_unique_id' => 'AQAD854DoEp9',
                'file_size' => 59609,
                'width' => 684,
                'height' => 777,
            ],
        ];

        // Создаём сообщение с caption
        $payload = TelegramUpdateDtoMock::getDtoParams();
        unset($payload['message']['text']);

        $payload['message']['photo'] = $photo;
        $payload['message']['caption'] = 'Версия 1';

        $newMessageDto = TelegramUpdateDtoMock::getDto($payload);

        (new TgMessageService($newMessageDto))->handleUpdate();

        // Проверка БД
        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'incoming',
            'platform' => 'telegram',
            'from_id' => rand(),
            'to_id' => rand(),
        ];
        $createdMessage = Message::where($whereMessageParams)->firstOrCreate($whereMessageParams);

        // Первая джоба — создание
        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $firstJob = array_shift($pushed)['job'];
        $jobCaption = $firstJob->updateDto->caption ?? null;

        $this->assertEquals('Версия 1', $jobCaption);

        // Редактируем caption
        $editPayload = [
            'update_id' => time(),
            'edited_message' => TelegramUpdateDtoMock::getDtoParams()['message'],
        ];

        unset($editPayload['edited_message']['text']);

        $editPayload['edited_message']['photo'] = $photo;
        $editPayload['edited_message']['caption'] = 'Новый текст сообщения';

        $editPayload['edited_message']['message_id'] = $createdMessage->from_id;
        $editPayload['edited_message']['chat']['id'] = $this->botUser->chat_id;
        $editPayload['edited_message']['message_thread_id'] = $this->botUser->topic_id;

        $editDto = TelegramUpdateDtoMock::getDto($editPayload);
        (new TgEditMessageService($editDto))->handleUpdate();

        // Вторая джоба — редактирование caption
        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(2, $pushed);

        $secondJob = $pushed[1]['job'];

        $editedCaption = $secondJob->updateDto->caption ?? null;

        $this->assertEquals('Новый текст сообщения', $editedCaption);
    }
}
