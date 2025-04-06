<?php

namespace App\Services;

use App\Actions\Telegram\SendMessage;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\Models\Message;

class TgEditedMessageService extends TgService
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
                $this->editMessageText();
            }

        } else {
            throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
        }
    }

    /**
     * Edit message
     * @return TelegramAnswerDto|null
     */
    private function editMessageText(): ?TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'editMessageText';
        $this->messageParamsDTO->text = $this->update->text;

        $messageData = Message::where([
            'message_type' => $this->typeMessage,
            'from_id' => $this->update->messageId,
        ])->first();

        $toIdMessage = $messageData->to_id ?? null;
        if (empty($toIdMessage)) {
            throw new \Exception('Сообщение не найдено!');
        }

        $this->messageParamsDTO->message_id = $toIdMessage;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Edit message with photo or document
     * @return TelegramAnswerDto|null
     */
    private function editMessageCaption(): ?TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'editMessageCaption';
        $this->messageParamsDTO->caption = $this->update->caption;

        $messageData = Message::where([
            'message_type' => $this->typeMessage,
            'from_id' => $this->update->messageId,
        ])->first();

        $toIdMessage = $messageData->to_id ?? null;
        if (empty($toIdMessage)) {
            throw new \Exception('Сообщение не найдено!');
        }

        $this->messageParamsDTO->message_id = $toIdMessage;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

}
