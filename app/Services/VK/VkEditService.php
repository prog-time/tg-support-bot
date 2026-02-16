<?php

namespace App\Services\VK;

use App\DTOs\Vk\VkUpdateDto;
use App\Jobs\SendMessage\SendVkTelegramMessageJob;
use Illuminate\Support\Facades\Log;
use App\Models\Message;
use App\Services\ActionService\Edit\ToTgEditService;

class VkEditService extends ToTgEditService
{
    protected string $source = 'vk';

    protected string $typeMessage = 'incoming';

    public function __construct(VkUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->type !== 'message_edit') {
                throw new \Exception("Unknown event type: {$this->update->typeQuery}", 1);
            }

            if (!empty($this->update->listFileUrl)) {
                $this->editMessageCaption();
            } else {
                $this->editMessageText();
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * @return void
     */
    protected function editMessageText(): void
    {
        $this->messageParamsDTO->methodQuery = 'editMessageText';
        $this->messageParamsDTO->text = $this->update->text;

        $messageData = Message::getMessageData($this->typeMessage, $this->update->id, $this->source);
        if (empty($messageData)) {
            throw new \Exception('Message not found!', 1);
        }

        $this->messageParamsDTO->message_id = $messageData->to_id;

        SendVkTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function editMessageCaption(): void
    {
        $this->messageParamsDTO->methodQuery = 'editMessageCaption';
        $this->messageParamsDTO->caption = $this->update->text;

        $messageData = Message::getMessageData($this->typeMessage, $this->update->id, $this->source);
        if (empty($messageData)) {
            throw new \Exception('Message not found!', 1);
        }

        $this->messageParamsDTO->message_id = $messageData->to_id;

        SendVkTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }
}
