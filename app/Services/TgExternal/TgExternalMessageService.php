<?php

namespace App\Services\TgExternal;

use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageResponseDto;
use App\DTOs\Redis\WebhookMessageDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Helpers\TelegramHelper;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Jobs\SendWebhookMessage;
use App\Logging\LokiLogger;
use App\Models\Message;
use App\Services\ActionService\Send\FromTgMessageService;
use App\Services\Button\ButtonParser;

class TgExternalMessageService extends FromTgMessageService
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
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
            }

            $rawText = $this->update->text ?? $this->update->caption;
            $buttonParser = new ButtonParser();
            $parsedMessage = $buttonParser->parse($rawText ?? '');

            $buttons = null;
            if ($parsedMessage->hasButtons()) {
                $buttons = array_map(
                    fn ($button) => [
                        'text' => $button->text,
                        'type' => $button->type->value,
                        'value' => $button->value,
                        'row' => $button->row,
                    ],
                    $parsedMessage->buttons
                );
            }

            $resultData = [
                'source' => $this->botUser->externalUser->source,
                'external_id' => $this->botUser->externalUser->external_id,
                'message' => [
                    'content_type' => 'text',
                    'message_type' => 'outgoing',
                    'to_id' => time(),
                    'from_id' => $this->update->messageId,
                    'text' => $parsedMessage->text,
                    'date' => date('d.m.Y H:i'),
                    'file_url' => null,
                    'file_id' => null,
                    'file_type' => null,
                    'buttons' => $buttons,
                ],
            ];

            if (!empty($this->update->fileId)) {
                if (!empty($this->update->rawData['message']['photo'])) {
                    $fileType = 'photo';
                } else {
                    $fileType = 'document';
                }

                $resultData['message'] = array_merge($resultData['message'], [
                    'content_type' => 'file',
                    'file_id' => $this->update->fileId,
                    'file_url' => TelegramHelper::getFilePublicPath($this->update->fileId),
                    'file_type' => $fileType,
                ]);
            } elseif (!empty($this->update->rawData['message']['location'])) {
                $resultData['message'] = array_merge($resultData['message'], [
                    'location' => $this->update->location,
                ]);
            } elseif (!empty($this->update->rawData['message']['contact'])) {
                $contactData = $this->update->rawData['message']['contact'];

                $textMessage = "Контакт: \n";
                if (!empty($contactData['first_name'])) {
                    $textMessage .= "Имя: {$contactData['first_name']}\n";
                }

                if (!empty($contactData['phone_number'])) {
                    $textMessage .= "Телефон: {$contactData['phone_number']}\n";
                }

                $resultData['message'] = array_merge($resultData['message'], [
                    'text' => $textMessage,
                ]);
            }

            $webhookUrl = $this->botUser->externalUser->externalSource->webhook_url;
            $messageData = WebhookMessageDto::fromArray($resultData);
            $saveMessageData = $this->saveMessage($messageData);
            if (!empty($webhookUrl)) {
                SendWebhookMessage::dispatch($webhookUrl, [
                    'type_query' => 'send_message',
                    'externalId' => $messageData->externalId,
                    'message' => $saveMessageData->result->toArray(),
                ]);
            }

            SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
                'methodQuery' => 'editForumTopic',
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $this->botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.outgoing'),
            ]));
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * @return void
     */
    protected function sendPhoto(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
        //
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
     */
    protected function sendMessage(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
        //
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
    protected function sendVoice(): void
    {
        //
    }

    /**
     * @param WebhookMessageDto $resultQuery
     *
     * @return ExternalMessageAnswerDto
     */
    protected function saveMessage(WebhookMessageDto $resultQuery): ExternalMessageAnswerDto
    {
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $resultQuery->message->from_id,
            'to_id' => $resultQuery->message->to_id,
        ]);

        $message->externalMessage()->create([
            'text' => $resultQuery->message->text ?? null,
            'file_id' => $resultQuery->message->file_id ?? null,
            'file_type' => $resultQuery->message->file_type ?? null,
        ]);

        return ExternalMessageAnswerDto::from([
            'status' => true,
            'result' => ExternalMessageResponseDto::from([
                'message_type' => 'outgoing',
                'to_id' => $message->to_id,
                'from_id' => $message->from_id,
                'text' => $message->externalMessage->text,
                'date' => $message->created_at->format('d.m.Y H:i:s'),
                'content_type' => $message->file_type ?? 'text',
                'file_id' => $message->externalMessage->file_id,
                'file_url' => $message->externalMessage->file_url,
                'file_type' => $message->externalMessage->file_type,
                'buttons' => $resultQuery->message->buttons,
            ]),
        ]);
    }
}
