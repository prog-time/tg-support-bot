<?php

namespace Tests\Unit\Services\VK;

use App\Jobs\SendMessage\SendVkTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\VK\VkEditService;
use App\Services\VK\VkMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Vk\VkUpdateDtoMock;
use Tests\TestCase;

class VkEditServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $chatId;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Message::truncate();

        $this->chatId = config('testing.vk_private.chat_id');
        $this->botUser = BotUser::getUserByChatId($this->chatId, 'vk');
    }

    public function test_edit_text_message(): void
    {
        // новое сообщение
        $dtoNewMessage = VkUpdateDtoMock::getDto();
        (new VkMessageService($dtoNewMessage))->handleUpdate();
        // ---------------

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkTelegramMessageJob::class] ?? [];
        $this->assertNotEmpty($pushed);

        // Проверка редактирования джобы
        $jobData = $pushed[0]['job'];

        $this->assertEquals($dtoNewMessage->text, $jobData->queryParams->text);
        $this->assertEquals($this->botUser->topic_id, $jobData->queryParams->message_thread_id);
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $jobData->queryParams->chat_id);
        $this->assertEquals($dtoNewMessage, $jobData->updateDto);

        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'incoming',
            'platform' => 'vk',
            'from_id' => $dtoNewMessage->from_id,
            'to_id' => rand(),
        ];
        $messageData = Message::where($whereMessageParams)->firstOrCreate($whereMessageParams);

        // изменение сообщения
        $dtoParams = VkUpdateDtoMock::getDtoParams();

        $dtoParams['type'] = 'message_edit';
        $dtoParams['object']['message']['id'] = $messageData->from_id;
        $dtoParams['object']['message']['text'] = 'Test text, new!';

        $dtoUpdateMessage = VkUpdateDtoMock::getDto($dtoParams);

        (new VkEditService($dtoUpdateMessage))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkTelegramMessageJob::class] ?? [];
        $this->assertNotEmpty($pushed);

        // Проверка редактирования джобы
        $jobData = $pushed[1]['job'];

        $this->assertEquals($dtoUpdateMessage->text, $jobData->queryParams->text);
        $this->assertEquals($this->botUser->topic_id, $jobData->queryParams->message_thread_id);
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $jobData->queryParams->chat_id);
        $this->assertEquals($dtoUpdateMessage, $jobData->updateDto);
    }
}
