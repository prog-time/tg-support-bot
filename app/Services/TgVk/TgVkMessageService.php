<?php

namespace App\Services\TgVk;

use App\Actions\Telegram\GetFile;
use App\Actions\Telegram\SendMessage;
use App\Actions\VK\GetMessagesUploadServerVk;
use App\Actions\VK\SaveFileVk;
use App\Actions\VK\SendMessageVk;
use App\Actions\VK\SendQueryVk;
use App\Actions\VK\UploadFileVk;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\Vk\VkAnswerDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\Helpers\TelegramHelper;
use App\Models\Message;
use App\Services\ActionService\Send\FromTgMessageService;

class TgVkMessageService extends FromTgMessageService
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
        if ($this->update->typeQuery === 'message') {
            if (!empty($this->update->rawData['message']['photo'])) {
                $resultQuery = $this->sendPhoto();
            } elseif (!empty($this->update->rawData['message']['document'])) {
                $resultQuery = $this->sendDocument();
            } elseif (!empty($this->update->rawData['message']['sticker'])) {
                $resultQuery = $this->sendSticker();
            } elseif (!empty($this->update->rawData['message']['contact'])) {
                $resultQuery = $this->sendContact();
            } elseif (!empty($this->update->text)) {
                $resultQuery = $this->sendMessage();
            }

            if (empty($resultQuery) || !empty($resultQuery->error)) {
                throw new \Exception('Ошибка отправки запроса!');
            }

            $this->saveMessage($resultQuery);

            $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.outgoing'),
            ]));

            echo 'ok';
        } else {
            throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
        }
    }

    /**
     * Send photo
     *
     * @return VkAnswerDto
     */
    protected function sendPhoto(): VkAnswerDto
    {
        $fileData = $this->uploadFileVk($this->update->fileId, 'photo', 'photos');
        if (empty($fileData->response)) {
            throw new \Exception('Ошибка загрузки файла!');
        }
        $attachment = "photo{$fileData->response[0]['owner_id']}_{$fileData->response[0]['id']}";

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'attachment' => $attachment,
        ];
        return SendQueryVk::execute(VkTextMessageDto::from($queryParams));
    }

    /**
     * Send document
     *
     * @return VkAnswerDto
     */
    protected function sendDocument(): VkAnswerDto
    {
        $fileData = $this->uploadFileVk($this->update->fileId, 'doc', 'docs');
        if (empty($fileData->response)) {
            throw new \Exception('Ошибка загрузки файла!');
        }
        $attachment = "doc{$fileData->response['doc']['owner_id']}_{$fileData->response['doc']['id']}";

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'attachment' => $attachment,
        ];
        return SendQueryVk::execute(VkTextMessageDto::from($queryParams));
    }

    /**
     * Send location
     *
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
     *
     * @return VkAnswerDto
     */
    protected function sendVoice(): VkAnswerDto
    {
        $fileData = $this->uploadFileVk($this->update->fileId, 'audio_message', 'docs');
        if (empty($fileData->response)) {
            throw new \Exception('Ошибка загрузки файла!');
        }
        $attachment = "doc{$fileData->response['doc']['owner_id']}_{$fileData->response['doc']['id']}";

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'attachment' => $attachment,
        ];
        return SendQueryVk::execute(VkTextMessageDto::from($queryParams));
    }

    /**
     * Send sticker
     *
     * @return VkAnswerDto
     */
    protected function sendSticker(): VkAnswerDto
    {
        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'message' => $this->update->rawData['message']['sticker']['emoji'],
        ];
        return SendMessageVk::execute(VkTextMessageDto::from($queryParams));
    }

    /**
     * Send video note
     *
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
     *
     * @return VkAnswerDto
     */
    protected function sendContact(): VkAnswerDto
    {
        $contactData = $this->update->rawData['message']['contact'];

        $textMessage = "Контакт: \n";
        $textMessage .= "Имя: {$contactData['first_name']}\n";
        if (!empty($contactData['phone_number'])) {
            $textMessage .= "Телефон: {$contactData['phone_number']}\n";
        }

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'message' => $textMessage,
        ];
        return SendMessageVk::execute(VkTextMessageDto::from($queryParams));
    }

    /**
     * Send text message
     *
     * @return null|VkAnswerDto
     */
    protected function sendMessage(): ?VkAnswerDto
    {
        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'message' => $this->update->text,
        ];
        return SendMessageVk::execute(VkTextMessageDto::from($queryParams));
    }

    /**
     * Save message in DB
     *
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function saveMessage(mixed $resultQuery): void
    {
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->source,
            'message_type' => $this->typeMessage,
            'from_id' => $this->update->messageId,
            'to_id' => $resultQuery->response,
        ]);
    }

    /**
     * @param string $fileId
     * @param string $typeFile
     * @param string $typeMethod
     *
     * @return TelegramAnswerDto|VkAnswerDto|null
     *
     * @throws \Exception
     */
    protected function uploadFileVk(string $fileId, string $typeFile, string $typeMethod)
    {
        // get telegram file data
        $fileData = GetFile::execute($fileId);
        if (empty($fileData->rawData['result']['file_path'])) {
            throw new \Exception('Ошибка получения данных файла!');
        }
        $fullFilePath = TelegramHelper::getFilePublicPath($this->update->fileId);

        // get upload server data
        $resultData = GetMessagesUploadServerVk::execute($this->botUser->chat_id, $typeMethod);
        if (empty($resultData->response['upload_url'])) {
            throw new \Exception('Ошибка получения ссылки для загрузки файла!');
        }

        // upload file in VK
        $urlQuery = $resultData->response['upload_url'];
        $responseData = UploadFileVk::execute($urlQuery, $fullFilePath, $typeFile);

        // save file in VK
        return SaveFileVk::execute($typeMethod, $responseData);
    }
}
