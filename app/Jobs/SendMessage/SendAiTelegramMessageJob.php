<?php

namespace App\Jobs\SendMessage;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Helpers\AiHelper;
use App\Logging\LokiLogger;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Services\Ai\AiAssistantService;
use App\TelegramBot\TelegramMethods;

class SendAiTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public string $typeMessage = 'incoming';

    private mixed $telegramMethods;

    public string $managerTextMessage;

    public string $aiTextMessage;

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
        string $managerTextMessage,
        string $aiTextMessage,
        mixed $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->managerTextMessage = $managerTextMessage;
        $this->aiTextMessage = $aiTextMessage;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);

            $managerTextMessage = trim(str_replace('/ai_generate', '', $this->updateDto->text));
            if (empty($managerTextMessage)) {
                throw new \Exception('Сообщение пустое!', 1);
            }

            // Создать AI-запрос
            $aiService = new AiAssistantService();
            $aiResponse = $aiService->processMessage(new AiRequestDto(
                message: $managerTextMessage,
                userId: $this->botUserId,
                platform: 'telegram',
                provider: config('ai.default_provider'),
                forceEscalation: false
            ));

            if (empty($aiResponse)) {
                throw new \Exception('Не удалось отправить запрос в AI!', 1);
            }

            $response = $this->telegramMethods->sendQueryTelegram('sendMessage', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $botUser->topic_id,
                'text' => AiHelper::preparedAiAnswer($this->managerTextMessage, $this->aiTextMessage),
                'parse_mode' => 'html',
            ], config('traffic_source.settings.telegram_ai.token'));

            // ✅ Успешная отправка
            if ($response->ok === true) {
                $this->saveMessage($botUser, $response);

                // изменяем сообщение
                SendTelegramMessageJob::dispatch(
                    $botUser->id,
                    $this->updateDto,
                    TGTextMessageDto::from([
                        'token' => config('traffic_source.settings.telegram_ai.token'),
                        'methodQuery' => 'editMessageText',
                        'typeSource' => 'supergroup',
                        'chat_id' => config('traffic_source.settings.telegram.group_id'),
                        'message_id' => $response->message_id,
                        'message_thread_id' => $response->message_thread_id,
                        'text' => $response->text,
                        'parse_mode' => 'html',
                        'reply_markup' => AiHelper::preparedAiReplyMarkup($response->message_id, $this->aiTextMessage),
                    ]),
                    'incoming',
                );

                // удаляем сообщение
                SendTelegramMessageJob::dispatch(
                    $botUser->id,
                    $this->updateDto,
                    TGTextMessageDto::from([
                        'methodQuery' => 'deleteMessage',
                        'typeSource' => 'supergroup',
                        'chat_id' => $this->updateDto->chatId,
                        'message_thread_id' => $response->message_thread_id,
                        'message_id' => $this->updateDto->messageId,
                    ]),
                    'outgoing',
                );
                return;
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

        AiMessage::create([
            'bot_user_id' => $botUser->id,
            'message_id' => $resultQuery->message_id,
            'text_ai' => $this->aiTextMessage,
            'text_manager' => $this->managerTextMessage,
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
