<?php

namespace App\Services\External;

use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Logging\LokiLogger;

class ExternalMessageService extends ExternalService
{
    public function __construct(ExternalMessageDto $update)
    {
        parent::__construct($update);

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
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
                throw new \Exception('Текст сообщения не найден!', 1);
            }

            $this->sendMessage();
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * @return void
     */
    protected function sendMessage(): void
    {
        $this->messageParamsDTO->text = $this->update->text;

        SendExternalTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
            $this->typeMessage,
        );
    }
}
