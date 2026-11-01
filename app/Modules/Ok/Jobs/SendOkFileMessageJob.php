<?php

namespace App\Modules\Ok\Jobs;

use App\Helpers\TelegramHelper;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ok\Api\OkMethods;
use App\Modules\Ok\DTOs\OkFileMessageDto;
use App\Modules\Ok\DTOs\OkTextMessageDto;
use App\Modules\Ok\Services\OkAttachmentStorage;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Services\Settings\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Upload a manager's file to OK and send it. Audio and video that OK refuses go
 * as a plain file. If the file is not delivered, managers are told in the topic
 * and the text still reaches the user.
 */
class SendOkFileMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const READ_ATTEMPTS = 3;

    public int $timeout = 300;

    /** Pause between attempts to download the file from Telegram. */
    public int $retryDelaySeconds = 3;

    /**
     * @param OkFileMessageDto $file
     */
    public function __construct(public OkFileMessageDto $file)
    {
    }

    /**
     * @param OkMethods           $ok
     * @param OkAttachmentStorage $storage
     *
     * @return void
     */
    public function handle(OkMethods $ok, OkAttachmentStorage $storage): void
    {
        $contents = $this->readContents();

        if ($contents !== null) {
            $this->recordCopy($contents, $storage);
        }

        if ($contents !== null && $this->sendWithFallback($ok, $contents)) {
            return;
        }

        $this->notifyManagers();

        if ($this->file->text !== '') {
            $ok->sendQuery('sendMessage', (new OkTextMessageDto('sendMessage', $this->file->chat_id, $this->file->text))->toArray());
        }
    }

    /**
     * @param OkMethods $ok
     * @param string    $contents
     *
     * @return bool
     */
    private function sendWithFallback(OkMethods $ok, string $contents): bool
    {
        $type = OkMethods::attachmentType($this->file->mime);
        $types = in_array($type, ['AUDIO', 'VIDEO'], true) ? [$type, 'FILE'] : [$type];

        foreach ($types as $candidate) {
            try {
                $token = $ok->uploadFile($contents, $candidate, $this->file->name, $this->file->mime);
                $ok->sendAttachment($this->file->chat_id, $this->file->text, $candidate, $token);

                return true;
            } catch (\Throwable $e) {
                Log::channel('app')->warning('SendOkFileMessageJob: sending failed', [
                    'source' => 'ok_file_send_failed',
                    'type' => $candidate,
                    'error' => OkMethods::withoutToken($e->getMessage()),
                ]);
            }
        }

        return false;
    }

    /**
     * @return string|null
     */
    private function readContents(): ?string
    {
        if ($this->file->path !== null) {
            $contents = @file_get_contents($this->file->path);
            @unlink($this->file->path);

            return $contents === false ? null : $contents;
        }

        for ($attempt = 1; $attempt <= self::READ_ATTEMPTS; $attempt++) {
            $contents = $this->downloadFromTelegram($attempt);

            if ($contents !== null) {
                return $contents;
            }

            if ($attempt < self::READ_ATTEMPTS) {
                sleep($this->retryDelaySeconds);
            }
        }

        return null;
    }

    /**
     * One download attempt. The file URL contains the bot token, so only the
     * exception class is logged.
     *
     * @param int $attempt
     *
     * @return string|null
     */
    private function downloadFromTelegram(int $attempt): ?string
    {
        $status = null;
        $error = null;

        try {
            $url = TelegramHelper::getFileTelegramPath((string) $this->file->telegramFileId);
            $response = $url !== null ? Http::timeout(60)->get($url) : null;
            $status = $response?->status();

            if ($response?->successful()) {
                return $response->body();
            }
        } catch (\Throwable $e) {
            $error = get_class($e);
        }

        Log::channel('app')->warning('SendOkFileMessageJob: cannot download the file from Telegram', [
            'source' => 'ok_file_read_failed',
            'attempt' => $attempt,
            'status' => $status,
            'exception' => $error,
        ]);

        return null;
    }

    /**
     * Tell managers in the topic that the user did not get the file.
     *
     * @return void
     */
    private function notifyManagers(): void
    {
        $topicId = BotUser::where('chat_id', $this->file->chat_id)->value('topic_id');
        $groupId = (string) app(SettingsService::class)->get('telegram.group_id');

        Log::channel('app')->error('SendOkFileMessageJob: the file was not delivered', [
            'source' => 'ok_file_not_delivered',
            'chat_id' => $this->file->chat_id,
        ]);

        if (empty($topicId) || $groupId === '') {
            return;
        }

        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $groupId,
            'message_thread_id' => (int) $topicId,
            'text' => "⚠️ Файл «{$this->file->name}» не доставлен пользователю. Отправьте его ещё раз.",
            'parse_mode' => null,
        ]));
    }

    /**
     * Keep a local copy on the message so the admin workspace can show the file.
     *
     * @param string              $contents
     * @param OkAttachmentStorage $storage
     *
     * @return void
     */
    private function recordCopy(string $contents, OkAttachmentStorage $storage): void
    {
        $message = $this->file->messageId !== null ? Message::find($this->file->messageId) : null;

        if ($message === null) {
            return;
        }

        $stored = $storage->store($contents, $this->file->mime, $this->file->name);

        $message->attachments()->create([
            'file_id' => $stored['path'],
            'file_type' => match (OkMethods::attachmentType($this->file->mime)) {
                'IMAGE' => 'photo',
                'VIDEO' => 'video_note',
                'AUDIO' => 'audio_message',
                default => 'document',
            },
            'file_name' => $stored['name'],
        ]);
    }
}
