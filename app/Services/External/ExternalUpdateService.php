<?php

namespace App\Services\External;

use App\Actions\Telegram\GetFile;
use App\DTOs\Redis\WebhookMessageDto;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Helpers\TelegramHelper;
use App\Jobs\SendWebhookMessage;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgTopicService;
use phpDocumentor\Reflection\Exception;

class ExternalUpdateService
{
    protected string $typeMessage = '';

    protected TelegramUpdateDto $update;

    protected TgTopicService $tgTopicService;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(TelegramUpdateDto $update)
    {
        $this->update = $update;
        $this->tgTopicService = new TgTopicService();
        $this->botUser = BotUser::getTelegramUserData($this->update);

        if (empty($this->botUser)) {
            throw new Exception('Пользователя не существует!');
        }

        switch ($update->typeSource) {
            case 'private':
                $this->typeMessage = 'incoming';
                $queryParams = [
                    'chat_id' => config('traffic_source.settings.telegram.group_id'),
                    'message_thread_id' => $this->botUser->topic_id,
                ];
                break;

            case 'supergroup':
                $this->typeMessage = 'outgoing';
                $queryParams = [
                    'chat_id' => $this->botUser->chat_id,
                ];
                break;

            default:
                throw new Exception('Данный тип запроса не поддерживается!');
        }

        $queryParams['methodQuery'] = 'sendMessage';
        $queryParams['typeSource'] = $update->typeSource;
        $this->messageParamsDTO = TGTextMessageDto::from($queryParams);
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function handleUpdate(): void
    {
        if ($this->update->typeQuery === 'message') {
            $fullFilePath = '';
            $dopMessageParams = [];
            if (!empty($this->update->fileId)) {
                $fileData = GetFile::execute($this->update->fileId);
                if (!empty($fileData->rawData['result']['file_path'])) {
                    $localFilePath = $fileData->rawData['result']['file_path'];
                    $fullFilePath = TelegramHelper::getFilePath($localFilePath);
                }
            }

            if (!empty($this->update->rawData['message']['location'])) {
                $dopMessageParams['location'] = [
                    'latitude' => $this->update->location['latitude'],
                    'longitude' => $this->update->location['longitude'],
                ];
            }

            if (!empty($this->update->rawData['message']['contact'])) {
                $contactData = $this->update->rawData['message']['contact'];

                $textMessage = "Контакт: \n";
                if (!empty($contactData['first_name'])) {
                    $textMessage .= "Имя: {$contactData['first_name']}\n";
                }

                if (!empty($contactData['phone_number'])) {
                    $textMessage .= "Телефон: {$contactData['phone_number']}\n";
                }
                $this->update->text = $textMessage;
            }

            $messageData = WebhookMessageDto::fromArray([
                'source' => $this->botUser->externalUser->source,
                'external_id' => $this->botUser->externalUser->external_id,
                'message_type' => 'outgoing',

                'to_id' => time(),
                'from_id' => $this->update->messageId,

                'dop_params' => $dopMessageParams,

                'text' => $this->update->text,
                'file_path' => $fullFilePath,
                'date' => date('d.m.Y H:i'),
            ]);
            $this->saveMessage($messageData);

            $webhookUrl = $this->botUser->externalUser->externalSource->webhook_url;
            if (!empty($webhookUrl)) {
                SendWebhookMessage::dispatch($webhookUrl, $messageData);
            }

            $this->tgTopicService->editTgTopic(TelegramTopicDto::fromData([
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.outgoing'),
            ]));
        } else {
            throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
        }
    }

    /**
     * Save message in DB
     *
     * @param WebhookMessageDto $resultQuery
     *
     * @return void
     */
    protected function saveMessage(WebhookMessageDto $resultQuery): void
    {
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $resultQuery->fromId,
            'to_id' => $resultQuery->toId,
        ]);
    }
}
