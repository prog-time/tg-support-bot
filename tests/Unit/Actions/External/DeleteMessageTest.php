<?php

namespace Tests\Unit\Actions\External;

use App\Actions\External\DeleteMessage;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class DeleteMessageTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_delete_message(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/deleteMessage' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        (new ExternalTrafficService())->store(ExternalMessageDtoMock::getDto());

        $messageData = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => 'live_chat',
            'message_type' => 'incoming',
            'from_id' => time(),
            'to_id' => time(),
        ]);
        $payload = ExternalMessageDtoMock::getDtoParams();
        $payload['message_id'] = $messageData->from_id;

        $dto = ExternalMessageDtoMock::getDto($payload);
        DeleteMessage::execute($dto);

        $messageData = Message::first();

        $this->assertNull($messageData);
    }
}
