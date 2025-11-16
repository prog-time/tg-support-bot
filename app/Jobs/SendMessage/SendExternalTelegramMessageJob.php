<?php

namespace App\Jobs\SendMessage;

use App\Actions\Telegram\BanMessage;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgTopicService;
use App\TelegramBot\TelegramMethods;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SendExternalTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public BotUser $botUser;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage;

    public TgTopicService $tgTopicService;

    public function __construct(
        BotUser $botUser,
        ExternalMessageDto $updateDto,
        TGTextMessageDto $queryParams,
        string $typeMessage,
    ) {
        $this->tgTopicService = new TgTopicService();

        $this->botUser = $botUser;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;
        $this->typeMessage = $typeMessage;
    }

    public function handle(): void
    {
        try {
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

            $response = TelegramMethods::sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            // ✅ Успешная отправка
            if ($response->ok === true) {
                if ($methodQuery === 'editMessageText' || $methodQuery === 'editMessageCaption') {
                    $this->editMessage($response);
                    $this->updateTopic();
                    return;
                } else {
                    $this->saveMessage($response);
                    $this->updateTopic();
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
                (new LokiLogger())->log('warning', [
                    'message' => 'TOPIC_NOT_FOUND → создаём новую тему',
                    'bot_user_id' => $this->botUser->id,
                ]);

                $newThreadId = $this->botUser->saveNewTopic();
                $this->queryParams->message_thread_id = $newThreadId;
                $this->release(1);
                return;
            }

            // ✅ 403 — пользователь заблокировал бота
            if ($response->response_code === 403) {
                (new LokiLogger())->log('warning', [
                    'message' => '403 — пользователь заблокировал бота',
                    'bot_user_id' => $this->botUser->id,
                ]);

                BanMessage::execute($this->botUser->topic_id);
                return;
            }

            throw new \Exception('SendTelegramMessageJob: неизвестная ошибка', 1);
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function saveMessage(mixed $resultQuery): void
    {
        if (!$resultQuery instanceof TelegramAnswerDto) {
            throw new \Exception('Expected TelegramAnswerDto', 1);
        }

        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
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
    protected function editMessage(mixed $resultQuery): void
    {
        if (!$resultQuery instanceof TelegramAnswerDto) {
            throw new \Exception('Expected TelegramAnswerDto', 1);
        }

        $message = Message::where([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
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
