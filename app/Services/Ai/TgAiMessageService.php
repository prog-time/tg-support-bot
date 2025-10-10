<?php

namespace App\Services\Ai;

use App\DTOs\TelegramUpdateDto;
use App\Helpers\DateHelper;
use App\Helpers\TelegramHelper;
use App\Jobs\AiQuery;
use App\Models\AiCondition;
use App\Models\Message;
use App\Services\ActionService\Send\FromTgMessageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Exception;

class TgAiMessageService extends FromTgMessageService
{
    /**
     * @param TelegramUpdateDto $update
     *
     * @throws Exception
     */
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return bool
     */
    public function handleUpdate(): bool
    {
        try {
            if ($this->update->typeQuery !== 'message') {
                throw new \Exception("Неизвестный тип события: {$this->update->typeQuery}");
            }

            if (!$this->shouldUseAiAssistant()) {
                throw new \Exception('Запрещено создание сообщения от AI');
            }

            AiQuery::dispatch($this->update);

            return true;
        } catch (\Exception $e) {
            Log::info('Webhook sent', ['exception' => $e]);

            return false;
        }
    }

    /**
     * Проверить, должен ли AI-помощник обрабатывать сообщение.
     *
     * @return bool
     */
    private function shouldUseAiAssistant(): bool
    {
        try {
            $shouldUseAi = false;
            if (!empty($this->update->text)) {
                $aiCondition = $this->botUser->aiCondition;
                $lastMessage = $this->botUser->lastMessageManager;

                if ($aiCondition) {
                    if ($aiCondition->active === false) {
                        $disableTimeout = !empty(config('ai.disable_timeout')) ? (int)config('ai.disable_timeout') : 7200;
                        $shouldUseAi = DateHelper::isIntervalExceeded($lastMessage->updated_at, Carbon::now(), $disableTimeout);
                    } else {
                        $shouldUseAi = true;
                    }
                } else {
                    $shouldUseAi = true;
                    AiCondition::create([
                        'bot_user_id' => $this->botUser->id,
                        'active' => true,
                    ]);
                }

                if ($shouldUseAi) {
                    AiCondition::where([
                        'bot_user_id' => $this->botUser->id,
                    ])->update([
                        'active' => true,
                    ]);
                }
            }

            return $shouldUseAi;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return array
     */
    protected function sendPhoto(): array
    {
        return [];
    }

    /**
     * @return mixed
     */
    protected function sendSticker(): mixed
    {
        return null;
    }

    /**
     * @return array
     */
    protected function sendLocation(): array
    {
        return [
            'location' => $this->update->location,
        ];
    }

    /**
     * @return mixed
     */
    protected function sendMessage(): mixed
    {
        return null;
    }

    /**
     * @return string[]
     */
    protected function sendContact(): array
    {
        $contactData = $this->update->rawData['message']['contact'];

        $textMessage = "Контакт: \n";
        if (!empty($contactData['first_name'])) {
            $textMessage .= "Имя: {$contactData['first_name']}\n";
        }

        if (!empty($contactData['phone_number'])) {
            $textMessage .= "Телефон: {$contactData['phone_number']}\n";
        }

        return [
            'text' => $textMessage,
        ];
    }

    /**
     * @return mixed
     */
    protected function sendDocument(): mixed
    {
        try {
            return [
                'file_id' => $this->update->fileId,
                'file_path' => TelegramHelper::getFilePublicPath($this->update->fileId),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return mixed
     */
    protected function sendVideoNote(): mixed
    {
        return null;
    }

    /**
     * @return mixed
     */
    protected function sendVoice(): mixed
    {
        return null;
    }

    /**
     * @param mixed $resultQuery
     *
     * @return Message
     */
    protected function saveMessage(mixed $resultQuery): Message
    {
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->externalUser->source,
            'message_type' => 'outgoing',
            'from_id' => $resultQuery->fromId,
            'to_id' => $resultQuery->toId,
        ]);

        $message->externalMessage()->create([
            'text' => $resultQuery->text ?? null,
            'file_id' => $resultQuery->fileId ?? null,
        ]);

        return $message;
    }
}
