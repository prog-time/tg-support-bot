<?php

namespace App\Services\VK;

use App\DTOs\Vk\VkUpdateDto;
use App\Jobs\SendVkTelegramMessageJob;
use App\Logging\LokiLogger;
use App\Models\Message;
use App\Services\ActionService\Edit\ToTgEditService;

class VkEditService extends ToTgEditService
{
    protected string $source = 'vk';

    protected string $typeMessage = 'incoming';

    public function __construct(VkUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->type !== 'message_edit') {
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
            }

            if (!empty($this->update->listFileUrl)) {
                $this->editMessageCaption();
            } else {
                $this->editMessageText();
            }
        } catch (\Exception $e) {
            $logger = new LokiLogger();
            $logger->log('api_request', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
        }
    }

    /**
     * @return void
     */
    protected function editMessageText(): void
    {
        $this->messageParamsDTO->methodQuery = 'editMessageText';
        $this->messageParamsDTO->text = $this->update->text;

        $messageData = Message::getMessageData($this->typeMessage, $this->update->id, $this->source);
        if (empty($messageData)) {
            throw new \Exception('Сообщение не найдено!');
        }

        $this->messageParamsDTO->message_id = $messageData->to_id;

        SendVkTelegramMessageJob::dispatch(
            $this->botUser,
            $this->update,
            $this->messageParamsDTO,
            $this->typeMessage,
        );
    }

    /**
     * @return void
     */
    protected function editMessageCaption(): void
    {
        $this->messageParamsDTO->methodQuery = 'editMessageCaption';
        $this->messageParamsDTO->caption = $this->update->text;

        $messageData = Message::getMessageData($this->typeMessage, $this->update->id, $this->source);
        if (empty($messageData)) {
            throw new \Exception('Сообщение не найдено!');
        }

        $this->messageParamsDTO->message_id = $messageData->to_id;

        SendVkTelegramMessageJob::dispatch(
            $this->botUser,
            $this->update,
            $this->messageParamsDTO,
            $this->typeMessage,
        );
    }
}
