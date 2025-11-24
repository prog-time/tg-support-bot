<?php

namespace App\Services\Tg;

use App\Actions\Telegram\ConversionMessageText;
use App\Actions\Telegram\SendMessage;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\Models\Message;
use App\Services\ActionService\Edit\FromTgEditService;

class TgEditMessageService extends FromTgEditService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): mixed
    {
        if ($this->update->typeQuery === 'edited_message') {
            if (!empty($this->update->rawData['edited_message']['photo']) ||
                !empty($this->update->rawData['edited_message']['document'])) {
                $this->editMessageCaption();
            } else {
                $this->editMessageText();
            }

            return '';
        }

        throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
    }

    /**
     * Edit message
     * @return TelegramAnswerDto|null
     */
    protected function editMessageText(): ?TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'editMessageText';

        $this->messageParamsDTO->text = $this->update->text;
        if (!empty($this->update->entities)) {
            $this->messageParamsDTO->text = ConversionMessageText::conversionMarkdownFormat($this->update->text, $this->update->entities);
            $this->messageParamsDTO->parse_mode = 'MarkdownV2';
        }

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
    protected function editMessageCaption(): ?TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'editMessageCaption';

        $this->messageParamsDTO->caption = $this->update->caption;
        if (!empty($this->update->entities)) {
            $this->messageParamsDTO->caption = ConversionMessageText::conversionMarkdownFormat($this->update->caption, $this->update->entities);
            $this->messageParamsDTO->parse_mode = 'MarkdownV2';
        }

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
