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

    public function test_notify_incoming_message_is_noop(): void
    {
        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        $dto = new TelegramUpdateDto(
            updateId: 1,
            typeQuery: 'message',
            aiTechMessage: false,
            typeSource: 'private',
            chatId: 100,
            text: 'Hello',
        );

        // Verifies no exception is thrown (method is a no-op, returns void)
        $this->expectNotToPerformAssertions();
        $this->service->notifyIncomingMessage($botUser, $dto);
    }

    public function test_create_conversation_is_noop(): void
    {
        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        // Verifies no exception is thrown (method is a no-op, returns void)
        $this->expectNotToPerformAssertions();
        $this->service->createConversation($botUser->id);
    }
}
