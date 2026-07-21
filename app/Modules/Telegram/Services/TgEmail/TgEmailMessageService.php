<?php

namespace App\Modules\Telegram\Services\TgEmail;

use App\Models\Message;
use App\Modules\Email\DTOs\EmailMessageDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Email\Services\EmailThreadStore;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\Services\ActionService\Send\FromTgMessageService;
use Illuminate\Support\Facades\Log;

/**
 * Delivers a manager's reply typed in the Telegram supergroup topic to the
 * Email user, mirroring {@see \App\Modules\Telegram\Services\TgMax\TgMaxMessageService}.
 *
 * Without this the switch in {@see \App\Modules\Telegram\Controllers\TelegramBotController}
 * routed `platform = 'email'` group replies into the External-Sources default
 * arm, which has no webhook for email — so the reply was silently dropped even
 * though incoming mail worked (issue #214 follow-up). Email is text-only in v1;
 * media typed into the topic is not forwarded.
 */
class TgEmailMessageService extends FromTgMessageService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->typeQuery !== 'message') {
                throw new \Exception("Unknown event type: {$this->update->typeQuery}", 1);
            }

            $text = $this->update->text ?? $this->update->caption ?? null;

            if (!empty($text)) {
                $this->sendMessage($text);
            }
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * @param string $text
     *
     * @return void
     */
    protected function sendMessage(string $text = ''): void
    {
        if ($this->botUser === null || $text === '') {
            return;
        }

        // The send job only sends; the /admin/chats path records the row via
        // SendReplyAction, so the group-topic path records it here to keep
        // BR-002 (every sent message stored) and to surface the reply in the
        // admin workspace.
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 0,
            'text' => $text,
        ]);

        $headers = app(EmailThreadStore::class)->replyHeaders($this->botUser->id);

        SendEmailMessageJob::dispatch(
            EmailMessageDto::from([
                'to' => $this->botUser->chat_id,
                'subject' => $headers['subject'],
                'text' => $text,
                'inReplyTo' => $headers['inReplyTo'],
                'references' => $headers['references'],
            ])
        );
    }

    /**
     * Email v1 is text-only — media replies from the group topic are not forwarded.
     *
     * @return void
     */
    protected function sendPhoto(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendVoice(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
        //
    }
}
