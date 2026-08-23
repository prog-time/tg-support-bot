<?php

namespace Tests\Unit\Livewire\Chat;

use App\Livewire\Chat\ConversationPage;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\User;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Unit tests for the standalone Livewire Chat ConversationPage workspace.
 *
 * Tests cover lifecycle methods — mount, dialog list, selectChat, sendReply,
 * polling interval, and shouldShowReplyForm.
 */
class ConversationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    public function test_can_render_page(): void
    {
        Livewire::test(ConversationPage::class)
            ->assertSuccessful();
    }

    // ── mount ──────────────────────────────────────────────────────────────────

    public function test_mount_loads_empty_collections(): void
    {
        $component = Livewire::test(ConversationPage::class);

        $component
            ->assertSet('activeBotUserId', null)
            ->assertSet('activeBotUser', null);

        $this->assertTrue($component->get('chatMessages')->isEmpty());
    }

    public function test_mount_populates_dialog_list(): void
    {
        BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class);

        $this->assertTrue($component->get('dialogList')->isNotEmpty());
    }

    // ── Dialog list pagination (infinite scroll) ────────────────────────────────

    private function seedChats(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            BotUser::create(['chat_id' => $i, 'platform' => 'telegram']);
        }
    }

    public function test_dialog_list_loads_only_the_first_page(): void
    {
        $this->seedChats(40);

        $component = Livewire::test(ConversationPage::class)
            ->assertSet('hasMoreDialogs', true);

        $this->assertCount(30, $component->get('dialogList'));
    }

    public function test_load_more_dialogs_grows_the_window(): void
    {
        $this->seedChats(40);

        $component = Livewire::test(ConversationPage::class)
            ->call('loadMoreDialogs')
            ->assertSet('hasMoreDialogs', false);

        $this->assertCount(40, $component->get('dialogList'));
    }

    public function test_small_dialog_list_reports_no_more(): void
    {
        $this->seedChats(5);

        $component = Livewire::test(ConversationPage::class)
            ->assertSet('hasMoreDialogs', false);

        $this->assertCount(5, $component->get('dialogList'));
    }

    public function test_changing_search_resets_the_dialog_window(): void
    {
        $this->seedChats(40);

        Livewire::test(ConversationPage::class)
            ->call('loadMoreDialogs')          // window grown to 60
            ->assertSet('dialogLimit', 60)
            ->set('search', '1')               // updatedSearch resets the window
            ->assertSet('dialogLimit', 30);
    }

    // ── selectChat ─────────────────────────────────────────────────────────────

    public function test_select_chat_sets_active_bot_user(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSet('activeBotUserId', $botUser->id)
            ->assertSet('activeBotUser.id', $botUser->id);
    }

    public function test_select_chat_loads_messages(): void
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

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id);

        $this->assertCount(1, $component->get('chatMessages'));
    }

    // ── Dialog list ordering ────────────────────────────────────────────────────

    public function test_dialog_list_is_ordered_by_latest_message_descending(): void
    {
        $makeChatWithMessageAt = function (int $chatId, \Illuminate\Support\Carbon $at): BotUser {
            $user = BotUser::create(['chat_id' => $chatId, 'platform' => 'telegram']);
            $msg = Message::create([
                'bot_user_id' => $user->id,
                'platform' => 'telegram',
                'message_type' => 'incoming',
                'from_id' => 0,
                'to_id' => 0,
                'text' => 'm',
            ]);
            $msg->forceFill(['created_at' => $at])->save();

            return $user;
        };

        // Insert out of chronological order to prove the sort, not insertion order.
        $oldest = $makeChatWithMessageAt(101, now()->subMinutes(30));
        $newest = $makeChatWithMessageAt(102, now()->subMinute());
        $middle = $makeChatWithMessageAt(103, now()->subMinutes(10));

        $list = Livewire::test(ConversationPage::class)->get('dialogList');

        $this->assertSame(
            [$newest->id, $middle->id, $oldest->id],
            $list->pluck('id')->all()
        );
    }

    public function test_dialog_list_order_follows_message_date_not_insertion_order(): void
    {
        // Queue lag: the chat whose message row was inserted LAST (highest id) can
        // carry an OLDER message date. The list must follow the date, not the id.
        $userA = BotUser::create(['chat_id' => 201, 'platform' => 'telegram']);
        $userB = BotUser::create(['chat_id' => 202, 'platform' => 'telegram']);

        // A's message: inserted first (lower id) but newer date.
        $a = Message::create([
            'bot_user_id' => $userA->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'a',
        ]);
        $a->forceFill(['created_at' => now()->subMinute()])->save();

        // B's message: inserted later (higher id) but older date.
        $b = Message::create([
            'bot_user_id' => $userB->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'b',
        ]);
        $b->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $list = Livewire::test(ConversationPage::class)->get('dialogList');

        // A (newer message date) must be above B despite B having the higher id.
        $this->assertSame([$userA->id, $userB->id], $list->pluck('id')->all());
    }

    public function test_dialog_list_order_survives_a_roundtrip_without_reload(): void
    {
        // id order is the REVERSE of message-date order, so any fallback to
        // id ordering (e.g. lost on Livewire hydration) is detectable.
        $older = BotUser::create(['chat_id' => 301, 'platform' => 'telegram']); // lower id, older msg
        $newer = BotUser::create(['chat_id' => 302, 'platform' => 'telegram']); // higher id, newer msg

        $o = Message::create([
            'bot_user_id' => $older->id, 'platform' => 'telegram', 'message_type' => 'incoming',
            'from_id' => 0, 'to_id' => 0, 'text' => 'o',
        ]);
        $o->forceFill(['created_at' => now()->subMinutes(20)])->save();

        $n = Message::create([
            'bot_user_id' => $newer->id, 'platform' => 'telegram', 'message_type' => 'incoming',
            'from_id' => 0, 'to_id' => 0, 'text' => 'n',
        ]);
        $n->forceFill(['created_at' => now()->subMinute()])->save();

        $component = Livewire::test(ConversationPage::class);

        // Correct on the initial render.
        $this->assertSame(
            [$newer->id, $older->id],
            $component->get('dialogList')->pluck('id')->all()
        );

        // A round-trip that does NOT call loadDialogList() must keep the order.
        $component->call('insertQuickReply', 'hello');

        $this->assertSame(
            [$newer->id, $older->id],
            $component->get('dialogList')->pluck('id')->all(),
            'Dialog order must survive a Livewire round-trip that does not reload the list.'
        );
    }

    // ── Lazy message loading (windowed thread) ──────────────────────────────────

    /**
     * Create $n text messages ("m1".."m$n") in order for the given user.
     */
    private function seedMessages(BotUser $botUser, int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Message::create([
                'bot_user_id' => $botUser->id,
                'platform' => 'telegram',
                'message_type' => $i % 2 === 1 ? 'incoming' : 'outgoing',
                'from_id' => 1,
                'to_id' => 0,
                'text' => "m{$i}",
            ]);
        }
    }

    public function test_select_chat_loads_only_the_most_recent_page(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);
        $this->seedMessages($botUser, 60);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSet('hasMoreMessages', true);

        $msgs = $component->get('chatMessages');
        $this->assertCount(50, $msgs);
        $this->assertSame('m11', $msgs->first()->text);
        $this->assertSame('m60', $msgs->last()->text);
    }

    public function test_load_older_messages_prepends_previous_page(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);
        $this->seedMessages($botUser, 60);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('loadOlderMessages')
            ->assertSet('hasMoreMessages', false);

        $msgs = $component->get('chatMessages');
        $this->assertCount(60, $msgs);
        $this->assertSame('m1', $msgs->first()->text);
        $this->assertSame('m60', $msgs->last()->text);
    }

    public function test_small_thread_reports_no_more_messages(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);
        $this->seedMessages($botUser, 3);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSet('hasMoreMessages', false);

        $this->assertCount(3, $component->get('chatMessages'));
    }

    public function test_poll_appends_new_messages_without_resetting_loaded_history(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);
        $this->seedMessages($botUser, 60);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)   // 50 loaded
            ->call('loadOlderMessages');          // full 60 loaded

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
            'text' => 'fresh',
        ]);

        $component->call('pollUpdates');

        $msgs = $component->get('chatMessages');
        $this->assertCount(61, $msgs);
        $this->assertSame('fresh', $msgs->last()->text);
    }

    // ── Read marker & background focus gate ──────────────────────────────────────

    public function test_poll_does_not_mark_conversation_read_while_tab_is_unfocused(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');

        $botUser = BotUser::create(['chat_id' => 2001, 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id);

        $readAfterSelect = $botUser->fresh()->manager_last_read_at;

        Carbon::setTestNow('2026-01-01 10:05:00');

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'arrived while backgrounded',
        ]);

        // wire:poll.5s.keep-alive keeps firing in the background so desktop/push
        // notifications still work, but must not silently mark this read.
        $component->call('pollUpdates', false)
            ->assertDispatched('messages-updated');

        $this->assertTrue($botUser->fresh()->manager_last_read_at->equalTo($readAfterSelect));

        Carbon::setTestNow();
    }

    public function test_poll_catches_up_the_read_marker_once_focus_returns(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');

        $botUser = BotUser::create(['chat_id' => 2002, 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id);

        Carbon::setTestNow('2026-01-01 10:05:00');

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'arrived while backgrounded',
        ]);

        $component->call('pollUpdates', false); // loaded, but not marked read

        Carbon::setTestNow('2026-01-01 10:05:05');

        // Next tick after focus returns has no *new* messages of its own, but
        // must still catch up the pending read marker.
        $component->call('pollUpdates', true);

        $this->assertEquals(
            '2026-01-01 10:05:05',
            $botUser->fresh()->manager_last_read_at->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    public function test_select_chat_zero_clears_active_dialog(): void
    {
        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('selectChat', 0)
            ->assertSet('activeBotUserId', null)
            ->assertSet('activeBotUser', null)
            ->assertSet('chatMessages', fn ($msgs) => $msgs->isEmpty());
    }

    // ── sendReply ──────────────────────────────────────────────────────────────

    public function test_send_reply_saves_message_and_dispatches_job(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => 100, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('replyText', 'Hello!')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertDispatched('admin-toast', message: 'Сообщение отправлено');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'text' => 'Hello!',
        ]);

        Queue::assertPushed(SendTelegramSimpleQueryJob::class);
    }

    // ── Polling interval ───────────────────────────────────────────────────────

    public function test_polling_interval_is_five_seconds(): void
    {
        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertEquals('5s', $instance->getPollingInterval());
    }

    // ── shouldShowReplyForm ────────────────────────────────────────────────────

    public function test_should_show_reply_form_returns_true(): void
    {
        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertTrue($instance->shouldShowReplyForm());
    }

    // ── Desktop notifications ───────────────────────────────────────────────────

    private function makeIncoming(BotUser $user, string $text = 'hi'): Message
    {
        return Message::create([
            'bot_user_id' => $user->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0, 'to_id' => 0, 'text' => $text,
        ]);
    }

    public function test_poll_dispatches_notification_for_new_incoming_message(): void
    {
        $user = BotUser::create(['chat_id' => 7001, 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class);
        $this->makeIncoming($user, 'привет');

        $component->call('pollUpdates')
            ->assertDispatched('new-incoming-messages');
    }

    public function test_poll_does_not_notify_for_preexisting_messages(): void
    {
        $user = BotUser::create(['chat_id' => 7002, 'platform' => 'telegram']);
        $this->makeIncoming($user); // exists before mount → part of the baseline

        Livewire::test(ConversationPage::class)
            ->call('pollUpdates')
            ->assertNotDispatched('new-incoming-messages');
    }

    public function test_poll_does_not_notify_for_the_active_dialog(): void
    {
        $user = BotUser::create(['chat_id' => 7003, 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class)->call('selectChat', $user->id);
        $this->makeIncoming($user, 'в открытом чате');

        $component->call('pollUpdates')
            ->assertNotDispatched('new-incoming-messages');
    }

    public function test_poll_does_not_notify_for_outgoing_messages(): void
    {
        $user = BotUser::create(['chat_id' => 7004, 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class);
        Message::create([
            'bot_user_id' => $user->id, 'platform' => 'telegram', 'message_type' => 'outgoing',
            'from_id' => 0, 'to_id' => 0, 'text' => 'ответ',
        ]);

        $component->call('pollUpdates')
            ->assertNotDispatched('new-incoming-messages');
    }

    public function test_poll_notifies_only_once_per_message(): void
    {
        $user = BotUser::create(['chat_id' => 7005, 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class);
        $this->makeIncoming($user);

        $component->call('pollUpdates')->assertDispatched('new-incoming-messages');
        // Watermark advanced — the same message must not notify again.
        $component->call('pollUpdates')->assertNotDispatched('new-incoming-messages');
    }

    // ── deleteChat ─────────────────────────────────────────────────────────────

    public function test_delete_chat_removes_dialog_with_messages_and_clears_active(): void
    {
        $botUser = BotUser::create(['chat_id' => 6001, 'platform' => 'telegram']);
        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0, 'to_id' => 0, 'text' => 'hello',
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSet('activeBotUserId', $botUser->id)
            ->call('deleteChat')
            ->assertSet('activeBotUserId', null)
            ->assertSet('activeBotUser', null);

        $this->assertDatabaseMissing('bot_users', ['id' => $botUser->id]);
        $this->assertSame(0, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_delete_chat_is_noop_without_active_dialog(): void
    {
        $botUser = BotUser::create(['chat_id' => 6002, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('deleteChat')
            ->assertSet('activeBotUserId', null);

        // Nothing deleted when no dialog is open.
        $this->assertDatabaseHas('bot_users', ['id' => $botUser->id]);
    }

    // ── clearHistory ───────────────────────────────────────────────────────────

    public function test_clear_history_removes_messages_but_keeps_active_chat(): void
    {
        $botUser = BotUser::create(['chat_id' => 6101, 'platform' => 'telegram']);
        Message::create([
            'bot_user_id' => $botUser->id, 'platform' => 'telegram', 'message_type' => 'incoming',
            'from_id' => 0, 'to_id' => 0, 'text' => 'one',
        ]);
        Message::create([
            'bot_user_id' => $botUser->id, 'platform' => 'telegram', 'message_type' => 'outgoing',
            'from_id' => 0, 'to_id' => 0, 'text' => 'two',
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('clearHistory')
            ->assertSet('activeBotUserId', $botUser->id)
            ->assertCount('chatMessages', 0);

        $this->assertDatabaseHas('bot_users', ['id' => $botUser->id]);
        $this->assertSame(0, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_clear_history_is_noop_without_active_dialog(): void
    {
        $botUser = BotUser::create(['chat_id' => 6102, 'platform' => 'telegram']);
        Message::create([
            'bot_user_id' => $botUser->id, 'platform' => 'telegram', 'message_type' => 'incoming',
            'from_id' => 0, 'to_id' => 0, 'text' => 'keep',
        ]);

        Livewire::test(ConversationPage::class)->call('clearHistory');

        $this->assertSame(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    // ── profileUrl ─────────────────────────────────────────────────────────────

    public function test_profile_url_is_null_without_active_dialog(): void
    {
        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertNull($instance->profileUrl());
    }

    public function test_profile_url_for_vk_is_a_web_link(): void
    {
        $user = BotUser::create(['chat_id' => 12345, 'platform' => 'vk']);

        $instance = Livewire::test(ConversationPage::class)->call('selectChat', $user->id)->instance();

        $this->assertSame('https://vk.com/id12345', $instance->profileUrl());
    }

    public function test_profile_url_is_null_for_telegram(): void
    {
        // A numeric Telegram chat_id cannot be turned into a working profile link
        // (no stored @username), so no link is offered.
        $user = BotUser::create(['chat_id' => 67890, 'platform' => 'telegram']);

        $instance = Livewire::test(ConversationPage::class)->call('selectChat', $user->id)->instance();

        $this->assertNull($instance->profileUrl());
    }

    public function test_profile_url_is_null_for_unsupported_platform(): void
    {
        $user = BotUser::create(['chat_id' => 555, 'platform' => 'max']);

        $instance = Livewire::test(ConversationPage::class)->call('selectChat', $user->id)->instance();

        $this->assertNull($instance->profileUrl());
    }

    // ── insertQuickReply ──────────────────────────────────────────────────────

    public function test_insert_quick_reply_sets_reply_text(): void
    {
        Livewire::test(ConversationPage::class)
            ->call('insertQuickReply', 'Ожидайте, пожалуйста')
            ->assertSet('replyText', 'Ожидайте, пожалуйста');
    }

    // ── hasUnread ──────────────────────────────────────────────────────────────

    private function botUserWithLastMessage(string $type, array $attrs = []): BotUser
    {
        $botUser = BotUser::create(array_merge(['chat_id' => 5000, 'platform' => 'telegram'], $attrs));

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => $type,
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'msg',
        ]);

        return $botUser->fresh(['lastMessage']);
    }

    public function test_has_unread_true_for_open_dialog_with_incoming_last_message(): void
    {
        $user = $this->botUserWithLastMessage('incoming');

        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertTrue($instance->hasUnread($user));
    }

    public function test_has_unread_false_when_last_message_outgoing(): void
    {
        $user = $this->botUserWithLastMessage('outgoing');

        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertFalse($instance->hasUnread($user));
    }

    public function test_has_unread_false_for_closed_dialog(): void
    {
        $user = $this->botUserWithLastMessage('incoming', ['is_closed' => true, 'closed_at' => now()]);

        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertFalse($instance->hasUnread($user));
    }

    public function test_has_unread_false_for_banned_dialog(): void
    {
        $user = $this->botUserWithLastMessage('incoming', ['is_banned' => true, 'banned_at' => now()]);

        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertFalse($instance->hasUnread($user));
    }

    public function test_has_unread_false_for_active_dialog(): void
    {
        $user = $this->botUserWithLastMessage('incoming');

        $component = Livewire::test(ConversationPage::class)->call('selectChat', $user->id);

        $this->assertFalse($component->instance()->hasUnread($user));
    }

    public function test_has_unread_false_when_read_after_last_message(): void
    {
        $user = $this->botUserWithLastMessage('incoming', ['manager_last_read_at' => now()->addHour()]);

        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertFalse($instance->hasUnread($user));
    }

    public function test_has_unread_true_when_message_arrived_after_read(): void
    {
        $user = $this->botUserWithLastMessage('incoming', ['manager_last_read_at' => now()->subHour()]);

        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertTrue($instance->hasUnread($user));
    }

    public function test_selecting_dialog_persists_read_timestamp(): void
    {
        $user = $this->botUserWithLastMessage('incoming');

        Livewire::test(ConversationPage::class)->call('selectChat', $user->id);

        // Persisted: a fresh, non-active load is no longer flagged unread.
        $reloaded = $user->fresh(['lastMessage']);
        $this->assertNotNull($reloaded->manager_last_read_at);

        $freshComponent = Livewire::test(ConversationPage::class)->instance();
        $this->assertFalse($freshComponent->hasUnread($reloaded));
    }

    public function test_opening_dialog_marks_all_messages_read_despite_queue_lag(): void
    {
        // Messages are persisted by queued jobs, so a message that arrived before
        // the manager opened the dialog can land with a created_at slightly ahead
        // of "now" (the job ran after the click). Such a message must still be
        // treated as read once the dialog is opened.
        $user = BotUser::create(['chat_id' => 5002, 'platform' => 'telegram']);

        $lagged = Message::create([
            'bot_user_id' => $user->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'queued',
        ]);
        $lagged->forceFill(['created_at' => now()->addMinute()])->save();

        Livewire::test(ConversationPage::class)->call('selectChat', $user->id);

        $reloaded = $user->fresh(['lastMessage']);
        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertFalse($instance->hasUnread($reloaded));
        $this->assertSame(0, $instance->unreadCount($reloaded));
    }

    // ── unreadCount ────────────────────────────────────────────────────────────

    public function test_unread_count_returns_number_of_incoming_messages(): void
    {
        $user = $this->botUserWithLastMessage('incoming');

        // Two more incoming messages (3 total) for the same dialog.
        foreach (range(1, 2) as $i) {
            Message::create([
                'bot_user_id' => $user->id,
                'platform' => 'telegram',
                'message_type' => 'incoming',
                'from_id' => 0,
                'to_id' => 0,
                'text' => 'msg ' . $i,
            ]);
        }

        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertSame(3, $instance->unreadCount($user->fresh(['lastMessage'])));
    }

    public function test_unread_count_is_zero_when_dialog_not_unread(): void
    {
        $user = $this->botUserWithLastMessage('outgoing');

        $instance = Livewire::test(ConversationPage::class)->instance();

        $this->assertSame(0, $instance->unreadCount($user));
    }

    public function test_unread_count_only_counts_messages_after_read_timestamp(): void
    {
        $user = BotUser::create([
            'chat_id' => 5001,
            'platform' => 'telegram',
            'manager_last_read_at' => now()->subMinutes(30),
        ]);

        // Older incoming message, read earlier (before the read timestamp).
        // created_at is not fillable, so set it explicitly after creation.
        $old = Message::create([
            'bot_user_id' => $user->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'old',
        ]);
        $old->forceFill(['created_at' => now()->subHour()])->save();

        // Newer incoming message (the latest one) — arrived after the read mark.
        Message::create([
            'bot_user_id' => $user->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'new',
        ]);

        $instance = Livewire::test(ConversationPage::class)->instance();

        // Only the post-read message counts; the older one is excluded.
        $this->assertSame(1, $instance->unreadCount($user->fresh(['lastMessage'])));
    }

    // ── getMediaAttachments ──────────────────────────────────────────────────

    public function test_get_media_attachments_returns_empty_without_active_dialog(): void
    {
        $component = Livewire::test(ConversationPage::class);

        $this->assertTrue($component->instance()->getMediaAttachments()->isEmpty());
    }

    // ── deleteChat (admin-only) ──────────────────────────────────────────────

    public function test_is_admin_true_for_admin_user(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertTrue(Livewire::test(ConversationPage::class)->instance()->isAdmin());
    }

    public function test_is_admin_false_for_manager_user(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->assertFalse(Livewire::test(ConversationPage::class)->instance()->isAdmin());
    }

    public function test_admin_can_delete_chat(): void
    {
        $this->actingAs(User::factory()->create());

        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('deleteChat')
            ->assertSet('activeBotUser', null);

        $this->assertDatabaseMissing('bot_users', ['id' => $botUser->id]);
    }

    public function test_manager_cannot_delete_chat(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $botUser = BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('deleteChat')
            ->assertStatus(403);

        $this->assertDatabaseHas('bot_users', ['id' => $botUser->id]);
    }
}
