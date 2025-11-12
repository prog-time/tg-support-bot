<?php

namespace App\Jobs\SendMessage;

use App\Actions\Telegram\BanMessage;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgTopicService;
use App\TelegramBot\TelegramMethods;
use Illuminate\Support\Facades\Log;

class SendTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public BotUser $botUser;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage;

    public TgTopicService $tgTopicService;

    public string $typeSource;

    public function __construct(
        BotUser $botUser,
        TelegramUpdateDto $updateDto,
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

            if (empty($params['message_thread_id']) && $this->typeMessage === 'incoming') {
                $params['message_thread_id'] = $this->botUser->topic_id;

                if (empty($params['message_thread_id'])) {
                    throw new \Exception('Ошибка! Отсутствует topic_id при запросах в группу!');
                }
            }

            $response = TelegramMethods::sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            // ✅ Успешная отправка
            if ($response->ok === true) {
                if ($methodQuery !== 'editMessageText' && $methodQuery !== 'editMessageCaption') {
                    $this->saveMessage($response);
                    $this->updateTopic();
                    return;
                }
            }

            // ✅ 429 Too Many Requests
            if ($response->response_code === 429) {
                $retryAfter = $response->parameters->retry_after ?? 3;
                Log::warning("429 Too Many Requests. Повтор через {$retryAfter} сек.");
                $this->release($retryAfter);
                return;
            }

            // ✅ 400 MARKDOWN_ERROR
            if ($response->response_code === 400 && $response->type_error === 'MARKDOWN_ERROR') {
                Log::warning('MARKDOWN_ERROR → переключаем parse_mode в HTML');
                $this->queryParams->parse_mode = 'html';
                $this->release(1);
                return;
            }

            // ✅ 400 TOPIC_NOT_FOUND
            if ($response->response_code === 400 && $response->type_error === 'TOPIC_NOT_FOUND') {
                Log::warning('TOPIC_NOT_FOUND → создаём новую тему');
                $newThreadId = $this->botUser->saveNewTopic();
                $this->queryParams->message_thread_id = $newThreadId;
                $this->release(1);
                return;
            }

            // ✅ 403 — пользователь заблокировал бота
            if ($response->response_code === 403) {
                Log::warning('403 — пользователь заблокировал бота');
                BanMessage::execute($this->botUser->topic_id);
                return;
            }

            // ✅ Неизвестная ошибка
            Log::error('SendTelegramMessageJob: неизвестная ошибка', [
                'response' => (array)$response,
            ]);
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

        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $this->updateDto->messageId,
            'to_id' => $resultQuery->message_id,
        ]);
    }

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function editMessage(mixed $resultQuery): void
    {
        //
    }
}
