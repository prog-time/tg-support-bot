<?php

namespace Tests\Feature\Modules\External\Jobs;

use App\Models\BotUser;
use App\Modules\External\DTOs\ExternalMessageDto;
use App\Modules\Telegram\Actions\DeleteForumTopic;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendExternalTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class SendExternalTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private ExternalMessageDto $dto;

    private ?BotUser $botUser;

    private int $chatId;

    private int $groupId;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->groupId = time();
        $this->chatId = time();

        $dtoParams = ExternalMessageDtoMock::getDtoParams();
        $dtoParams['chat_id'] = $this->chatId;

        $this->dto = ExternalMessageDtoMock::getDto($dtoParams);

        $this->botUser = (new BotUser())->getOrCreateExternalBotUser($this->dto);
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_send_message_for_group(): void
    {
        try {
            $typeMessage = 'incoming';
            $textMessage = '👋 Тест из SendExternalTelegramMessageJob @ ' . now();

            Http::fake([
                'https://api.telegram.org/bot*/sendMessage' => Http::response([
                    'ok' => true,
                    'result' => [
                        'message_id' => time(),
                        'chat' => [
                            'id' => $this->groupId,
                            'type' => 'private',
                        ],
                        'text' => $textMessage,
                    ],
                ], 200),
            ]);

            $queryParams = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $this->chatId,
                'message_thread_id' => $this->botUser->topic_id,
                'text' => $textMessage,
            ]);

            $job = new SendExternalTelegramMessageJob(
                $this->botUser->id,
                $this->dto,
                $queryParams,
                $typeMessage
            );
            $job->handle();

            $this->assertDatabaseHas('messages', [
                'bot_user_id' => $this->botUser->id,
                'message_type' => $typeMessage,
                'platform' => $this->dto->source,
            ]);
        } finally {
            if ($this->botUser->topic_id) {
                app(DeleteForumTopic::class)->execute($this->botUser);
            }
        }
    }
}
