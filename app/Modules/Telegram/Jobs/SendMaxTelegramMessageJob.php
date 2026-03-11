<?php

namespace App\Modules\Telegram\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Max\DTOs\MaxUpdateDto;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendMaxTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    /** @var MaxUpdateDto */
    public mixed $updateDto;

    /** @var TGTextMessageDto */
    public mixed $queryParams;

    public string $typeMessage = 'incoming';

    private TelegramMethods $telegramMethods;

    public function __construct(
        int $botUserId,
        MaxUpdateDto $updateDto,
        TGTextMessageDto $queryParams,
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

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            if ($botUser->topic_id) {
                $response = $this->telegramMethods->sendQueryTelegram(
                    'editForumTopic',
                    [
                        'chat_id' => config('traffic_source.settings.telegram.group_id'),
                        'message_thread_id' => $botUser->topic_id,
                        'icon_custom_emoji_id' => __('icons.incoming'),
                    ]
                );

                if ($response->isTopicNotFound || $response->type_error === 'TOPIC_NOT_MODIFIED') {
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
                                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                                'message_thread_id' => $botUser->topic_id,
                            ]
                        );
                        $botUser->update(['is_closed' => false, 'closed_at' => null]);
                    }
                }
            }

            if (!$botUser->topic_id) {
                TopicCreateJob::withChain([
                    new SendMaxTelegramMessageJob(
                        $this->botUserId,
                        $this->updateDto,
                        $this->queryParams,
                    ),
                ])->dispatch($this->botUserId);
                return;
            }

            // Max CDN URLs cannot be downloaded by Telegram directly.
            // Download the file ourselves and send via multipart upload.
            $fileField = match ($methodQuery) {
                'sendDocument' => 'document',
                'sendPhoto' => 'photo',
                'sendVoice' => 'voice',
                default => null,
            };

            if ($fileField !== null && isset($params[$fileField]) && str_starts_with((string) $params[$fileField], 'http')) {
                $fileUrl = $params[$fileField];
                unset($params[$fileField]);

                $fileResponse = Http::get($fileUrl);
                if ($fileResponse->failed()) {
                    throw new \RuntimeException("Failed to download from Max CDN: status={$fileResponse->status()}");
                }

                $urlPath = (string) parse_url($fileUrl, PHP_URL_PATH);
                $extension = pathinfo($urlPath, PATHINFO_EXTENSION);
                $tempPath = sys_get_temp_dir() . '/' . uniqid('max_', true) . ($extension ? '.' . $extension : '');
                file_put_contents($tempPath, $fileResponse->body());

                $params['uploaded_file_path'] = $tempPath;
            }

            $response = $this->telegramMethods->sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            if ($response->ok === true) {
                if ($methodQuery !== 'editMessageText' && $methodQuery !== 'editMessageCaption') {
                    $this->saveMessage($botUser, $response);
                    $this->updateTopic($botUser, $this->typeMessage);
                    return;
                }
            } else {
                $this->telegramResponseHandler($response);
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * Save message to database after successful sending.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
        if (!$resultQuery instanceof TelegramAnswerDto) {
            throw new \Exception('Expected TelegramAnswerDto', 1);
        }

        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $this->updateDto->from_id,
            'to_id' => $resultQuery->message_id,
            'text' => $this->updateDto->text ?? null,
        ]);

        foreach ($this->updateDto->listAttachments as $attachment) {
            $message->attachments()->create([
                'file_id' => $attachment['file_id'],
                'file_type' => $attachment['type'],
                'file_name' => $attachment['file_name'] ?? null,
            ]);
        }
    }

    /**
     * Edit message in database.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
