<?php

namespace App\Services\TgVk;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\Jobs\SendMessage\SendVkMessageJob;
use Illuminate\Support\Facades\Log;
use App\Models\Message;
use App\Services\ActionService\Edit\FromTgEditService;

class TgVkEditService extends FromTgEditService
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
            if ($this->update->typeQuery !== 'edited_message') {
                throw new \Exception("Unknown event type: {$this->update->typeQuery}", 1);
            }

            if (!empty($this->update->rawData['edited_message']['photo']) || !empty($this->update->rawData['edited_message']['document'])) {
                $this->editMessageCaption();
            } else {
                $this->editMessageText();
            }

            echo 'ok';
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * @return void
     */
    protected function editMessageText(): void
    {
        $dataMessage = Message::where([
            'bot_user_id' => $this->botUser->id,
            'message_type' => $this->typeMessage,
            'from_id' => $this->update->messageId,
        ])->first();

        if (empty($dataMessage)) {
            throw new \Exception('Message not found!', 1);
        }

        $queryParams = [
            'methodQuery' => 'messages.edit',
            'peer_id' => $this->botUser->chat_id,
            'message_id' => $dataMessage->to_id,
            'message' => $this->update->text,
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
    protected function editMessageCaption(): void
    {
        $this->editMessageText();
    }
}
