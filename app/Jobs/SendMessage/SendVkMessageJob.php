<?php

namespace App\Jobs\SendMessage;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgTopicService;
use App\VkBot\VkMethods;
use Illuminate\Support\Facades\DB;

class SendVkMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public BotUser $botUser;

    public mixed $updateDto;

    public mixed $queryParams;

    public TgTopicService $tgTopicService;

    public string $typeMessage = 'outgoing';

    public function __construct(
        BotUser $botUser,
        TelegramUpdateDto $updateDto,
        VkTextMessageDto $queryParams,
    ) {
        $this->tgTopicService = new TgTopicService();

        $this->botUser = $botUser;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;
    }

    public function handle(): void
    {
        try {
            DB::transaction(function () {
                $methodQuery = $this->queryParams->methodQuery;
                $dataQuery = $this->queryParams->toArray();

                $response = VkMethods::sendQueryVk($methodQuery, $dataQuery);

                // ✅ Успешная отправка
                if ($response->response_code === 200) {
                    $this->saveMessage($response);
                    $this->updateTopic();
                    return;
                } elseif (!empty($response->error_message)) {
                    throw new \Exception($response->error_message, 1);
                }

                throw new \Exception('SendTelegramMessageJob: неизвестная ошибка', 1);
            });
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
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $this->updateDto->messageId,
            'to_id' => $resultQuery->response,
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
        //
    }
}
