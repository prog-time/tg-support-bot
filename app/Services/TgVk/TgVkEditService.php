<?php

namespace App\Services\TgVk;

use App\Actions\VK\SendMessageVk;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\Vk\VkAnswerDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\Models\Message;
use App\Services\ActionService\Edit\FromTgEditService;
use phpDocumentor\Reflection\Exception;

class TgVkEditService extends FromTgEditService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        if ($this->update->typeQuery === 'edited_message') {
            if (!empty($this->update->rawData['edited_message']['photo']) ||
                !empty($this->update->rawData['edited_message']['document'])) {
                $this->editMessageCaption();
            } else {
                $result = $this->editMessageText();
            }
        } else {
            throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
        }
    }

    /**
     * @return VkAnswerDto|null
     */
    protected function editMessageText(): ?VkAnswerDto
    {
        try {
            $dataMessage = Message::where([
                'bot_user_id' => $this->botUser->id,
                'platform' => $this->source,
                'message_type' => $this->typeMessage,
                'from_id' => $this->update->messageId,
            ])->first();

            if (!$dataMessage) {
                throw new Exception('Сообщение не найдено!');
            }

            $queryParams = [
                'methodQuery' => 'messages.edit',
                'peer_id' => $this->botUser->chat_id,
                'message_id' => $dataMessage->to_id,
                'message' => $this->update->text,
            ];

            return SendMessageVk::execute(VkTextMessageDto::from($queryParams));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return VkAnswerDto|null
     */
    protected function editMessageCaption(): ?VkAnswerDto
    {
        return $this->editMessageText();
    }
}
