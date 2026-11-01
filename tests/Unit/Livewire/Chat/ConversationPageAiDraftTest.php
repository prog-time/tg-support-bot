<?php

namespace Tests\Unit\Livewire\Chat;

use App\Livewire\Chat\ConversationPage;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Models\User;
use App\Modules\Ai\Actions\AiAcceptMessage;
use App\Modules\Ai\Actions\AiCancelMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ConversationPageAiDraftTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Queue::fake();

        $this->botUser = BotUser::create(['chat_id' => time(), 'platform' => 'telegram']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_pending_drafts_shown_when_ai_draft_exists(): void
    {
        AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'text_ai' => 'AI draft text',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $this->botUser->id);

        $this->assertTrue($component->get('pendingAiDrafts')->isNotEmpty());

        // The draft must actually RENDER in the workspace (not just be loaded) —
        // regression guard for the blade condition that previously gated it on
        // the removed config('app.manager_interface') === 'admin_panel'.
        $component->assertSee('ИИ-черновик')->assertSee('AI draft text');
    }

    public function test_pending_drafts_also_shown_when_message_id_set(): void
    {
        // Drafts with a Telegram message_id (main bot posted to supergroup) are
        // still shown in the admin panel workspace.
        AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => '321',
            'text_ai' => 'AI draft text with supergroup message',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $this->botUser->id);

        $this->assertTrue($component->get('pendingAiDrafts')->isNotEmpty());
    }

    public function test_accept_ai_draft_marks_accepted_and_delivers(): void
    {
        $draft = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'text_ai' => 'AI answer',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        $acceptMock = Mockery::mock(AiAcceptMessage::class);
        $acceptMock->shouldReceive('executeForDraft')
            ->once()
            ->with(Mockery::on(fn ($m) => $m->id === $draft->id))
            ->andReturnUsing(function ($m): void {
                $m->update(['status' => AiMessage::STATUS_ACCEPTED]);
            });
        $this->app->instance(AiAcceptMessage::class, $acceptMock);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $this->botUser->id)
            ->call('acceptAiDraft', $draft->id);

        $this->assertDatabaseHas('ai_messages', [
            'id' => $draft->id,
            'status' => AiMessage::STATUS_ACCEPTED,
        ]);
    }

    public function test_cancel_ai_draft_marks_cancelled(): void
    {
        $draft = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'text_ai' => 'AI answer',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        $cancelMock = Mockery::mock(AiCancelMessage::class);
        $cancelMock->shouldReceive('executeForDraft')
            ->once()
            ->with(Mockery::on(fn ($m) => $m->id === $draft->id))
            ->andReturnUsing(function ($m): void {
                $m->update(['status' => AiMessage::STATUS_CANCELLED]);
            });
        $this->app->instance(AiCancelMessage::class, $cancelMock);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $this->botUser->id)
            ->call('cancelAiDraft', $draft->id);

        $this->assertDatabaseHas('ai_messages', [
            'id' => $draft->id,
            'status' => AiMessage::STATUS_CANCELLED,
        ]);
    }

    public function test_edit_ai_draft_fills_reply_text_and_cancels(): void
    {
        $draft = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'text_ai' => 'Edit this text',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        $cancelMock = Mockery::mock(AiCancelMessage::class);
        $cancelMock->shouldReceive('executeForDraft')
            ->once()
            ->andReturnUsing(function ($m): void {
                $m->update(['status' => AiMessage::STATUS_CANCELLED]);
            });
        $this->app->instance(AiCancelMessage::class, $cancelMock);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $this->botUser->id)
            ->call('editAiDraft', $draft->id);

        $this->assertSame('Edit this text', $component->get('replyText'));
    }

    public function test_accept_ignores_draft_belonging_to_other_user(): void
    {
        $otherUser = BotUser::create(['chat_id' => time() + 1, 'platform' => 'telegram']);
        $draft = AiMessage::create([
            'bot_user_id' => $otherUser->id,
            'message_id' => null,
            'text_ai' => 'Other user draft',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        $acceptMock = Mockery::mock(AiAcceptMessage::class);
        $acceptMock->shouldNotReceive('executeForDraft');
        $this->app->instance(AiAcceptMessage::class, $acceptMock);

        Livewire::test(ConversationPage::class)
            ->call('selectChat', $this->botUser->id)
            ->call('acceptAiDraft', $draft->id);

        // Draft status should remain pending (not accepted)
        $this->assertDatabaseHas('ai_messages', [
            'id' => $draft->id,
            'status' => AiMessage::STATUS_PENDING,
        ]);
    }
}
