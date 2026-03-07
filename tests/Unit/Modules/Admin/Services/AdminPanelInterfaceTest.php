<?php

namespace Tests\Unit\Modules\Admin\Services;

use App\Contracts\ManagerInterfaceContract;
use App\Models\BotUser;
use App\Modules\Admin\Services\AdminPanelInterface;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private AdminPanelInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AdminPanelInterface();
    }

    public function test_implements_manager_interface_contract(): void
    {
        $this->assertInstanceOf(ManagerInterfaceContract::class, $this->service);
    }

    public function test_notify_incoming_message_saves_message_to_db(): void
    {
        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        $dto = new TelegramUpdateDto(
            updateId: 1,
            typeQuery: 'message',
            aiTechMessage: false,
            typeSource: 'private',
            chatId: 100,
            messageId: 42,
            text: 'Hello',
        );

        $this->service->notifyIncomingMessage($botUser, $dto);

        $this->assertDatabaseHas('messages', [
            'bot_user_id'  => $botUser->id,
            'platform'     => 'telegram',
            'message_type' => 'incoming',
            'from_id'      => 42,
            'to_id'        => 0,
            'text'         => 'Hello',
        ]);
    }

    public function test_notify_incoming_message_saves_attachment(): void
    {
        $botUser = BotUser::create(['chat_id' => 200, 'platform' => 'telegram']);

        $dto = new TelegramUpdateDto(
            updateId: 2,
            typeQuery: 'message',
            aiTechMessage: false,
            typeSource: 'private',
            chatId: 200,
            messageId: 99,
            fileId: 'file_abc123',
            fileType: 'photo',
        );

        $this->service->notifyIncomingMessage($botUser, $dto);

        $this->assertDatabaseHas('messages', [
            'bot_user_id'  => $botUser->id,
            'message_type' => 'incoming',
            'from_id'      => 99,
            'text'         => null,
        ]);

        $this->assertDatabaseHas('message_attachments', [
            'file_id'   => 'file_abc123',
            'file_type' => 'photo',
        ]);
    }

    public function test_notify_incoming_message_uses_caption_when_text_is_null(): void
    {
        $botUser = BotUser::create(['chat_id' => 300, 'platform' => 'telegram']);

        $dto = new TelegramUpdateDto(
            updateId: 3,
            typeQuery: 'message',
            aiTechMessage: false,
            typeSource: 'private',
            chatId: 300,
            messageId: 55,
            caption: 'Photo caption',
            fileId: 'file_xyz',
            fileType: 'photo',
        );

        $this->service->notifyIncomingMessage($botUser, $dto);

        $this->assertDatabaseHas('messages', [
            'bot_user_id'  => $botUser->id,
            'message_type' => 'incoming',
            'text'         => 'Photo caption',
        ]);
    }

    public function test_create_conversation_is_noop(): void
    {
        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        // Verifies no exception is thrown (method is a no-op, returns void)
        $this->expectNotToPerformAssertions();
        $this->service->createConversation($botUser->id);
    }
}
