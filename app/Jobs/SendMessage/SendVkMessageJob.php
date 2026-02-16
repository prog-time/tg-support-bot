<?php

namespace App\Jobs\SendMessage;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\Vk\VkTextMessageDto;
use Illuminate\Support\Facades\Log;
use App\Models\BotUser;
use App\Models\Message;
use App\VkBot\VkMethods;

class SendVkMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = 'outgoing';

    private mixed $vkMethods;

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
        VkTextMessageDto $queryParams,
        mixed $vkMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;

        $this->vkMethods = $vkMethods ?? new VkMethods();
    }

    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);

            $methodQuery = $this->queryParams->methodQuery;
            $dataQuery = $this->queryParams->toArray();

            $response = $this->vkMethods->sendQueryVk($methodQuery, $dataQuery);

            if ($response->response_code === 200) {
                $this->saveMessage($botUser, $response);
                $this->updateTopic($botUser, $this->typeMessage);
                return;
            } elseif (!empty($response->error_message)) {
                throw new \Exception($response->error_message, 1);
            }

            throw new \Exception('SendVkMessageJob: unknown error', 1);
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
        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $this->updateDto->messageId,
            'to_id' => $resultQuery->response,
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
        //
    }
}
