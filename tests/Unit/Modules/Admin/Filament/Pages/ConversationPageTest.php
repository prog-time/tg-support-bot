<?php

namespace Tests\Unit\Modules\Admin\Filament\Pages;

use App\Models\BotUser;
use App\Models\Message;
use App\Models\User;
use App\Modules\Admin\Filament\Pages\ConversationPage;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_can_render_page_for_existing_user(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id])
            ->assertSuccessful();
    }

    public function test_mount_loads_bot_user(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id])
            ->assertSet('botUserId', $botUser->id)
            ->assertSet('botUser.id', $botUser->id);
    }

    public function test_mount_loads_messages(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
            'text' => 'Hello',
        ]);

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 1,
            'text' => 'Hi back',
        ]);

        $component = Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id]);

        $this->assertCount(2, $component->get('chatMessages'));
    }

    public function test_mount_with_nonexistent_user_sets_null_bot_user(): void
    {
        Livewire::test(ConversationPage::class, ['botUserId' => 99999])
            ->assertSet('botUser', null);
    }

    public function test_load_messages_only_loads_for_given_user(): void
    {
        $botUser1 = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);
        $botUser2 = BotUser::create(['chat_id' => 2, 'platform' => 'telegram']);

        Message::create([
            'bot_user_id' => $botUser1->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
            'text' => 'User 1 message',
        ]);

        Message::create([
            'bot_user_id' => $botUser2->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 2,
            'to_id' => 0,
            'text' => 'User 2 message',
        ]);

        $component = Livewire::test(ConversationPage::class, ['botUserId' => $botUser1->id]);

        $messages = $component->get('chatMessages');
        $this->assertCount(1, $messages);
        $this->assertEquals('User 1 message', $messages->first()->text);
    }

    public function test_load_messages_ordered_by_created_at(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        $first = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
            'text' => 'First',
            'created_at' => now()->subMinutes(5),
        ]);

        $second = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 1,
            'text' => 'Second',
            'created_at' => now(),
        ]);

        $messages = Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id])
            ->get('chatMessages');

        $this->assertEquals($first->id, $messages->first()->id);
        $this->assertEquals($second->id, $messages->last()->id);
    }

    public function test_send_reply_saves_message_and_dispatches_job(): void
    {
        Queue::fake();
        config(['app.manager_interface' => 'admin_panel']);

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id])
            ->set('replyData.text', 'Hello!')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertNotified('Сообщение отправлено');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'text' => 'Hello!',
        ]);

        Queue::assertPushed(SendTelegramSimpleQueryJob::class);
    }

    public function test_send_reply_reloads_messages_after_sending(): void
    {
        Queue::fake();
        config(['app.manager_interface' => 'admin_panel']);

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id]);

        $this->assertCount(0, $component->get('chatMessages'));

        $component->set('replyData.text', 'Hello!')->call('sendReply');

        $this->assertCount(1, $component->get('chatMessages'));
    }

    public function test_send_reply_does_nothing_outside_admin_panel_mode(): void
    {
        Queue::fake();
        config(['app.manager_interface' => 'telegram_group']);

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id])
            ->set('replyData.text', 'Hello!')
            ->call('sendReply');

        $this->assertDatabaseMissing('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_send_reply_does_nothing_when_bot_user_is_null(): void
    {
        Queue::fake();
        config(['app.manager_interface' => 'admin_panel']);

        Livewire::test(ConversationPage::class, ['botUserId' => 99999])
            ->set('replyData.text', 'Hello!')
            ->call('sendReply');

        Queue::assertNothingPushed();
    }

    public function test_polling_interval_is_five_seconds(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        $instance = Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id])
            ->instance();

        $this->assertEquals('5s', $instance->getPollingInterval());
    }

    public function test_should_show_reply_form_returns_true_in_admin_panel_mode(): void
    {
        config(['app.manager_interface' => 'admin_panel']);

        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        $instance = Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id])
            ->instance();

        $this->assertTrue($instance->shouldShowReplyForm());
    }

    public function test_should_show_reply_form_returns_false_in_telegram_group_mode(): void
    {
        config(['app.manager_interface' => 'telegram_group']);

        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        $instance = Livewire::test(ConversationPage::class, ['botUserId' => $botUser->id])
            ->instance();

        $this->assertFalse($instance->shouldShowReplyForm());
    }
}
