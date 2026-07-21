<?php

namespace App\Modules\Email\Services;

use App\Models\Message;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendEmailTelegramMessageJob;
use App\Modules\Telegram\Services\ActionService\Send\ToTgMessageService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * Routes an incoming email into the shared support funnel, mirroring
 * {@see \App\Modules\Avito\Services\AvitoMessageService} /
 * {@see \App\Modules\Max\Services\MaxMessageService}.
 *
 * Two mutually exclusive branches, exactly as the other channels:
 *  - Telegram supergroup configured → forward into the user's forum topic;
 *    {@see SendEmailTelegramMessageJob} persists the row after the API call.
 *  - No group configured → persist directly so the admin workspace still
 *    shows the message, then consider AI.
 * Neither branch can double-write a `messages` row.
 *
 * Unlike a webhook-driven channel, there is no "event type" discriminator
 * here — {@see \App\Modules\Email\Contracts\EmailInboxReader::fetchUnseen()}
 * only ever returns real inbound messages, so (unlike AvitoMessageService /
 * MaxMessageService) there is no unknown-event-type branch to guard against.
 *
 * {@see \App\Modules\Email\Console\PollInboxCommand} needs a success/failure
 * signal after calling handleUpdate() — the mailbox `Seen` flag must be set
 * ONLY after successful processing, so a mid-run failure is retried on the
 * next poll instead of being silently dropped. `handleUpdate()` itself MUST
 * stay `void` (it overrides the base class's abstract `handleUpdate(): void`,
 * and PHP requires an exact match for a `void` return type — declaring `bool`
 * here would be a fatal "declaration must be compatible" error). The outcome
 * is instead tracked on {@see self::$succeeded} and read via
 * {@see self::handledSuccessfully()} after the call.
 *
 * Email is text-only in this iteration: the media hooks required by the base
 * class are intentional no-ops (see the class-level note on each).
 */
class EmailMessageService extends ToTgMessageService
{
    protected string $source = 'email';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    /** @see self::handledSuccessfully() */
    private bool $succeeded = true;

    public function __construct(EmailUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * Route the incoming email into the support funnel.
     *
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            // Label the conversation with the real person, not just their
            // email address. Fills only empty fields — an operator-set name
            // is never overwritten.
            $this->captureProfile();

            if (empty($this->update->text)) {
                // Nothing to route (e.g. an empty-body email). Retrying would
                // not change that — leave $succeeded=true so it is marked seen.
                return;
            }

            app(EmailThreadStore::class)->remember(
                $this->botUser->id,
                $this->update->messageId,
                $this->update->subject,
            );

            if (!empty((string) app(SettingsService::class)->get('telegram.group_id'))) {
                $this->sendMessage();

                return;
            }

            // Group-OFF path: persist directly so the admin workspace shows it.
            $this->persistIncomingEmailMessage();
            $this->maybeDispatchAi($this->update->text);
        } catch (\Throwable $e) {
            Log::channel('app')->error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            $this->succeeded = false;
        }
    }

    /**
     * Whether the last handleUpdate() call completed without an unexpected
     * failure. Read by {@see \App\Modules\Email\Console\PollInboxCommand}
     * to decide whether the email may be marked seen.
     *
     * @return bool
     */
    public function handledSuccessfully(): bool
    {
        return $this->succeeded;
    }

    /**
     * Forward the message into the Telegram forum topic.
     *
     * @return void
     */
    protected function sendMessage(): void
    {
        $dto = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'text' => $this->update->text,
        ]);

        SendEmailTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );

        $this->maybeDispatchAi($this->update->text);
    }

    /**
     * Persist the email sender's display name onto the BotUser.
     *
     * Fills the field only when empty, so a name the operator may have set by
     * hand is never overwritten.
     *
     * @return void
     */
    protected function captureProfile(): void
    {
        if ($this->botUser === null) {
            return;
        }

        if (empty($this->botUser->display_name) && !empty($this->update->senderName)) {
            $this->botUser->update(['display_name' => $this->update->senderName]);
        }
    }

    /**
     * Persist an incoming email directly to the `messages` table without
     * routing it through the Telegram supergroup.
     *
     * Called only when no `telegram.group_id` is configured; the group-ON
     * path persists via {@see SendEmailTelegramMessageJob::saveMessage()} instead.
     *
     * @return void
     */
    protected function persistIncomingEmailMessage(): void
    {
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => $this->update->text ?? null,
        ]);
    }

    /**
     * Trigger the AI assistant for a text message, subject to the shared gate.
     *
     * @param string|null $text
     *
     * @return void
     */
    protected function maybeDispatchAi(?string $text): void
    {
        if ($this->botUser === null) {
            return;
        }

        $shouldAiReply = app(ShouldAiReply::class);
        if (!$shouldAiReply->shouldGenerateForBotUserText($this->botUser, $text)) {
            return;
        }

        if ((bool) app(SettingsService::class)->get('ai.auto_reply')) {
            SendAiReplyJob::dispatch($this->botUser->id, null, (string) $text);
        } else {
            SendAiDraftJob::dispatch($this->botUser->id, null, (string) $text);
        }
    }

    /**
     * Email attachments are out of scope for this iteration — the inbox
     * reader parses text-only, so these hooks are never reached.
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
