<?php

namespace Tests\Feature\Admin;

use App\Livewire\Chat\ConversationPage;
use App\Models\AutoReply;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Modules\Admin\Actions\DeleteBotUser;
use App\Modules\Admin\Actions\SendReplyAction;
use App\Modules\Admin\Jobs\SendAdminDocumentJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature tests for the 3-column manager chat workspace (ConversationPage).
 *
 * Covers:
 *  - Page renders under auth
 *  - Dialog list populates with BotUsers
 *  - Search filter narrows the dialog list
 *  - Status-filter tabs (all / open / closed)
 *  - Selecting a dialog loads its messages and user panel
 *  - Sending a reply calls SendReplyAction and refreshes
 *  - Sending a reply with a file attachment (telegram) and file-only messages
 *  - Attachments gated to telegram/vk via supportsAttachments()
 *  - Quick-reply insertion
 *  - Media gallery returns image attachments
 *  - Reply form always shown (no mode gating)
 */
class ConversationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    /**
     * Regression guard for #211: the reply textarea collapsed to one line while
     * typing because wire:model.live re-rendered it on every keystroke (and every
     * 5 s poll), letting Livewire's morph revert the height Alpine had set.
     *
     * The fix isolates the field from morphing with wire:ignore and binds it with
     * Livewire entangle instead. This can't be exercised in a browser here (no
     * Dusk), so the structural markers are pinned: reverting to wire:model.live on
     * the reply field brings the bug back and fails this test.
     */
    public function test_reply_textarea_is_isolated_from_livewire_morphing(): void
    {
        $botUser = BotUser::create(['chat_id' => '211', 'platform' => 'telegram']);

        $html = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSee('wire:ignore', false)
            ->assertSee('x-model="text"', false)
            ->html();

        $this->assertStringNotContainsString(
            'wire:model.live="replyText"',
            $html,
            'The reply textarea must not use wire:model.live — it re-renders on every keystroke and collapses the field (#211).'
        );
    }

    public function test_page_renders_under_auth(): void
    {
        Livewire::test(ConversationPage::class)
            ->assertSuccessful();
    }

    public function test_page_shows_empty_state_when_no_dialog_selected(): void
    {
        Livewire::test(ConversationPage::class)
            ->assertSee('Выберите диалог');
    }

    // ── Dialog list ────────────────────────────────────────────────────────────

    public function test_dialog_list_contains_bot_users(): void
    {
        BotUser::create(['chat_id' => '111', 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class);

        $component->assertSet('dialogList', fn ($list) => $list->isNotEmpty());
    }

    public function test_dialog_list_ordered_by_last_message_desc(): void
    {
        $older = BotUser::create(['chat_id' => '200', 'platform' => 'telegram']);
        $newer = BotUser::create(['chat_id' => '201', 'platform' => 'telegram']);

        // Use DB::table() to bypass Eloquent timestamps management
        // and set created_at to controlled values for deterministic ordering.
        \Illuminate\Support\Facades\DB::table('messages')->insert([
            'bot_user_id' => $older->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
            'text' => 'Old message',
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-01 10:00:00',
        ]);

        \Illuminate\Support\Facades\DB::table('messages')->insert([
            'bot_user_id' => $newer->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
            'text' => 'New message',
            'created_at' => '2024-06-01 10:00:00',
            'updated_at' => '2024-06-01 10:00:00',
        ]);

        $component = Livewire::test(ConversationPage::class);

        $component->assertSet('dialogList', function ($list) use ($newer) {
            return $list->first()?->id === $newer->id;
        });
    }

    public function test_dialog_list_marks_closed_conversations(): void
    {
        BotUser::create([
            'chat_id' => '300',
            'platform' => 'telegram',
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        Livewire::test(ConversationPage::class)
            ->assertSee('Обращение закрыто');
    }

    public function test_dialog_list_does_not_mark_open_conversations(): void
    {
        BotUser::create(['chat_id' => '301', 'platform' => 'telegram', 'is_closed' => false]);

        Livewire::test(ConversationPage::class)
            ->assertDontSee('Обращение закрыто');
    }

    // ── Search ─────────────────────────────────────────────────────────────────

    public function test_search_filters_dialog_list_by_chat_id(): void
    {
        BotUser::create(['chat_id' => '12345', 'platform' => 'telegram']);
        BotUser::create(['chat_id' => '99999', 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->set('search', '123')
            ->assertSet('dialogList', fn ($list) => $list->count() === 1
                && (string) $list->first()->chat_id === '12345');
    }

    public function test_empty_search_shows_all_users(): void
    {
        BotUser::create(['chat_id' => '11111', 'platform' => 'telegram']);
        BotUser::create(['chat_id' => '22222', 'platform' => 'vk']);

        Livewire::test(ConversationPage::class)
            ->set('search', '')
            ->assertSet('dialogList', fn ($list) => $list->count() === 2);
    }

    // ── Status filter ──────────────────────────────────────────────────────────

    public function test_status_filter_open_excludes_closed_dialogs(): void
    {
        BotUser::create(['chat_id' => '300', 'platform' => 'telegram', 'is_closed' => false]);
        BotUser::create(['chat_id' => '301', 'platform' => 'telegram', 'is_closed' => true]);

        Livewire::test(ConversationPage::class)
            ->set('statusFilter', 'open')
            ->assertSet('dialogList', fn ($list) => $list->count() === 1
                && (string) $list->first()->chat_id === '300');
    }

    public function test_status_filter_closed_excludes_open_dialogs(): void
    {
        BotUser::create(['chat_id' => '400', 'platform' => 'telegram', 'is_closed' => false]);
        BotUser::create(['chat_id' => '401', 'platform' => 'telegram', 'is_closed' => true]);

        Livewire::test(ConversationPage::class)
            ->set('statusFilter', 'closed')
            ->assertSet('dialogList', fn ($list) => $list->count() === 1
                && (string) $list->first()->chat_id === '401');
    }

    public function test_status_filter_all_shows_both(): void
    {
        BotUser::create(['chat_id' => '500', 'platform' => 'telegram', 'is_closed' => false]);
        BotUser::create(['chat_id' => '501', 'platform' => 'telegram', 'is_closed' => true]);

        Livewire::test(ConversationPage::class)
            ->set('statusFilter', 'all')
            ->assertSet('dialogList', fn ($list) => $list->count() === 2);
    }

    // ── Select dialog ──────────────────────────────────────────────────────────

    public function test_select_chat_loads_messages(): void
    {
        $botUser = BotUser::create(['chat_id' => '600', 'platform' => 'telegram']);

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
            'text' => 'Hello!',
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSet('activeBotUserId', $botUser->id)
            ->assertSet('chatMessages', fn ($msgs) => $msgs->count() === 1);
    }

    public function test_select_chat_sets_active_bot_user(): void
    {
        $botUser = BotUser::create(['chat_id' => '700', 'platform' => 'vk']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSet('activeBotUser', fn ($u) => $u instanceof BotUser && $u->id === $botUser->id);
    }

    public function test_select_chat_zero_clears_active_dialog(): void
    {
        $botUser = BotUser::create(['chat_id' => '800', 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('selectChat', 0)
            ->assertSet('activeBotUserId', null)
            ->assertSet('activeBotUser', null)
            ->assertSet('chatMessages', fn ($msgs) => $msgs->isEmpty());
    }

    public function test_messages_ordered_by_created_at_asc(): void
    {
        $botUser = BotUser::create(['chat_id' => '900', 'platform' => 'telegram']);

        $first = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
            'text' => 'First',
            'created_at' => now()->subMinutes(10),
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

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSet('chatMessages', function ($msgs) use ($first, $second) {
                return $msgs->first()->id === $first->id
                    && $msgs->last()->id === $second->id;
            });
    }

    // ── Polling ────────────────────────────────────────────────────────────────

    public function test_poll_updates_loads_new_messages_for_active_dialog(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1030', 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id);

        $component->assertSet('chatMessages', fn ($m) => $m->isEmpty());

        // A new incoming message arrives after the dialog was opened.
        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'New incoming',
        ]);

        $component->call('pollUpdates')
            ->assertSet('chatMessages', fn ($m) => $m->count() === 1 && $m->first()->text === 'New incoming')
            ->assertDispatched('messages-updated');
    }

    public function test_poll_updates_does_not_scroll_without_new_messages(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1031', 'platform' => 'telegram']);
        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'Existing',
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('pollUpdates')
            ->assertNotDispatched('messages-updated');
    }

    public function test_poll_updates_refreshes_dialog_list(): void
    {
        BotUser::create(['chat_id' => '1032', 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class);

        BotUser::create(['chat_id' => '1033', 'platform' => 'telegram']);

        $component->call('pollUpdates')
            ->assertSet('dialogList', fn ($list) => $list->count() === 2);
    }

    // ── Send reply ─────────────────────────────────────────────────────────────

    public function test_send_reply_calls_send_reply_action(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1000', 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('replyText', 'Test reply from workspace')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertDispatched('admin-toast', message: 'Сообщение отправлено');

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'text' => 'Test reply from workspace',
        ]);
    }

    public function test_send_reply_clears_reply_text(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1001', 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('replyText', 'Something')
            ->call('sendReply')
            ->assertSet('replyText', '');
    }

    public function test_send_reply_silently_ignores_empty_message(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1002', 'platform' => 'telegram']);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('replyText', '   ')
            ->call('sendReply')
            ->assertHasNoErrors();

        // Nothing is sent or saved for an empty (whitespace-only) submission.
        $this->assertSame(0, Message::where('bot_user_id', $botUser->id)->where('message_type', 'outgoing')->count());
    }

    // ── Attachments ──────────────────────────────────────────────────────────────

    public function test_send_reply_with_attachment_dispatches_document_job(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1004', 'platform' => 'telegram']);
        $file = UploadedFile::fake()->create('report.pdf', 120, 'application/pdf');

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('attachment', $file)
            ->set('replyText', 'See attached')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertSet('attachment', null);

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'text' => 'See attached',
        ]);

        Queue::assertPushed(SendAdminDocumentJob::class);
    }

    public function test_send_reply_allows_file_only_message(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1005', 'platform' => 'telegram']);
        $file = UploadedFile::fake()->create('photo.png', 80, 'image/png');

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('attachment', $file)
            ->set('replyText', '')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'text' => null,
        ]);

        Queue::assertPushed(SendAdminDocumentJob::class);
    }

    public function test_supports_attachments_for_telegram_vk_and_max(): void
    {
        $telegram = BotUser::create(['chat_id' => '1006', 'platform' => 'telegram']);
        $component = Livewire::test(ConversationPage::class)->call('selectChat', $telegram->id);
        $this->assertTrue($component->instance()->supportsAttachments());

        $vk = BotUser::create(['chat_id' => '1007', 'platform' => 'vk']);
        $component->call('selectChat', $vk->id);
        $this->assertTrue($component->instance()->supportsAttachments());

        $max = BotUser::create(['chat_id' => '1008', 'platform' => 'max']);
        $component->call('selectChat', $max->id);
        $this->assertTrue($component->instance()->supportsAttachments());

        // External-source dialogs remain text-only.
        $external = BotUser::create(['chat_id' => '1010', 'platform' => 'widget']);
        $component->call('selectChat', $external->id);
        $this->assertFalse($component->instance()->supportsAttachments());
    }

    public function test_remove_attachment_clears_selected_file(): void
    {
        $botUser = BotUser::create(['chat_id' => '1009', 'platform' => 'telegram']);
        $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('attachment', $file)
            ->assertSet('attachment', fn ($a) => $a !== null)
            ->call('removeAttachment')
            ->assertSet('attachment', null);
    }

    public function test_send_reply_skipped_when_no_active_dialog(): void
    {
        Queue::fake();

        Livewire::test(ConversationPage::class)
            ->set('replyText', 'Orphaned reply')
            ->call('sendReply');

        $this->assertDatabaseMissing('messages', [
            'message_type' => 'outgoing',
            'text' => 'Orphaned reply',
        ]);
    }

    // ── Close dialog ─────────────────────────────────────────────────────────────

    public function test_close_dialog_marks_bot_user_closed(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1010', 'platform' => 'telegram', 'is_closed' => false]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('closeDialog')
            ->assertDispatched('admin-toast', message: 'Диалог закрыт');

        $botUser->refresh();
        $this->assertTrue($botUser->isClosed());
        $this->assertNotNull($botUser->closed_at);
    }

    public function test_close_dialog_noop_when_already_closed(): void
    {
        Queue::fake();

        $botUser = BotUser::create([
            'chat_id' => '1011',
            'platform' => 'telegram',
            'is_closed' => true,
            'closed_at' => now()->subDay(),
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('closeDialog');

        // Already-closed short-circuit: no close-flow jobs dispatched.
        Queue::assertNothingPushed();
    }

    public function test_close_dialog_skipped_when_no_active_dialog(): void
    {
        Queue::fake();

        Livewire::test(ConversationPage::class)
            ->call('closeDialog');

        Queue::assertNothingPushed();
    }

    // ── Ban user ─────────────────────────────────────────────────────────────────

    public function test_ban_user_marks_bot_user_banned_and_closed(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '1020', 'platform' => 'telegram', 'is_banned' => false]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('banUser')
            ->assertDispatched('admin-toast', message: 'Пользователь заблокирован');

        $botUser->refresh();
        $this->assertTrue($botUser->isBanned());
        $this->assertNotNull($botUser->banned_at);
        $this->assertTrue($botUser->isClosed());
    }

    public function test_ban_user_noop_when_already_banned(): void
    {
        Queue::fake();

        $botUser = BotUser::create([
            'chat_id' => '1021',
            'platform' => 'telegram',
            'is_banned' => true,
            'banned_at' => now()->subDay(),
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('banUser');

        Queue::assertNothingPushed();
    }

    public function test_ban_user_skipped_when_no_active_dialog(): void
    {
        Queue::fake();

        Livewire::test(ConversationPage::class)
            ->call('banUser');

        Queue::assertNothingPushed();
    }

    public function test_unban_user_clears_ban(): void
    {
        Queue::fake();

        $botUser = BotUser::create([
            'chat_id' => '1022',
            'platform' => 'telegram',
            'is_banned' => true,
            'banned_at' => now()->subDay(),
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('unbanUser')
            ->assertDispatched('admin-toast', message: 'Пользователь разблокирован');

        $botUser->refresh();
        $this->assertFalse($botUser->isBanned());
        $this->assertNull($botUser->banned_at);
    }

    public function test_unban_user_noop_when_not_banned(): void
    {
        $botUser = BotUser::create(['chat_id' => '1023', 'platform' => 'telegram', 'is_banned' => false]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->call('unbanUser');

        $botUser->refresh();
        $this->assertFalse($botUser->isBanned());
    }

    public function test_dialog_list_marks_banned_conversations(): void
    {
        BotUser::create([
            'chat_id' => '1024',
            'platform' => 'telegram',
            'is_banned' => true,
            'banned_at' => now(),
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        Livewire::test(ConversationPage::class)
            ->assertSee('Пользователь заблокирован')
            // banned badge takes priority over the closed badge
            ->assertDontSee('Обращение закрыто');
    }

    // ── Reopen on reply ────────────────────────────────────────────────────────

    public function test_sending_reply_reopens_closed_conversation(): void
    {
        Queue::fake();

        $botUser = BotUser::create([
            'chat_id' => '1025',
            'platform' => 'telegram',
            'is_closed' => true,
            'closed_at' => now()->subDay(),
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('replyText', 'Are you still there?')
            ->call('sendReply')
            ->assertHasNoErrors();

        $botUser->refresh();
        $this->assertFalse($botUser->isClosed());
        $this->assertNull($botUser->closed_at);
    }

    public function test_reply_form_always_shown_when_dialog_selected(): void
    {
        $botUser = BotUser::create(['chat_id' => '1003', 'platform' => 'telegram']);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id);

        $component->assertSet('activeBotUserId', $botUser->id);

        $this->assertTrue($component->instance()->shouldShowReplyForm());
    }

    // ── Quick replies ──────────────────────────────────────────────────────────

    public function test_insert_quick_reply_sets_reply_text(): void
    {
        Livewire::test(ConversationPage::class)
            ->call('insertQuickReply', 'Ожидайте, пожалуйста')
            ->assertSet('replyText', 'Ожидайте, пожалуйста');
    }

    // ── Media gallery ──────────────────────────────────────────────────────────

    public function test_get_media_attachments_returns_all_files_for_active_dialog(): void
    {
        $botUser = BotUser::create(['chat_id' => '2000', 'platform' => 'telegram']);

        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1,
            'to_id' => 0,
        ]);

        MessageAttachment::create([
            'message_id' => $message->id,
            'file_id' => 'file_abc',
            'file_type' => 'photo',
        ]);

        MessageAttachment::create([
            'message_id' => $message->id,
            'file_id' => 'file_doc',
            'file_type' => 'document',
        ]);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id);

        $attachments = $component->instance()->getMediaAttachments();

        // Regression: documents must appear alongside photos in the МЕДИАФАЙЛЫ block.
        $this->assertCount(2, $attachments);
        $this->assertEqualsCanonicalizing(
            ['photo', 'document'],
            $attachments->pluck('file_type')->all()
        );
    }

    public function test_get_media_attachments_returns_empty_without_active_dialog(): void
    {
        $component = Livewire::test(ConversationPage::class);

        $this->assertTrue($component->instance()->getMediaAttachments()->isEmpty());
    }

    // ── Auto-reply chips ─────────────────────────────────────────────────────────

    public function test_get_auto_replies_returns_only_enabled_rules(): void
    {
        AutoReply::create(['trigger' => 'Привет', 'response' => 'Здравствуйте!', 'enabled' => true]);
        AutoReply::create(['trigger' => 'Цена', 'response' => 'Тарифы', 'enabled' => true]);
        AutoReply::create(['trigger' => 'Архив', 'response' => 'Отключено', 'enabled' => false]);

        $autoReplies = Livewire::test(ConversationPage::class)
            ->instance()
            ->getAutoReplies();

        $this->assertCount(2, $autoReplies);
        $this->assertEqualsCanonicalizing(['Привет', 'Цена'], $autoReplies->pluck('trigger')->all());
    }

    public function test_auto_reply_trigger_chip_is_rendered_above_the_input(): void
    {
        $botUser = BotUser::create(['chat_id' => '5000', 'platform' => 'telegram']);
        AutoReply::create(['trigger' => 'Привет', 'response' => 'Здравствуйте!', 'enabled' => true]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSee('Привет');
    }

    // ── Avatar cleanup on delete ───────────────────────────────────────────────

    public function test_delete_bot_user_removes_avatar_file(): void
    {
        Storage::fake('local');

        $avatarPath = 'avatars/bot-user-99.jpg';
        Storage::disk('local')->put($avatarPath, 'AVATAR-BYTES');

        $botUser = BotUser::create([
            'chat_id' => 9999,
            'platform' => 'telegram',
            'avatar_path' => $avatarPath,
        ]);

        (new DeleteBotUser())->execute($botUser);

        Storage::disk('local')->assertMissing($avatarPath);
    }

    // ── Operator authorship ────────────────────────────────────────────────────

    public function test_send_reply_attributes_authenticated_operator(): void
    {
        Queue::fake();

        $operator = User::factory()->create(['name' => 'Operator Masha']);
        $botUser = BotUser::create(['chat_id' => '6000', 'platform' => 'telegram']);

        Livewire::actingAs($operator)
            ->test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->set('replyText', 'Attributed reply')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'sender_user_id' => $operator->id,
            'sender_name' => 'Operator Masha',
        ]);
    }

    public function test_outgoing_bubble_shows_operator_initials_when_author_known(): void
    {
        Queue::fake();

        $operator = User::factory()->create(['name' => 'Anna Ivanova']);
        $botUser = BotUser::create(['chat_id' => '6001', 'platform' => 'telegram']);

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'Hello from operator',
            'sender_user_id' => $operator->id,
            'sender_name' => $operator->name,
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSee('AI');  // initials A+I of "Anna Ivanova"
    }

    public function test_outgoing_bubble_shows_generic_fallback_when_no_author(): void
    {
        Queue::fake();

        $botUser = BotUser::create(['chat_id' => '6002', 'platform' => 'telegram']);

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'Historic reply without author',
        ]);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id)
            ->assertSee('Менеджер');
    }
}
