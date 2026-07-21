<?php

namespace Tests\Unit\Modules\Telegram\Services\TgEmail;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Telegram\Services\TgEmail\TgEmailMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

/**
 * A manager's reply typed in the Telegram supergroup topic must reach the Email
 * user. The controller routes by platform, and before this handler existed the
 * 'email' case fell into the External-Sources default arm — which has no webhook
 * for email — so the reply was silently dropped while incoming mail still worked.
 */
class TgEmailMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Message::truncate();
        BotUser::truncate();

        $this->botUser = BotUser::getUserByChatId('client@example.com', 'email');
        $this->botUser->topic_id = 321;
        $this->botUser->save();

        $this->basicPayload = TelegramUpdateDto_VKMock::getDtoParams($this->botUser);
    }

    public function test_group_topic_reply_is_sent_as_email(): void
    {
        $dto = TelegramUpdateDto_VKMock::getDto($this->basicPayload);

        (new TgEmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendEmailMessageJob::class, function ($job) use ($dto) {
            return $job->queryParams->to === 'client@example.com'
                && $job->queryParams->text === $dto->text;
        });
    }

    public function test_group_topic_reply_records_the_outgoing_message(): void
    {
        // The send job only sends — this handler must record the row itself, or
        // the reply never appears in /admin/chats and breaks BR-002.
        $dto = TelegramUpdateDto_VKMock::getDto($this->basicPayload);

        (new TgEmailMessageService($dto))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'email',
            'message_type' => 'outgoing',
            'text' => $dto->text,
        ]);
    }

    public function test_empty_reply_sends_nothing(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['text'] = '';

        (new TgEmailMessageService(TelegramUpdateDto_VKMock::getDto($payload)))->handleUpdate();

        Queue::assertNotPushed(SendEmailMessageJob::class);
        $this->assertDatabaseCount('messages', 0);
    }
}
