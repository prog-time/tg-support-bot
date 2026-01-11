<?php

namespace App\Jobs\SendMessage;

use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\TopicCreateJob;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\Message;
use App\TelegramBot\TelegramMethods;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SendExternalTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = 'incoming';

    private mixed $telegramMethods;

    public function __construct(
        int $botUserId,
        ExternalMessageDto $updateDto,
        TGTextMessageDto $queryParams,
        string $typeMessage,
        mixed $telegramMethods = null,
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
            $botUser->refresh();

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            if ($this->typeMessage === 'incoming') {
                if ($botUser->topic_id) {
                    $params['message_thread_id'] = $botUser->topic_id;
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
                    throw new \Exception('Файл не найден: ' . $fullPath);
                }

                $params['uploaded_file'] = new UploadedFile(
                    $fullPath,
                    basename($fullPath),
                    mime_content_type($fullPath),
                    null,
                    true
                );
            }

            $response = $this->telegramMethods->sendQueryTelegram(
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
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * Сохраняем сообщение в базу после успешной отправки
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
        ]);
    }

    /**
     * Редактируем сообщение
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
