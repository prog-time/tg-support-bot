<?php

namespace Tests\Unit\Modules\Ai\Actions;

use App\Helpers\AiHelper;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Admin\Jobs\MirrorAdminReplyToGroupJob;
use App\Modules\Ai\Actions\AiAcceptMessage;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdate_AiButtonAction;
use Tests\TestCase;

class AiAcceptMessageTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        BotUser::truncate();
        Message::truncate();
        Queue::fake();


        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_accept_ai_message_deletes_supergroup_message_and_delivers(): void
    {
        $aiTextMessage = 'Тестовое сообщение от AI';
        $managerTextMessage = 'Сообщение от менеджера';

        $messageData = Message::create([
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'platform' => 'telegram',
            'from_id' => time(),
            'to_id' => time(),
        ]);

        $messageAiData = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => $messageData->id,
            'text_ai' => $aiTextMessage,
            'text_manager' => $managerTextMessage,
        ]);

        $dataParams = TelegramUpdate_AiButtonAction::getDtoParams();
        $dataParams['callback_query']['data'] = 'ai_message_send_' . $messageAiData->message_id;
        $dataParams['callback_query']['message']['message_thread_id'] = $this->botUser->topic_id;
        $dto = TelegramUpdate_AiButtonAction::getDto($dataParams);

        (new AiAcceptMessage())->execute($dto);

        // deleteMessage still uses the full SaveTelegramMessageJob (supergroup delete).
        /** @phpstan-ignore-next-line */
        $pushedFull = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushedFull);
        $this->assertEquals('deleteMessage', $pushedFull[0]['job']->queryParams->methodQuery);
        $this->assertNull($pushedFull[0]['job']->queryParams->token);

        // User delivery now uses the simple (non-saving) job.
        /** @phpstan-ignore-next-line */
        $pushedSimple = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushedSimple);
        $this->assertEquals('sendMessage', $pushedSimple[0]['job']->queryParams->methodQuery);
        $this->assertEquals($this->botUser->chat_id, $pushedSimple[0]['job']->queryParams->chat_id);
        $this->assertEquals($aiTextMessage, $pushedSimple[0]['job']->queryParams->text);

        // AI answer persisted to messages table.
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'text' => $aiTextMessage,
        ]);
    }

    public function test_execute_for_draft_accepts_and_delivers_without_supergroup_edit_when_no_message_id(): void
    {
        $draft = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'text_ai' => 'AI draft text for admin',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        (new AiAcceptMessage())->executeForDraft($draft);

        $this->assertDatabaseHas('ai_messages', [
            'id' => $draft->id,
            'status' => AiMessage::STATUS_ACCEPTED,
        ]);

        // No full SendTelegramMessageJob (deleteMessage) since there's no supergroup message_id.
        Queue::assertNotPushed(SendTelegramMessageJob::class);

        // Simple job dispatched for delivery to user.
        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushed);
        $this->assertEquals('sendMessage', $pushed[0]['job']->queryParams->methodQuery);

        // Outgoing message persisted to messages table.
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'text' => 'AI draft text for admin',
        ]);

        // Exactly one outgoing row (no duplicates).
        $this->assertEquals(1, Message::where('bot_user_id', $this->botUser->id)->where('message_type', 'outgoing')->count());
    }

    public function test_execute_for_draft_deletes_supergroup_message_when_message_id_present(): void
    {
        app(\App\Services\Settings\SettingsService::class)->set('telegram.group_id', '-100000000000');

        $draft = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => 999,
            'text_ai' => 'AI draft with supergroup message',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        (new AiAcceptMessage())->executeForDraft($draft);

        $this->assertDatabaseHas('ai_messages', [
            'id' => $draft->id,
            'status' => AiMessage::STATUS_ACCEPTED,
        ]);

        // Full job for deleteMessage (supergroup cleanup).
        /** @phpstan-ignore-next-line */
        $pushedFull = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushedFull);
        $this->assertEquals('deleteMessage', $pushedFull[0]['job']->queryParams->methodQuery);

        // Simple job for user delivery.
        /** @phpstan-ignore-next-line */
        $pushedSimple = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushedSimple);
        $this->assertEquals('sendMessage', $pushedSimple[0]['job']->queryParams->methodQuery);

        // Outgoing message row persisted.
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'text' => 'AI draft with supergroup message',
        ]);
    }

    public function test_execute_for_draft_mirrors_answer_to_group_when_configured(): void
    {
        // Group fully configured → telegram()['connected'] is true → the accepted
        // answer is posted into the group topic with the AI label prefix (plain).
        $settings = app(\App\Services\Settings\SettingsService::class);
        $settings->set('telegram.token', 'bot:token');
        $settings->set('telegram.secret_key', 'secret');
        $settings->set('telegram.group_id', '-1001234567890');

        $this->botUser->update(['topic_id' => 555]);

        $draft = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'text_ai' => '<b>Ответ</b> пользователю',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        (new AiAcceptMessage())->executeForDraft($draft);

        Queue::assertPushed(MirrorAdminReplyToGroupJob::class, function ($job) {
            return $job->prefix === AiHelper::AI_LABEL . "\n"
                && $job->text === 'Ответ пользователю'; // HTML stripped
        });
    }

    public function test_execute_for_draft_does_not_mirror_when_group_not_configured(): void
    {
        // Group not connected (token/secret/group_id cleared) → no mirror.
        $settings = app(\App\Services\Settings\SettingsService::class);
        $settings->forget('telegram.token');
        $settings->forget('telegram.secret_key');
        $settings->forget('telegram.group_id');

        $draft = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'text_ai' => 'Ответ',
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        (new AiAcceptMessage())->executeForDraft($draft);

        Queue::assertNotPushed(MirrorAdminReplyToGroupJob::class);
    }

    public function test_execute_for_draft_persists_plain_text_for_telegram(): void
    {
        $htmlText = '<b>Ответ</b> с <i>форматированием</i>';

        $draft = AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => null,
            'text_ai' => $htmlText,
            'text_manager' => '',
            'status' => AiMessage::STATUS_PENDING,
        ]);

        (new AiAcceptMessage())->executeForDraft($draft);

        // messages.text must be plain (no HTML tags).
        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'outgoing',
            'text' => 'Ответ с форматированием',
        ]);

        // The simple job sends PLAIN text with no parse_mode — Telegram can reject
        // raw AI HTML ("can't parse entities"), so delivery matches the manager reply.
        /** @phpstan-ignore-next-line */
        $pushedSimple = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushedSimple);
        $this->assertEquals('Ответ с форматированием', $pushedSimple[0]['job']->queryParams->text);
        $this->assertNull($pushedSimple[0]['job']->queryParams->parse_mode);
    }
}
