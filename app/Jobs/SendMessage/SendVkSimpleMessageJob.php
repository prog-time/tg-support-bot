<?php

namespace App\Jobs\SendMessage;

use App\DTOs\Vk\VkTextMessageDto;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\VkBot\VkMethods;

class SendVkSimpleMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = 'outgoing';

    private mixed $vkMethods;

    public function __construct(
        VkTextMessageDto $queryParams,
        mixed $vkMethods = null,
    ) {
        $this->queryParams = $queryParams;

        $this->vkMethods = $vkMethods ?? new VkMethods();
    }

    public function handle(): void
    {
        try {
            $methodQuery = $this->queryParams->methodQuery;
            $dataQuery = $this->queryParams->toArray();

            $this->vkMethods->sendQueryVk($methodQuery, $dataQuery);

            throw new \Exception('SendVkMessageJob: неизвестная ошибка', 1);
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
        //
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
        //
    }
}
