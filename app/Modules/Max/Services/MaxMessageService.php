<?php

namespace App\Modules\Max\Services;

use App\Models\BotUser;
use App\Modules\Max\DTOs\MaxUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendMaxTelegramMessageJob;
use App\Modules\Telegram\Services\ActionService\Send\ToTgMessageService;
use Illuminate\Support\Facades\Log;


class MaxMessageService extends ToTgMessageService
{
    protected string $source = 'max';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(MaxUpdateDto $update)
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
            if ($this->update->type !== 'message_created') {
                throw new \Exception('Unknown event type', 1);
            }

            Log::channel('loki')->info('MaxMessageService: incoming update', [
                'text'            => $this->update->text,
                'listFileUrl'     => $this->update->listFileUrl,
                'listAttachments' => $this->update->listAttachments,
                'rawAttachments'  => $this->update->rawData['message']['body']['attachments'] ?? [],
            ]);

            if (!empty($this->update->listFileUrl)) {
                $this->sendDocument();
            } elseif (!empty($this->update->text)) {
                $this->sendMessage();
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendDocument';
        $this->messageParamsDTO->document = $this->update->listFileUrl[0];
        $this->messageParamsDTO->caption = $this->update->text ?? '';

        Log::channel('loki')->info('MaxMessageService: sendDocument', [
            'document' => $this->messageParamsDTO->document,
            'caption'  => $this->messageParamsDTO->caption,
        ]);

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendMessage(): void
    {
        $this->messageParamsDTO->text = $this->update->text;

        SendMaxTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
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
    protected function sendContact(): void
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
     * @return void
     */
    protected function sendLocation(): void
    {
        //
    }
}
