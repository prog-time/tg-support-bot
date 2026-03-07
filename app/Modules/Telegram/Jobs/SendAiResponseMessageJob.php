<?php

namespace App\Modules\Telegram\Jobs;

use App\DTOs\Ai\AiRequestDto;
use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Services\Ai\AiAssistantService;
use Illuminate\Support\Facades\Log;

class SendAiResponseMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    /** @var TelegramUpdateDto */
    public mixed $updateDto;

    public string $typeMessage = 'incoming';

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
    }

    public function handle(?AiAssistantService $aiService = null): void
    {
        $aiService ??= app(AiAssistantService::class);

        try {
            $botUser = BotUser::find($this->botUserId);

            $managerTextMessage = trim(str_replace('/ai_generate', '', $this->updateDto->text));
            if (empty($managerTextMessage)) {
                throw new \Exception('Message is empty!', 1);
            }

            $aiRequest = new AiRequestDto(
                message: $managerTextMessage,
                userId: $this->botUserId,
                platform: 'telegram',
                provider: config('ai.default_provider'),
                forceEscalation: false
            );

            $aiResponse = $aiService->processMessage($aiRequest);

            if (empty($aiResponse)) {
                throw new \Exception('Failed to send request to AI!', 1);
            }
            SendAiTelegramMessageJob::dispatch(
                $botUser->id,
                $this->updateDto,
                $managerTextMessage,
                $aiResponse->response
            );
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * Save message to database after successful sending.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }

    /**
     * Edit message in database.
     *
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
