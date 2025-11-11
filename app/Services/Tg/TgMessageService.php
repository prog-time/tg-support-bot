<?php

namespace App\Services\Tg;

use App\Actions\Telegram\ConversionMessageText;
use App\DTOs\TelegramUpdateDto;
use App\Jobs\SendTelegramMessageJob;
use App\Logging\LokiLogger;
use App\Services\ActionService\Send\FromTgMessageService;

class TgMessageService extends FromTgMessageService
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
        try {
            if ($this->update->typeQuery !== 'message') {
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}", 1);
            }

            if (!empty($this->update->rawData['message']['photo'])) {
                $this->sendPhoto();
            } elseif (!empty($this->update->rawData['message']['document'])) {
                $this->sendDocument();
            } elseif (!empty($this->update->rawData['message']['location'])) {
                $this->sendLocation();
            } elseif (!empty($this->update->rawData['message']['voice'])) {
                $this->sendVoice();
            } elseif (!empty($this->update->rawData['message']['sticker'])) {
                $this->sendSticker();
            } elseif (!empty($this->update->rawData['message']['video_note'])) {
                $this->sendVideoNote();
            } elseif (!empty($this->update->rawData['message']['contact'])) {
                $this->sendContact();
            } elseif (!empty($this->update->text)) {
                $this->sendMessage();
            }

            SendTelegramMessageJob::dispatch(
                $this->botUser,
                $this->update,
                $this->messageParamsDTO,
                $this->typeMessage,
            );
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * @return void
     */
    protected function sendPhoto(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendPhoto';
        $this->messageParamsDTO->photo = $this->update->fileId;

        $this->messageParamsDTO->caption = $this->update->caption;
        if (!empty($this->update->entities)) {
            $this->messageParamsDTO->caption = ConversionMessageText::conversionMarkdownFormat($this->update->caption, $this->update->entities);
            $this->messageParamsDTO->parse_mode = 'MarkdownV2';
        }
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';
        $this->messageParamsDTO->document = $this->update->fileId;

        $this->messageParamsDTO->caption = $this->update->caption;
        if (!empty($this->update->entities)) {
            $this->messageParamsDTO->caption = ConversionMessageText::conversionMarkdownFormat($this->update->caption, $this->update->entities);
            $this->messageParamsDTO->parse_mode = 'MarkdownV2';
        }
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendLocation';
        $this->messageParamsDTO->latitude = $this->update->location['latitude'];
        $this->messageParamsDTO->longitude = $this->update->location['longitude'];
    }

    /**
     * @return void
     */
    protected function sendVoice(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendVoice';
        $this->messageParamsDTO->voice = $this->update->fileId;
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendSticker';
        $this->messageParamsDTO->sticker = $this->update->fileId;
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendVideoNote';
        $this->messageParamsDTO->video_note = $this->update->fileId;
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendMessage';
        $contactData = $this->update->rawData['message']['contact'];

        $textMessage = "Контакт: \n";
        $textMessage .= "Имя: {$contactData['first_name']}\n";
        if (!empty($contactData['phone_number'])) {
            $textMessage .= "Телефон: {$contactData['phone_number']}\n";
        }

        $this->messageParamsDTO->text = $textMessage;
    }

    /**
     * @return void
     */
    protected function sendMessage(): void
    {
        $this->messageParamsDTO->text = $this->update->text;
        if (!empty($this->update->entities)) {
            $this->messageParamsDTO->text = ConversionMessageText::conversionMarkdownFormat($this->update->text, $this->update->entities);
            $this->messageParamsDTO->parse_mode = 'MarkdownV2';
        }
    }
}
