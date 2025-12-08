<?php

namespace App\Services\Tg;

use App\Actions\Telegram\ConversionMessageText;
use App\DTOs\TelegramUpdateDto;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Logging\LokiLogger;
use App\Models\Message;
use App\Services\ActionService\Edit\FromTgEditService;
use phpDocumentor\Reflection\Exception;

class TgEditMessageService extends FromTgEditService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->typeQuery !== 'edited_message') {
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}", 1);
            }

            if (!empty($this->update->rawData['edited_message']['photo']) ||
                !empty($this->update->rawData['edited_message']['document'])) {
                $this->editMessageCaption();
            } else {
                $this->editMessageText();
            }

            SendTelegramMessageJob::dispatch(
                $this->botUser->id,
                $this->update,
                $this->messageParamsDTO,
                $this->typeMessage,
            );
        } catch (Exception $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * Edit message
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function editMessageText(): void
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
            throw new \Exception('Сообщение не найдено!', 1);
        }

        $this->messageParamsDTO->message_id = $toIdMessage;
    }

    /**
     * Edit message with photo or document
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function editMessageCaption(): void
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
            throw new \Exception('Сообщение не найдено!', 1);
        }

        $this->messageParamsDTO->message_id = $toIdMessage;
    }
}
