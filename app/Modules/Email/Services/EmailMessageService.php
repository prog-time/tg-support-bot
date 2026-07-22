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
 * Incoming email supports at most one attachment, forwarded as a Telegram
 * photo/document by {@see self::sendMessage()} — see `EmailUpdateDto::$attachments`.
 * The base class's per-media-type hooks (sendPhoto/sendDocument/...) are
 * intentional no-ops here (see the class-level note on each): IMAP has no
 * "message type" discriminator the way a webhook payload does.
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

            if (empty($this->update->text) && $this->update->attachments === []) {
                // Nothing to route (e.g. an empty-body email with no
                // attachment). Retrying would not change that — leave
                // $succeeded=true so it is marked seen.
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
            $this->maybeDispatchAi($this->update->displayText());
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
        $text = $this->update->displayText();
        $escapedText = $this->escapeForTelegramHtml($text);
        $attachment = $this->update->attachments[0] ?? null;

        $dto = TGTextMessageDto::from($attachment !== null ? [
            // An attachment goes out as a photo/document with the text as its
            // caption — Telegram has no "text + separate file" single call, and
            // this mirrors how MaxMessageService forwards incoming Max media.
            'methodQuery' => str_starts_with($attachment['mime'], 'image/') ? 'sendPhoto' : 'sendDocument',
            'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'caption' => $escapedText !== '' ? $escapedText : null,
            'uploaded_file_path' => $attachment['path'],
        ] : [
            'methodQuery' => 'sendMessage',
            'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            // Telegram's parse_mode=html (TGTextMessageDto's default) treats
            // an unescaped '<'/'>'/'&' as markup. A plain-text email body is
            // never *meant* to carry HTML, but a mail client's own "On ... X
            // <address> wrote:" reply-quote header routinely contains a bare
            // '<address' — Telegram then rejects the whole send with 400
            // "can't parse entities", and since that failure doesn't throw,
            // it was silently swallowed: the email got marked seen and the
            // reply vanished, never reaching Telegram or the messages table.
            'text' => $escapedText,
        ]);

        SendEmailTelegramMessageJob::dispatch(
            $this->botUser->id,
            // Queue-safe copy — see EmailUpdateDto::withoutProviderRef(): the
            // full DTO's providerRef (raw IMAP message) breaks queue payload
            // serialization for emails with an attachment.
            $this->update->withoutProviderRef(),
            $dto,
        );

        $this->maybeDispatchAi($text);
    }

    /**
     * Escape the characters Telegram's parse_mode=html requires escaped in
     * plain text ('<', '>', '&') — deliberately NOT quotes: Telegram only
     * recognizes &lt;/&gt;/&amp; in message text, so encoding quotes would
     * surface as a literal "&quot;" instead of being decoded back.
     *
     * @param string $text
     *
     * @return string
     */
    private function escapeForTelegramHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
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
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => $this->update->displayText(),
        ]);

        $this->recordIncomingAttachment($message);
    }

    /**
     * Record the email's attachment (if any) on the local `local` disk copy
     * ({@see \App\Modules\Email\Api\EmailImapClient::extractAttachments()}
     * already stored it under `chat-attachments/`) so the admin workspace can
     * render/download it via the same route outgoing attachments use — email
     * has no provider-native URL to fall back on, unlike VK/Max.
     *
     * @param Message $message
     *
     * @return void
     */
    private function recordIncomingAttachment(Message $message): void
    {
        $attachment = $this->update->attachments[0] ?? null;
        if ($attachment === null || empty($attachment['storedPath'])) {
            return;
        }

        $message->attachments()->create([
            'file_id' => $attachment['storedPath'],
            'file_type' => str_starts_with($attachment['mime'], 'image/') ? 'photo' : 'document',
            'file_name' => $attachment['name'],
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
     * Never reached: an incoming email's (single) attachment is forwarded by
     * {@see self::sendMessage()} directly — via `EmailUpdateDto::$attachments`
     * rather than the base class's per-media-type dispatch, since IMAP has no
     * "message type" the way a webhook payload does; this hook only exists to
     * satisfy the abstract base class.
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
