<?php

namespace Tests\Unit\Services\TgVk;

use App\Jobs\SendMessage\SendVkMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgVk\TgVkEditService;
use App\Services\TgVk\TgVkMessageService;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

class TgVkEditServiceTest extends TestCase
{
    private BotUser $botUser;

    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(config('testing.vk_private.chat_id'), 'vk');
        $this->basicPayload = TelegramUpdateDto_VKMock::getDtoParams($this->botUser)['message'];
    }

    public function test_edit_text_message(): void
    {
        Message::truncate();

        // Создаём новое сообщение
        $newMessagePayload = [
            'update_id' => time(),
            'message' => $this->basicPayload,
        ];

        $newMessageDto = TelegramUpdateDto_VKMock::getDto($newMessagePayload);

        (new TgVkMessageService($newMessageDto))->handleUpdate();

        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'platform' => 'vk',
            'from_id' => rand(),
            'to_id' => rand(),
        ];
        $newMessageData = Message::where($whereMessageParams)->firstOrCreate($whereMessageParams);

        // Редактируем сообщение
        $editPayload = [
            'update_id' => time(),
            'edited_message' => $this->basicPayload,
        ];

        $editPayload['edited_message']['text'] = 'Новый текст сообщения';
        $editPayload['edited_message']['message_id'] = $newMessageData->from_id;
        $editPayload['edited_message']['message_thread_id'] = $this->botUser->topic_id;

        $editDto = TelegramUpdateDto_VKMock::getDto($editPayload);

        (new TgVkEditService($editDto))->handleUpdate();

        // Получаем все джобы нужного класса
        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class];
        $this->assertEquals(count($pushed), 2);

        // проверка отправки сообщения
        $firstJob = array_shift($pushed);
        $jobData = $firstJob['job'];
        $this->assertEquals($newMessageDto->text, $jobData->updateDto->text);
        $this->assertEquals($this->botUser->id, $jobData->botUser->id);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->peer_id);
        $this->assertEquals($newMessageDto, $jobData->updateDto);

        // проверка редактирования сообщения
        $jobData = $pushed[0]['job'];
        $this->assertEquals($editDto->text, $jobData->updateDto->text);
        $this->assertEquals($this->botUser->id, $jobData->botUser->id);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->peer_id);
        $this->assertEquals($editDto, $jobData->updateDto);
    }
}
