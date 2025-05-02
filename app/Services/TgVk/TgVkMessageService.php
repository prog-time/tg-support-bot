<?php

namespace App\Services\TgVk;

use App\Services\TgService;
use App\Actions\Telegram\ConversionMessageText;
use App\Actions\Telegram\SendMessage;
use App\Actions\VK\SendMessageVk;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TelegramUpdateDto;
use App\Models\Message;
use App\DTOs\VK\VkTextMessageDto;

class TgVkMessageService extends TgService
{
    public function __construct(TelegramUpdateDto $update) {
        parent::__construct($update);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function handleUpdate()
    {
        if ($this->update->typeQuery === 'message') {
            if (!empty($this->update->rawData['message']['photo'])) {
                $resultQuery = $this->sendPhoto();
            } elseif (!empty($this->update->rawData['message']['document'])) {
                $resultQuery = $this->sendDocument();
            } elseif (!empty($this->update->rawData['message']['location'])) {
                $resultQuery = $this->sendLocation();
            } elseif (!empty($this->update->rawData['message']['voice'])) {
                $resultQuery = $this->sendVoice();
            } elseif (!empty($this->update->rawData['message']['sticker'])) {
                $resultQuery = $this->sendSticker();
            } elseif (!empty($this->update->rawData['message']['video_note'])) {
                $resultQuery = $this->sendVideoNote();
            } elseif (!empty($this->update->rawData['message']['contact'])) {
                $resultQuery = $this->sendContact();
            } elseif (!empty($this->update->text)) {
                $resultQuery = $this->sendMessage();
            }

            if (empty($resultQuery->ok)) {
                throw new \Exception("Ошибка отправки запроса!");
            }

            $this->saveMessage($resultQuery);

            $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.outgoing'),
            ]));
        } else {
            throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
        }
    }

    /**
     * Send photo
     * @return TelegramAnswerDto
     */
    protected function sendPhoto(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendPhoto';
        $this->messageParamsDTO->photo = $this->update->fileId;

        $this->messageParamsDTO->caption = $this->update->caption;
        if (!empty($this->update->entities)) {
            $this->messageParamsDTO->caption = ConversionMessageText::conversionMarkdownFormat($this->update->caption, $this->update->entities);
            $this->messageParamsDTO->parse_mode = 'MarkdownV2';
        }
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send document
     * @return TelegramAnswerDto
     */
    protected function sendDocument(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';
        $this->messageParamsDTO->document = $this->update->fileId;

        $this->messageParamsDTO->caption = $this->update->caption;
        if (!empty($this->update->entities)) {
            $this->messageParamsDTO->caption = ConversionMessageText::conversionMarkdownFormat($this->update->caption, $this->update->entities);
            $this->messageParamsDTO->parse_mode = 'MarkdownV2';
        }
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send location
     * @return TelegramAnswerDto
     */
    protected function sendLocation(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendLocation';
        $this->messageParamsDTO->latitude = $this->update->location['latitude'];
        $this->messageParamsDTO->longitude = $this->update->location['longitude'];
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send voice
     * @return TelegramAnswerDto
     */
    protected function sendVoice(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendVoice';
        $this->messageParamsDTO->voice = $this->update->fileId;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send sticker
     * @return TelegramAnswerDto
     */
    protected function sendSticker(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendSticker';
        $this->messageParamsDTO->sticker = $this->update->fileId;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send video note
     * @return TelegramAnswerDto
     */
    protected function sendVideoNote(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendVideoNote';
        $this->messageParamsDTO->video_note = $this->update->fileId;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send contact info
     * @return TelegramAnswerDto
     */
    protected function sendContact(): TelegramAnswerDto
    {
        $this->messageParamsDTO->methodQuery = 'sendMessage';
        $contactData = $this->update->rawData['message']['contact'];

        $textMessage = "Контакт: \n";
        $textMessage .= "Имя: {$contactData['first_name']}\n";
        if (!empty($contactData['phone_number'])) {
            $textMessage .= "Телефон: {$contactData['phone_number']}\n";
        }

        $this->messageParamsDTO->text = $textMessage;
        return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Send text message
     * @return TelegramAnswerDto
     */
    protected function sendMessage()
    {
        
        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'message' => $this->update->text,
        ];

        SendMessageVk::execute(VkTextMessageDto::from($queryParams));

        // $this->messageParamsDTO->text = $this->update->text;
        // if (!empty($this->update->entities)) {
        //     $this->messageParamsDTO->text = ConversionMessageText::conversionMarkdownFormat($this->update->text, $this->update->entities);
        //     $this->messageParamsDTO->parse_mode = 'MarkdownV2';
        // }
        // return SendMessage::execute($this->botUser, $this->messageParamsDTO);
    }

    /**
     * Save message in DB
     * @param TelegramAnswerDto $resultQuery
     * @return void
     */
    protected function saveMessage(TelegramAnswerDto $resultQuery): void
    {
        Message::create(
            [
                'bot_user_id' => $this->botUser->id,
                'platform' => $this->source,
                'message_type' => $this->typeMessage,
                'from_id' => $this->update->messageId,
                'to_id' => $resultQuery->message_id,
            ]
        );
    }

}
