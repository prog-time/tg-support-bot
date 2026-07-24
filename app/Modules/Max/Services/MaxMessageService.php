<?php

namespace App\Modules\Max\Services;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Max\DTOs\MaxUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendMaxTelegramMessageJob;
use App\Modules\Telegram\Services\ActionService\Send\ToTgMessageService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

class MaxMessageService extends ToTgMessageService
{
    protected string $source = 'max';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(MaxUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * Handle an incoming Max update and route it to the appropriate send method.
     *
     * When the Telegram supergroup is configured, the message is forwarded to the
     * user's forum topic and persisted via SendMaxTelegramMessageJob::saveMessage().
     * When the group is NOT configured, the message is persisted directly so the
     * admin workspace always shows incoming Max messages (group-OFF path).
     * The two branches are mutually exclusive: no duplicate rows are created.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->type !== 'message_created') {
                throw new \Exception('Unknown event type', 1);
            }

            // Label the conversation with the real person, not just their id.
            // MAX ships the name/username in the webhook, so this is stored before
            // the forum topic is created (TopicCreateJob reads BotUser profile).
            $this->captureProfile();

            Log::channel('app')->info('MaxMessageService: incoming update', [
                'text' => $this->update->text,
                'listFileUrl' => $this->update->listFileUrl,
                'listAttachments' => $this->update->listAttachments,
                'rawAttachments' => $this->update->rawData['message']['body']['attachments'] ?? [],
            ]);

            // When the Telegram supergroup is configured, forward to the group topic;
            // the job persists the row after the API call succeeds.
            if (!empty((string) app(SettingsService::class)->get('telegram.group_id'))) {
                if (!empty($this->update->listAttachments)) {
                    $this->sendAttachments();
                } elseif (!empty($this->update->text)) {
                    $this->sendMessage();
                }
            } else {
                // Group-OFF path: persist directly so the admin workspace shows the message.
                $this->persistIncomingMaxMessage();
                if (!empty($this->update->text)) {
                    $this->maybeDispatchAi($this->update->text);
                }
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
     * Persist the MAX sender's display name / username onto the BotUser.
     *
     * Fills the fields only when empty, so a name the operator may have set by
     * hand is never overwritten. Fields absent from the webhook are left as-is.
     *
     * @return void
     */
    protected function captureProfile(): void
    {
        if ($this->botUser === null) {
            return;
        }

        $changes = [];

        if (empty($this->botUser->display_name) && !empty($this->update->senderName)) {
            $changes['display_name'] = $this->update->senderName;
        }

        if (empty($this->botUser->username) && !empty($this->update->senderUsername)) {
            $changes['username'] = $this->update->senderUsername;
        }

        if ($changes !== []) {
            $this->botUser->update($changes);
        }
    }

    /**
     * Persist an incoming Max message directly to the `messages` table without
     * routing it through the Telegram supergroup.
     *
     * Called only when no telegram.group_id is configured. The group-ON path
     * persists via SendMaxTelegramMessageJob::saveMessage() instead.
     *
     * @return void
     */
    protected function persistIncomingMaxMessage(): void
    {
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => 'incoming',
            'from_id' => $this->update->from_id ?? 0,
            'to_id' => 0,
            'text' => $this->update->text ?? null,
        ]);

        foreach ($this->update->listAttachments as $attachment) {
            $message->attachments()->create([
                'file_id' => $attachment['file_id'],
                'file_type' => $attachment['type'],
                'file_name' => $attachment['file_name'] ?? null,
            ]);
        }
    }

    /**
     * Dispatch a separate job for each attachment.
     * The text caption is attached to the first attachment only.
     *
     * @return void
     */
    protected function sendAttachments(): void
    {
        $caption = $this->update->text ?? '';
        $isFirst = true;

        foreach ($this->update->listAttachments as $attachment) {
            $type = $attachment['type'] ?? null;
            $url = $attachment['file_id'] ?? null;

            if (empty($url) || empty($type)) {
                continue;
            }

            $currentCaption = $isFirst ? $caption : '';
            $isFirst = false;

            switch ($type) {
                case 'photo':
                    $this->dispatchPhoto($url, $currentCaption);
                    break;

                case 'document':
                    $this->dispatchDocument($url, $currentCaption, $attachment['file_name'] ?? null);
                    break;

                case 'voice':
                    $this->dispatchVoice($url);
                    break;

                case 'video':
                    // Telegram Bot API sendVideo does not support direct URL uploads;
                    // forward as a document instead so the file is delivered.
                    $this->dispatchDocument($url, $currentCaption, $attachment['file_name'] ?? null);
                    break;

                default:
                    Log::channel('app')->warning('MaxMessageService: unsupported attachment type', [
                        'type' => $type,
                        'url' => $url,
                    ]);
                    break;
            }
        }
    }

    /**
     * Dispatch a photo to the Telegram group topic.
     *
     * @param string $url
     * @param string $caption
     *
     * @return void
     */
    protected function dispatchPhoto(string $url, string $caption = ''): void
    {
        $dto = TGTextMessageDto::from([
            'methodQuery' => 'sendPhoto',
            'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'photo' => $url,
            'caption' => $caption !== '' ? $caption : null,
        ]);

        Log::channel('app')->info('MaxMessageService: dispatchPhoto', [
            'photo' => $url,
            'caption' => $caption,
        ]);

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );
    }

    /**
     * Dispatch a document (or video forwarded as document) to the Telegram group topic.
     *
     * @param string      $url
     * @param string      $caption
     * @param string|null $fileName
     *
     * @return void
     */
    protected function dispatchDocument(string $url, string $caption = '', ?string $fileName = null): void
    {
        $dto = TGTextMessageDto::from([
            'methodQuery' => 'sendDocument',
            'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'document' => $url,
            'caption' => $caption !== '' ? $caption : null,
        ]);

        Log::channel('app')->info('MaxMessageService: dispatchDocument', [
            'document' => $url,
            'caption' => $caption,
            'fileName' => $fileName,
        ]);

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );
    }

    /**
     * Dispatch a voice message to the Telegram group topic.
     *
     * @param string $url
     *
     * @return void
     */
    protected function dispatchVoice(string $url): void
    {
        $dto = TGTextMessageDto::from([
            'methodQuery' => 'sendVoice',
            'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'voice' => $url,
        ]);

        Log::channel('app')->info('MaxMessageService: dispatchVoice', [
            'voice' => $url,
        ]);

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );
    }

    /**
     * Send a plain text message to the Telegram group topic.
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

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $dto,
        );

        $this->maybeDispatchAi($this->update->text);
    }

    /**
     * Trigger AI generation for an incoming Max text message, when gating allows.
     *
     * The draft/auto-reply job is dispatched without a TelegramUpdateDto — the
     * jobs derive the platform from BotUser and assemble the supergroup post
     * and the platform-specific user delivery themselves.
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
     * Not used for Max → TG direction (Max sends photos via listAttachments).
     *
     * @return void
     */
    protected function sendPhoto(): void
    {
        //
    }

    /**
     * Not used for Max → TG direction (Max sends documents via listAttachments).
     *
     * @return void
     */
    protected function sendDocument(): void
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
    protected function sendContact(): void
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
    protected function sendVoice(): void
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
}
