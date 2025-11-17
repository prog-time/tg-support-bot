<?php

namespace Tests\Unit\Services\Tg;

use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\Tg\TgEditMessageService;
use App\Services\Tg\TgMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TgEditMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    private string $groupChatId;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');
        $this->groupChatId = config('testing.tg_group.chat_id');
    }

    public function test_edit_text_message_private(): void
    {
        // Создаём новое сообщение
        $newMessageDtoParams = TelegramUpdateDtoMock::getDtoParams($this->botUser);
        $newMessageDto = TelegramUpdateDtoMock::getDto($newMessageDtoParams);

        (new TgMessageService($newMessageDto))->handleUpdate();

        // Сохраняем в БД
        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'incoming',
            'platform' => 'telegram',
            'from_id' => rand(),
            'to_id' => rand(),
        ];
        $createdMessage = Message::where($whereMessageParams)->firstOrCreate($whereMessageParams);

        // Редактируем сообщение
        $editPayload = [
            'update_id' => time(),
            'edited_message' => TelegramUpdateDtoMock::getDtoParams()['message'],
        ];

        $editTextMessage = 'Новый текст сообщения';
        $editPayload['edited_message']['text'] = $editTextMessage;
        $editPayload['edited_message']['message_id'] = $createdMessage->from_id;
        $editPayload['edited_message']['chat']['id'] = $this->botUser->chat_id;
        $editPayload['edited_message']['message_thread_id'] = $this->botUser->topic_id;

        $editDto = TelegramUpdateDtoMock::getDto($editPayload);
        (new TgEditMessageService($editDto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(2, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals('private', $firstJob->queryParams->typeSource);
        $this->assertEquals($this->groupChatId, $firstJob->queryParams->chat_id);
        $this->assertEquals($newMessageDto->text, $firstJob->queryParams->text);

        // Проверяем вторую джобу (редактирование)
        $secondJob = $pushed[1]['job'];
        $this->assertEquals('editMessageText', $secondJob->queryParams->methodQuery);
        $this->assertEquals('private', $secondJob->queryParams->typeSource);
        $this->assertEquals($this->groupChatId, $secondJob->queryParams->chat_id);
        $this->assertEquals($editDto->text, $secondJob->queryParams->text);
    }

    //
    //    public function test_edit_caption_message(): void
    //    {
    //        $photo = [
    //            [
    //                'file_id'        => config('testing.tg_file.photo'),
    //                'file_unique_id' => 'AQAD854DoEp9',
    //                'file_size'      => 59609,
    //                'width'          => 684,
    //                'height'         => 777,
    //            ],
    //        ];
    //
    //
    //        /*
    //         * 1. Создаём сообщение с caption
    //         */
    //        $payload = TelegramUpdateDtoMock::getDtoParams();
    //        unset($payload['message']['text']);
    //
    //        $payload['message']['photo'] = $photo;
    //        $payload['message']['caption'] = 'Версия 1';
    //
    //        $newMessageDto = TelegramUpdateDtoMock::getDto($payload);
    //        (new TgMessageService($newMessageDto))->handleUpdate();
    //
    //
    //        // Проверка БД
    //        $params = [
    //            'bot_user_id' => $this->botUser->id,
    //            'message_type' => 'incoming',
    //            'platform' => 'telegram',
    //        ];
    //        $this->assertDatabaseHas('messages', $params);
    //
    //        $createdMessage = Message::where($params)->latest()->first();
    //
    //
    //        /*
    //         * 2. Первая джоба — создание
    //         */
    //        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
    //        $this->assertCount(1, $pushed);
    //
    //        $firstJob = array_shift($pushed)['job'];
    //
    //        $jobCaption = $firstJob->updateDto->message['caption'] ?? null;
    //        $this->assertEquals('Версия 1', $jobCaption);
    //
    //
    //        /*
    //         * 3. Редактируем caption
    //         */
    //        $editPayload = [
    //            'update_id'      => time(),
    //            'edited_message' => TelegramUpdateDtoMock::getDtoParams()['message'],
    //        ];
    //
    //        unset($editPayload['edited_message']['text']);
    //
    //        $editPayload['edited_message']['photo'] = $photo;
    //        $editPayload['edited_message']['caption'] = 'Новый текст сообщения';
    //
    //        $editPayload['edited_message']['message_id'] = $createdMessage->from_id;
    //        $editPayload['edited_message']['chat']['id'] = $this->botUser->chat_id;
    //        $editPayload['edited_message']['message_thread_id'] = $this->botUser->topic_id;
    //
    //        $editDto = TelegramUpdateDtoMock::getDto($editPayload);
    //        (new TgEditMessageService($editDto))->handleUpdate();
    //
    //
    //        /*
    //         * 4. Вторая джоба — редактирование caption
    //         */
    //        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
    //        $this->assertCount(1, $pushed);
    //
    //        $secondJob = $pushed[0]['job'];
    //
    //        $editedCaption = $secondJob->updateDto->edited_message['caption'] ?? null;
    //
    //        $this->assertEquals('Новый текст сообщения', $editedCaption);
    //    }
}
