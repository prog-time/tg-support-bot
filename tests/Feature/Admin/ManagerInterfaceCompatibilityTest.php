<?php

namespace Tests\Feature\Admin;

use App\Contracts\ManagerInterfaceContract;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Admin\Services\AdminPanelInterface;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Modules\Telegram\Services\TelegramGroupInterface;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManagerInterfaceCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Re-register AppServiceProvider to pick up the given config value.
     */
    private function rebindInterface(string $mode): void
    {
        config(['app.manager_interface' => $mode]);
        (new AppServiceProvider($this->app))->register();
    }

    public function test_di_binds_telegram_group_interface_in_telegram_group_mode(): void
    {
        $this->rebindInterface('telegram_group');

        $resolved = $this->app->make(ManagerInterfaceContract::class);

        $this->assertInstanceOf(TelegramGroupInterface::class, $resolved);
    }

    public function test_di_binds_admin_panel_interface_in_admin_panel_mode(): void
    {
        $this->rebindInterface('admin_panel');

        $resolved = $this->app->make(ManagerInterfaceContract::class);

        $this->assertInstanceOf(AdminPanelInterface::class, $resolved);
    }

    public function test_create_conversation_dispatches_topic_job_in_telegram_group_mode(): void
    {
        Queue::fake();
        $this->rebindInterface('telegram_group');

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        $this->app->make(ManagerInterfaceContract::class)->createConversation($botUser->id);

        Queue::assertPushed(TopicCreateJob::class);
    }

    public function test_create_conversation_does_not_dispatch_topic_job_in_admin_panel_mode(): void
    {
        Queue::fake();
        $this->rebindInterface('admin_panel');

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        $this->app->make(ManagerInterfaceContract::class)->createConversation($botUser->id);

        Queue::assertNotPushed(TopicCreateJob::class);
    }

    public function test_topic_id_preserved_after_switch_to_admin_panel_mode(): void
    {
        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram', 'topic_id' => 456]);

        $this->rebindInterface('admin_panel');

        $this->assertDatabaseHas('bot_users', [
            'id' => $botUser->id,
            'topic_id' => 456,
        ]);
    }

    /**
     * User wrote in telegram_group mode → mode switched to admin_panel →
     * user writes again → message appears in DB, no topic created.
     */
    public function test_mode_switch_messages_visible_and_no_new_topic_dispatched(): void
    {
        Queue::fake();

        // Phase 1: telegram_group mode — user already has a topic
        $botUser = BotUser::create([
            'chat_id' => 100,
            'platform' => 'telegram',
            'topic_id' => 456,
        ]);

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 100,
            'to_id' => 0,
            'text' => 'First message (telegram_group mode)',
        ]);

        // Phase 2: switch to admin_panel
        $this->rebindInterface('admin_panel');

        // Phase 3: new incoming message saved to DB
        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 100,
            'to_id' => 0,
            'text' => 'Second message (admin_panel mode)',
        ]);

        // createConversation must not dispatch TopicCreateJob
        $this->app->make(ManagerInterfaceContract::class)->createConversation($botUser->id);
        Queue::assertNotPushed(TopicCreateJob::class);

        // Both messages are visible (admin polling can read them)
        $messages = Message::where('bot_user_id', $botUser->id)->get();
        $this->assertCount(2, $messages);

        // topic_id not cleared by the mode switch
        $this->assertDatabaseHas('bot_users', ['id' => $botUser->id, 'topic_id' => 456]);
    }

    /**
     * In admin_panel mode notifyIncomingMessage is a no-op and must not throw.
     */
    public function test_notify_incoming_message_is_noop_in_admin_panel_mode(): void
    {
        Queue::fake();
        $this->rebindInterface('admin_panel');

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        $dto = new TelegramUpdateDto(
            updateId: 1,
            typeQuery: 'message',
            aiTechMessage: false,
            typeSource: 'private',
            chatId: 100,
            text: 'Hello',
        );

        $this->app->make(ManagerInterfaceContract::class)->notifyIncomingMessage($botUser, $dto);

        Queue::assertNothingPushed();
    }
}
