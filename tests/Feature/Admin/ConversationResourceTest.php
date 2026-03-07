<?php

namespace Tests\Feature\Admin;

use App\Models\BotUser;
use App\Models\Message;
use App\Models\User;
use App\Modules\Admin\Filament\Resources\ConversationResource\Pages\ListConversations;
use App\Modules\Admin\Filament\Resources\ConversationResource\Pages\ViewConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_can_render_list_page(): void
    {
        Livewire::test(ListConversations::class)
            ->assertSuccessful();
    }

    public function test_list_page_shows_bot_users(): void
    {
        $user = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Livewire::test(ListConversations::class)
            ->assertCanSeeTableRecords([$user]);
    }

    public function test_can_filter_list_by_platform(): void
    {
        $tgUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);
        $vkUser = BotUser::create(['chat_id' => 2, 'platform' => 'vk']);

        Livewire::test(ListConversations::class)
            ->filterTable('platform', 'telegram')
            ->assertCanSeeTableRecords([$tgUser])
            ->assertCanNotSeeTableRecords([$vkUser]);
    }

    public function test_can_render_view_conversation_page(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Livewire::test(ViewConversation::class, ['record' => $botUser->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_view_page_loads_messages(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Message::create([
            'bot_user_id'  => $botUser->id,
            'platform'     => 'telegram',
            'message_type' => 'incoming',
            'from_id'      => 1,
            'to_id'        => 0,
            'text'         => 'Test message',
        ]);

        Message::create([
            'bot_user_id'  => $botUser->id,
            'platform'     => 'telegram',
            'message_type' => 'outgoing',
            'from_id'      => 0,
            'to_id'        => 1,
            'text'         => 'Reply message',
        ]);

        Livewire::test(ViewConversation::class, ['record' => $botUser->getRouteKey()])
            ->assertSuccessful()
            ->assertViewHas('messages', fn ($msgs) => $msgs->count() === 2);
    }

    public function test_view_page_messages_are_ordered_by_created_at(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        $first = Message::create([
            'bot_user_id'  => $botUser->id,
            'platform'     => 'telegram',
            'message_type' => 'incoming',
            'from_id'      => 1,
            'to_id'        => 0,
            'text'         => 'First',
            'created_at'   => now()->subMinutes(5),
        ]);

        $second = Message::create([
            'bot_user_id'  => $botUser->id,
            'platform'     => 'telegram',
            'message_type' => 'outgoing',
            'from_id'      => 0,
            'to_id'        => 1,
            'text'         => 'Second',
            'created_at'   => now(),
        ]);

        Livewire::test(ViewConversation::class, ['record' => $botUser->getRouteKey()])
            ->assertViewHas('messages', function ($msgs) use ($first, $second): bool {
                return $msgs->first()->id === $first->id
                    && $msgs->last()->id === $second->id;
            });
    }

    public function test_send_reply_action_dispatches_job_in_admin_panel_mode(): void
    {
        Queue::fake();
        config(['app.manager_interface' => 'admin_panel']);

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        Livewire::test(ViewConversation::class, ['record' => $botUser->getRouteKey()])
            ->callAction('sendReply', ['text' => 'Hello from admin'])
            ->assertHasNoErrors()
            ->assertNotified('Сообщение отправлено');

        $this->assertDatabaseHas('messages', [
            'bot_user_id'  => $botUser->id,
            'message_type' => 'outgoing',
            'text'         => 'Hello from admin',
        ]);
    }

    public function test_send_reply_action_not_registered_outside_admin_panel_mode(): void
    {
        config(['app.manager_interface' => 'telegram_group']);

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        Livewire::test(ViewConversation::class, ['record' => $botUser->getRouteKey()])
            ->assertActionDoesNotExist('sendReply');
    }
}
