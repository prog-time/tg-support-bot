<?php

namespace App\Services\External;

use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Logging\LokiLogger;

class ExternalFileService extends ExternalService
{
    public function __construct(ExternalMessageDto $update)
    {
        parent::__construct($update);

        $this->messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'sendDocument',
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
            if (empty($this->update->uploaded_file)) {
                throw new \Exception('Файл не найден!', 1);
            }

            $this->sendDocument();
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
        $this->messageParamsDTO->uploaded_file_path = $this->update->uploaded_file_path;
        $this->update->uploaded_file = null;

        SendExternalTelegramMessageJob::dispatch(
            $this->botUser,
            $this->update,
            $this->messageParamsDTO,
            'incoming',
        );
    }
}
