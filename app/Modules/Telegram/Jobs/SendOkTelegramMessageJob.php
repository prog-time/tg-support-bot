<?php

namespace App\Modules\Telegram\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ok\DTOs\OkAttachmentDto;
use App\Modules\Ok\DTOs\OkUpdateDto;
use App\Modules\Ok\Services\OkAttachmentStorage;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Forward an incoming OK message into the user's Telegram forum topic. The row
 * in `messages` is written only after Telegram accepted the message.
 */
class SendOkTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 180;

    public int $botUserId;

    /** @var OkUpdateDto */
    public mixed $updateDto;

    /** @var TGTextMessageDto */
    public mixed $queryParams;

    public string $typeMessage = 'incoming';

    private TelegramMethods $telegramMethods;

    public function __construct(
        int $botUserId,
        OkUpdateDto $updateDto,
        TGTextMessageDto $queryParams,
        public OkAttachmentDto $attachment,
        ?TelegramMethods $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);
            if ($botUser === null) {
                throw new \RuntimeException('BotUser not found for OK forward', 1);
            }

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            if ($botUser->topic_id) {
                $response = $this->telegramMethods->sendQueryTelegram(
                    'editForumTopic',
                    [
                        'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
                        'message_thread_id' => $botUser->topic_id,
                        'icon_custom_emoji_id' => __('icons.incoming'),
                    ]
                );

                if ($response->isTopicNotFound) {
                    $botUser->update([
                        'topic_id' => null,
                    ]);

                    $botUser->refresh();
                } else {
                    $params['message_thread_id'] = $botUser->topic_id;
                    if ($botUser->isClosed()) {
                        $this->telegramMethods->sendQueryTelegram(
                            'reopenForumTopic',
                            [
                                'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
                                'message_thread_id' => $botUser->topic_id,
                            ]
                        );
                        $botUser->update(['is_closed' => false, 'closed_at' => null]);
                    }
                }
            }

            if (!$botUser->topic_id) {
                TopicCreateJob::withChain([
                    new SendOkTelegramMessageJob(
                        $this->botUserId,
                        $this->updateDto,
                        $this->queryParams,
                        $this->attachment,
                    ),
                ])->dispatch($this->botUserId);

                return;
            }

            $file = $this->attachment->url !== null ? app(OkAttachmentStorage::class)->download($this->attachment->url) : null;

            if ($this->attachment->url !== null && $file === null) {
                $this->sendLink($botUser, $params);

                return;
            }

            if ($file !== null) {
                $params['uploaded_file_path'] = $this->writeTempFile($file['contents'], $file['path']);
            }

            $response = $this->telegramMethods->sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            if ($response->ok === true) {
                $this->saveMessage($botUser, $response, $file);
                $this->updateTopic($botUser, $this->typeMessage);

                return;
            }

            if ($file !== null && $response->response_code !== 429) {
                $this->sendLink($botUser, $params);

                return;
            }

            $this->telegramResponseHandler($response);
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * Tell managers about a file that could not be forwarded, with its link.
     *
     * @param BotUser $botUser
     * @param array   $params
     *
     * @return void
     */
    private function sendLink(BotUser $botUser, array $params): void
    {
        unset($params['uploaded_file_path']);

        $response = $this->telegramMethods->sendQueryTelegram('sendMessage', $params + ['text' => $this->attachment->text]);

        if ($response->ok === true) {
            $this->saveMessage($botUser, $response, null);
            $this->updateTopic($botUser, $this->typeMessage);
        }
    }

    /**
     * @param string $contents
     * @param string $storedPath Gives the file extension
     *
     * @return string
     */
    private function writeTempFile(string $contents, string $storedPath): string
    {
        $extension = pathinfo($storedPath, PATHINFO_EXTENSION);
        $path = sys_get_temp_dir() . '/' . Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Persist the forwarded message after Telegram accepted it.
     *
     * @param BotUser    $botUser
     * @param mixed      $resultQuery
     * @param array|null $file        Stored file: path and name
     *
     * @return void
     *
     * @throws \Exception When the API layer returned an unexpected type.
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery, ?array $file = null): void
    {
        if (!$resultQuery instanceof TelegramAnswerDto) {
            throw new \Exception('Expected TelegramAnswerDto', 1);
        }

        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $this->updateDto->numericSenderId(),
            'to_id' => $resultQuery->message_id,
            'text' => $file === null ? $this->attachment->text : null,
        ]);

        if ($file !== null) {
            $message->attachments()->create([
                'file_id' => $file['path'],
                'file_type' => $this->attachment->fileType ?? 'document',
                'file_name' => $file['name'],
            ]);
        }
    }

    /**
     * Editing forwarded OK messages is not supported.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
    }
}
