<?php

namespace App\Jobs\SendMessage;

use App\Actions\Telegram\BanMessage;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\TopicCreateJob;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgTopicService;
use App\TelegramBot\TelegramMethods;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendExternalTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage;

    public TgTopicService $tgTopicService;

    public int $botUserId;

    private mixed $telegramMethods;

    public function __construct(
        int $botUserId,
        ExternalMessageDto $updateDto,
        TGTextMessageDto $queryParams,
        string $typeMessage,
        mixed $telegramMethods = null,
    ) {
        $this->tgTopicService = new TgTopicService();

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

            if ($botUser->topic_id && $this->typeMessage === 'incoming') {
                $params['message_thread_id'] = $botUser->topic_id;
            }

            $response = $this->telegramMethods->sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            // ✅ Успешная отправка
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
            }

            // ✅ 429 Too Many Requests
            if ($response->response_code === 429) {
                $retryAfter = $response->parameters->retry_after ?? 3;

                (new LokiLogger())->log('warning', [
                    'message' => "429 Too Many Requests. Повтор через {$retryAfter} сек.",
                    'bot_user_id' => $this->botUser->id,
                ]);

                $this->release($retryAfter);
                return;
            }

            // ✅ 400 MARKDOWN_ERROR
            if ($response->response_code === 400 && $response->type_error === 'MARKDOWN_ERROR') {
                (new LokiLogger())->log('warning', [
                    'message' => 'MARKDOWN_ERROR → переключаем parse_mode в HTML',
                    'bot_user_id' => $this->botUser->id,
                ]);

                $this->queryParams->parse_mode = 'html';
                $this->release(1);
                return;
            }

            // ✅ 400 MARKDOWN_ERROR
            if ($response->response_code === 400 && $response->type_error === 'CHAT_NOT_FOUND') {
                (new LokiLogger())->log('warning', [
                    'message' => 'CHAT_NOT_FOUND → неправильный chat_id',
                    'bot_user_id' => $this->botUser->id,
                ]);

                $this->queryParams->parse_mode = 'html';
                $this->release(1);
                return;
            }

            // ✅ 400 TOPIC_NOT_FOUND
            if ($response->response_code === 400 && $response->type_error === 'TOPIC_NOT_FOUND') {
                Log::warning('TOPIC_NOT_FOUND → создаём новую тему');

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

            // ✅ 403 — пользователь заблокировал бота
            if ($response->response_code === 403) {
                (new LokiLogger())->log('warning', [
                    'message' => '403 — пользователь заблокировал бота',
                    'bot_user_id' => $this->botUser->id,
                ]);

                BanMessage::execute($this->botUser, $this->updateDto);
                return;
            }

            throw new \Exception('SendExternalTelegramMessageJob: неизвестная ошибка', 1);
        } catch (\Exception $e) {
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
