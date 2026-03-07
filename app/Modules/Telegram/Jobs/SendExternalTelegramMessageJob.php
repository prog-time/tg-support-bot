<?php

namespace App\Modules\Telegram\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\External\DTOs\ExternalMessageDto;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendExternalTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    /** @var ExternalMessageDto */
    public mixed $updateDto;

    /** @var TGTextMessageDto */
    public mixed $queryParams;

    public string $typeMessage = 'incoming';

    public function __construct(
        int $botUserId,
        ExternalMessageDto $updateDto,
        TGTextMessageDto $queryParams,
        string $typeMessage,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;
        $this->typeMessage = $typeMessage;
    }

    public function handle(?TelegramMethods $telegramMethods = null): void
    {
        $telegramMethods ??= app(TelegramMethods::class);

        try {
            $botUser = BotUser::find($this->botUserId);
            $botUser->refresh();

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            if ($this->typeMessage === 'incoming') {
                if ($botUser->topic_id) {
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
                } else {
                    TopicCreateJob::withChain([
                        new SendExternalTelegramMessageJob(
                            $this->botUserId,
                            $this->updateDto,
                            $this->queryParams,
                            $this->typeMessage
                        ),
                    ])->dispatch($this->botUserId);

                    return;
                }
            }

            if (!empty($params['uploaded_file_path'])) {
                $params['uploaded_file'] = Storage::get($params['uploaded_file_path']);

                $fullPath = storage_path('app/public/' . $params['uploaded_file_path']);

                if (!file_exists($fullPath)) {
                    throw new \Exception('File not found: ' . $fullPath);
                }

                $params['uploaded_file'] = new UploadedFile(
                    $fullPath,
                    basename($fullPath),
                    mime_content_type($fullPath),
                    null,
                    true
                );
            }

            $response = $telegramMethods->sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            if ($response->ok === true) {
                if ($methodQuery === 'editMessageText' || $methodQuery === 'editMessageCaption') {
                    $this->editMessage($botUser, $response);
                    $this->updateTopic($botUser, $this->typeMessage);
                    return;
                } else {
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
            'platform' => $botUser->externalUser->source,
            'message_type' => 'incoming',
            'from_id' => time(),
            'to_id' => $resultQuery->message_id,
        ]);
        $message->externalMessage()->create([
            'text' => $resultQuery->text,
            'file_id' => $resultQuery->fileId,
            'file_type' => $this->updateDto->file_type ?? null,
            'file_name' => $this->updateDto->file_name ?? null,
        ]);
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
        if (!$resultQuery instanceof TelegramAnswerDto) {
            throw new \Exception('Expected TelegramAnswerDto', 1);
        }

        $message = Message::where([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->externalUser->source,
            'message_type' => 'incoming',
            'to_id' => $resultQuery->message_id,
        ])->first();

        $message->externalMessage()->update([
            'text' => $resultQuery->text,
            'file_id' => $resultQuery->fileId,
        ]);

        $message->save();
    }
}
