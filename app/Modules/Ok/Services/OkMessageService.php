<?php

namespace App\Modules\Ok\Services;

use App\Models\Message;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Ok\DTOs\OkAttachmentDto;
use App\Modules\Ok\DTOs\OkUpdateDto;
use App\Modules\Ok\Jobs\SaveOkIncomingAttachmentJob;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendOkTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Modules\Telegram\Services\ActionService\Send\ToTgMessageService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Route an incoming OK message to managers: into the forum topic when a group
 * is configured (SendOkTelegramMessageJob saves the rows), otherwise straight
 * into `messages` for the admin workspace. Every attachment becomes its own message.
 */
class OkMessageService extends ToTgMessageService
{
    protected string $source = 'ok';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    /**
     * @param OkUpdateDto $update
     */
    public function __construct(OkUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            $this->storeSenderName();

            $groupId = (string) app(SettingsService::class)->get('telegram.group_id');
            $groupId === '' ? $this->saveForAdminPanel() : $this->forwardToTopic($groupId);

            if ($this->update->text !== null) {
                $this->maybeDispatchAi($this->update->text);
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
     * Save the message directly when no Telegram group is configured.
     *
     * @return void
     */
    private function saveForAdminPanel(): void
    {
        if ($this->update->text !== null) {
            Message::create([
                'bot_user_id' => $this->botUser->id,
                'platform' => $this->botUser->platform,
                'message_type' => 'incoming',
                'from_id' => $this->update->numericSenderId(),
                'to_id' => 0,
                'text' => $this->update->text,
            ]);
        }

        foreach ($this->update->attachments as $attachment) {
            SaveOkIncomingAttachmentJob::dispatch($this->botUser->id, $this->update, $attachment);
        }
    }

    /**
     * Forward the text and every attachment as separate topic messages. Several
     * jobs run as a chain, so they keep the order and share one new topic.
     *
     * @param string $groupId
     *
     * @return void
     */
    private function forwardToTopic(string $groupId): void
    {
        $jobs = [];

        if ($this->update->text !== null) {
            $jobs[] = $this->forwardJob($groupId, new OkAttachmentDto('sendMessage', $this->update->text));
        }

        foreach ($this->update->attachments as $attachment) {
            $jobs[] = $this->forwardJob($groupId, $attachment);
        }

        if (count($jobs) > 1) {
            if (empty($this->botUser->topic_id)) {
                array_unshift($jobs, new TopicCreateJob($this->botUser->id));
            }

            Bus::chain($jobs)->dispatch();
        } elseif ($jobs !== []) {
            dispatch($jobs[0]);
        }
    }

    /**
     * @param string          $groupId
     * @param OkAttachmentDto $attachment
     *
     * @return SendOkTelegramMessageJob
     */
    private function forwardJob(string $groupId, OkAttachmentDto $attachment): SendOkTelegramMessageJob
    {
        return new SendOkTelegramMessageJob(
            $this->botUser->id,
            $this->update,
            TGTextMessageDto::from([
                'methodQuery' => $attachment->method,
                'chat_id' => $groupId,
                'message_thread_id' => $this->botUser->topic_id,
                'text' => $attachment->url === null ? $attachment->text : null,
                'parse_mode' => null,
            ]),
            $attachment,
        );
    }

    /**
     * Required by the base class; handleUpdate() routes the message itself.
     *
     * @return void
     */
    protected function sendMessage(): void
    {
    }

    /**
     * Keep the sender's name for the topic title when OK provides one.
     *
     * @return void
     */
    private function storeSenderName(): void
    {
        if ($this->update->senderName !== null && empty($this->botUser->display_name)) {
            $this->botUser->update(['display_name' => $this->update->senderName]);
        }
    }

    /**
     * Trigger the AI assistant for a text message, subject to the shared gate.
     *
     * @param string $text
     *
     * @return void
     */
    private function maybeDispatchAi(string $text): void
    {
        if (!app(ShouldAiReply::class)->shouldGenerateForBotUserText($this->botUser, $text)) {
            return;
        }

        if ((bool) app(SettingsService::class)->get('ai.auto_reply')) {
            SendAiReplyJob::dispatch($this->botUser->id, null, $text);
        } else {
            SendAiDraftJob::dispatch($this->botUser->id, null, $text);
        }
    }

    /**
     * @return void
     */
    protected function sendPhoto(): void
    {
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
    }

    /**
     * @return void
     */
    protected function sendVoice(): void
    {
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
    }
}
