<?php

namespace App\Services\External;

use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\Message;
use App\Services\TgTopicService;
use phpDocumentor\Reflection\Exception;

class ExternalEditedMessageService extends ExternalService
{
    protected string $typeMessage = 'incoming';

    protected ExternalMessageDto $update;

    protected TgTopicService $tgTopicService;

    protected ?BotUser $botUser;

    protected ?ExternalUser $externalUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(ExternalMessageDto $update)
    {
        parent::__construct($update);

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'editTextMessage',
            'typeSource' => 'private',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
        ]);
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function handleUpdate(): void
    {
        try {
            if (empty($this->update->text)) {
                throw new \Exception('Неизвестный тип события!', 1);
            }

            $this->editMessageText();
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * @return void
     */
    private function editMessageText(): void
    {
        $externalUser = ExternalUser::where([
            'external_id' => $this->update->external_id,
        ])->first();

        if (empty($externalUser)) {
            throw new Exception('Пользователь не найден!', 1);
        }

        $messageData = Message::where([
            'message_type' => 'incoming',
            'platform' => $externalUser->source,
            'from_id' => $this->update->message_id,
        ])->first();

        $toIdMessage = $messageData->to_id ?? null;
        if (empty($toIdMessage)) {
            throw new \Exception('Сообщение не найдено!', 1);
        }

        $this->messageParamsDTO->methodQuery = 'editMessageText';
        $this->messageParamsDTO->text = $this->update->text;
        $this->messageParamsDTO->message_id = $toIdMessage;

        SendExternalTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
            $this->typeMessage,
        );
    }
}
