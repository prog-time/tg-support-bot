<?php

namespace App\Modules\Avito\Services;

use App\Models\Message;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Avito\DTOs\AvitoUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendAvitoTelegramMessageJob;
use App\Modules\Telegram\Services\ActionService\Send\ToTgMessageService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * Handles an incoming Avito update and routes it into the shared support
 * funnel, mirroring {@see \App\Modules\Max\Services\MaxMessageService}.
 *
 * Two mutually exclusive branches, exactly as the Max/VK channels do:
 *  - Telegram supergroup configured → forward into the user's forum topic;
 *    {@see SendAvitoTelegramMessageJob} persists the row after the API call.
 *  - No group configured → persist directly so the admin workspace still shows
 *    the message, then consider AI.
 * Neither branch can double-write a `messages` row.
 *
 * Avito is text-only in this iteration: the media hooks required by the base
 * class are intentional no-ops (see the class-level note on each).
 */
class AvitoMessageService extends ToTgMessageService
{
    protected string $source = 'avito';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    public function __construct(AvitoUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * Route an incoming Avito update into the support funnel.
     *
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->type !== 'message') {
                throw new \Exception('Unknown event type', 1);
            }

            if (empty($this->update->text)) {
                return;
            }

            if (!empty((string) app(SettingsService::class)->get('telegram.group_id'))) {
                $this->sendMessage();

                return;
            }

            // Group-OFF path: persist directly so the admin workspace shows it.
            $this->persistIncomingAvitoMessage();
            $this->maybeDispatchAi($this->update->text);
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
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

        SendAvitoTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );

        $this->maybeDispatchAi($this->update->text);
    }

    /**
     * Persist an incoming Avito message without routing it through Telegram.
     *
     * Called only when no `telegram.group_id` is configured; the group-ON path
     * persists via {@see SendAvitoTelegramMessageJob::saveMessage()} instead.
     *
     * @return void
     */
    protected function persistIncomingAvitoMessage(): void
    {
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => 'incoming',
            'from_id' => (int) $this->update->author_id,
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
     * Avito attachments are out of scope for this iteration — the Messenger
     * payload is parsed text-only, so these hooks are never reached.
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
