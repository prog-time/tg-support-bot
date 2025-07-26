<?php

namespace App\Services\VK;

use App\Actions\Telegram\SendMessage;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\Vk\VkUpdateDto;
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
        if ($this->update->type === 'message_edit') {
            if (!empty($this->update->listFileUrl)) {
                $this->editMessageCaption();
            } else {
                $this->editMessageText();
            }
        } else {
            throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
        }
    }

    /**
     * @return TelegramAnswerDto|null
     */
    protected function editMessageText(): ?TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'editMessageText';
        $this->messageParamsDTO->text = $this->update->text;

        $messageData = Message::getMessageData($this->typeMessage, $this->update->id, $this->source);
        if (empty($messageData)) {
            throw new \Exception('Сообщение не найдено!');
        }

        $this->messageParamsDTO->message_id = $messageData->to_id;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * @return TelegramAnswerDto|null
     */
    protected function editMessageCaption(): ?TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'editMessageCaption';
        $this->messageParamsDTO->caption = $this->update->text;

        $messageData = Message::getMessageData($this->typeMessage, $this->update->id, $this->source);
        if (empty($messageData)) {
            throw new \Exception('Сообщение не найдено!');
        }

        $this->messageParamsDTO->message_id = $messageData->to_id;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }
}
