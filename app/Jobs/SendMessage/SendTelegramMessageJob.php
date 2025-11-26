<?php

namespace App\Jobs\SendMessage;

use App\Actions\Telegram\BanMessage;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\TopicCreateJob;
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

    private mixed $telegramMethods;

    public int $botUserId;

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
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
                if ($methodQuery !== 'editMessageText' && $methodQuery !== 'editMessageCaption') {
                    $this->saveMessage($botUser, $response);
                    $this->updateTopic($botUser, $this->typeMessage);
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

                TopicCreateJob::withChain([
                    new SendTelegramMessageJob(
                        $this->botUserId,
                        $this->updateDto,
                        $this->queryParams,
                        $this->typeMessage
                    ),
                ])->dispatch($this->botUserId, $this->updateDto);

                return;
            }

            // ✅ 403 — пользователь заблокировал бота
            if ($response->response_code === 403) {
                Log::warning('403 — пользователь заблокировал бота');
                BanMessage::execute($botUser, $this->updateDto);
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

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
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
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
