<?php

namespace App\Services\TgVk;

use App\Actions\Vk\GetMessagesUploadServerVk;
use App\Actions\Vk\SaveFileVk;
use App\Actions\Vk\UploadFileVk;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\Vk\VkAnswerDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\Helpers\TelegramHelper;
use App\Jobs\SendMessage\SendVkMessageJob;
use App\Logging\LokiLogger;
use App\Services\ActionService\Send\FromTgMessageService;
use App\Services\Button\ButtonParser;
use App\Services\Button\KeyboardBuilder;

class TgVkMessageService extends FromTgMessageService
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
                throw new \Exception("Unknown event type: {$this->update->typeQuery}", 1);
            }

            if (!empty($this->update->rawData['message']['photo'])) {
                $this->sendPhoto();
            } elseif (!empty($this->update->rawData['message']['document'])) {
                $this->sendDocument();
            } elseif (!empty($this->update->rawData['message']['sticker'])) {
                $this->sendSticker();
            } elseif (!empty($this->update->rawData['message']['contact'])) {
                $this->sendContact();
            } elseif (!empty($this->update->text)) {
                $this->sendMessage();
            }

            echo 'ok';
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function sendPhoto(): void
    {
        $fileData = $this->uploadFileVk($this->update->fileId, 'photo', 'photos');
        if (empty($fileData->response)) {
            throw new \Exception('File upload error!', 1);
        }
        $attachment = "photo{$fileData->response[0]['owner_id']}_{$fileData->response[0]['id']}";

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'attachment' => $attachment,
        ];

        SendVkMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            VkTextMessageDto::from($queryParams),
        );
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function sendDocument(): void
    {
        $fileData = $this->uploadFileVk($this->update->fileId, 'doc', 'docs');
        if (empty($fileData->response)) {
            throw new \Exception('File upload error!', 1);
        }
        $attachment = "doc{$fileData->response['doc']['owner_id']}_{$fileData->response['doc']['id']}";

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'attachment' => $attachment,
        ];

        SendVkMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            VkTextMessageDto::from($queryParams),
        );
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
        //
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function sendVoice(): void
    {
        $fileData = $this->uploadFileVk($this->update->fileId, 'audio_message', 'docs');
        if (empty($fileData->response)) {
            throw new \Exception('File upload error!', 1);
        }
        $attachment = "doc{$fileData->response['doc']['owner_id']}_{$fileData->response['doc']['id']}";

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'attachment' => $attachment,
        ];

        SendVkMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            VkTextMessageDto::from($queryParams),
        );
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'message' => $this->update->rawData['message']['sticker']['emoji'],
        ];

        SendVkMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            VkTextMessageDto::from($queryParams),
        );
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendContact(): void
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

        SendVkMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            VkTextMessageDto::from($queryParams),
        );
    }

    /**
     * @return void
     */
    protected function sendMessage(): void
    {
        $buttonParser = new ButtonParser();
        $keyboardBuilder = new KeyboardBuilder();

        $parsedMessage = $buttonParser->parse($this->update->text);
        $keyboard = $keyboardBuilder->buildVkKeyboard($parsedMessage);

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->botUser->chat_id,
            'message' => $parsedMessage->text,
            'keyboard' => $keyboard ? json_encode($keyboard) : null,
        ];

        SendVkMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            VkTextMessageDto::from($queryParams),
        );
    }

    /**
     * @param string $fileId
     * @param string $typeFile
     * @param string $typeMethod
     *
     * @return VkAnswerDto
     */
    protected function uploadFileVk(string $fileId, string $typeFile, string $typeMethod): VkAnswerDto
    {
        try {
            $fullFilePath = TelegramHelper::getFileTelegramPath($this->update->fileId);
            if (empty($fullFilePath)) {
                throw new \Exception('Error getting file data!', 1);
            }

            $resultData = GetMessagesUploadServerVk::execute($this->botUser->chat_id, $typeMethod);
            if (empty($resultData->response['upload_url'])) {
                throw new \Exception('Error getting file upload URL!', 1);
            }

            $urlQuery = $resultData->response['upload_url'];
            $responseData = UploadFileVk::execute($urlQuery, $fullFilePath, $typeFile);
            if (empty($responseData)) {
                throw new \Exception('File upload error!', 1);
            }

            return SaveFileVk::execute($typeMethod, $responseData);
        } catch (\Throwable $e) {
            return VkAnswerDto::fromData([
                'response_code' => 500,
                'response' => 0,
                'error_message' => $e->getCode() == 1 ? $e->getMessage() : 'Request sending error',
            ]);
        }
    }
}
