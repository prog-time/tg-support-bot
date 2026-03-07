<?php

namespace App\Modules\Telegram\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use Illuminate\Support\Facades\Log;

class SendTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    /** @var TelegramUpdateDto */
    public mixed $updateDto;

    /** @var TGTextMessageDto */
    public mixed $queryParams;

    public string $typeMessage;

    private TelegramMethods $telegramMethods;

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
        TGTextMessageDto $queryParams,
        string $typeMessage,
        ?TelegramMethods $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;
        $this->typeMessage = $typeMessage;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            if ($this->typeMessage === 'incoming') {
                if ($botUser->topic_id) {
                    $response = $this->telegramMethods->sendQueryTelegram(
                        'editForumTopic',
                        [
                            'chat_id' => config('traffic_source.settings.telegram.group_id'),
                            'message_thread_id' => $botUser->topic_id,
                            'icon_custom_emoji_id' => __('icons.incoming'),
                        ]
                    );

                    if ($botUser->isClosed()) {
                        $response = $this->telegramMethods->sendQueryTelegram(
                            'reopenForumTopic',
                            [
                                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                                'message_thread_id' => $botUser->topic_id,
                            ]
                        );
                    }

                    if ($response->isTopicNotFound) {
                        $botUser->update([
                           'topic_id' => null,
                        ]);

                        $botUser->refresh();
                    } else {
                        $params['message_thread_id'] = $botUser->topic_id;
                        if ($botUser->isClosed()) {
                            $botUser->update(['is_closed' => false, 'closed_at' => null]);
                        }
                    }
                }

                if (!$botUser->topic_id) {
                    TopicCreateJob::withChain([
                        new SendTelegramMessageJob(
                            $this->botUserId,
                            $this->updateDto,
                            $this->queryParams,
                            $this->typeMessage
                        ),
                    ])->dispatch($this->botUserId);
                    return;
                }
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
            'from_id' => $this->updateDto->messageId,
            'to_id' => $resultQuery->message_id,
            'text' => $this->typeMessage === 'incoming' ? ($this->updateDto->text ?? null) : ($this->queryParams->text ?? null),
        ]);

        if ($this->typeMessage === 'incoming' && !empty($this->updateDto->fileId)) {
            $message->attachments()->create([
                'file_id' => $this->updateDto->fileId,
                'file_type' => $this->updateDto->fileType ?? 'document',
            ]);
        }

        if ($this->typeMessage === 'outgoing') {
            $fileId = $this->queryParams->photo
                ?? $this->queryParams->document
                ?? $this->queryParams->voice
                ?? $this->queryParams->sticker
                ?? $this->queryParams->video_note
                ?? $this->queryParams->file_id;

            $fileType = match (true) {
                !empty($this->queryParams->photo) => 'photo',
                !empty($this->queryParams->document) => 'document',
                !empty($this->queryParams->voice) => 'voice',
                !empty($this->queryParams->sticker) => 'sticker',
                !empty($this->queryParams->video_note) => 'video_note',
                !empty($this->queryParams->file_id) => 'document',
                default => null,
            };

            if (!empty($fileId) && !empty($fileType)) {
                $message->attachments()->create([
                    'file_id' => $fileId,
                    'file_type' => $fileType,
                ]);
            }
        }
    }

    /**
     * Edit message in database.
     *
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
