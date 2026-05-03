<?php

namespace App\Modules\Ai\Jobs;

use App\DTOs\Ai\AiRequestDto;
use App\Helpers\AiHelper;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Ai\Services\AiBotApi;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Services\Ai\AiAssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAiDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param int               $botUserId   BotUser primary key
     * @param TelegramUpdateDto $updateDto   Parsed webhook update
     * @param string            $userMessage Original user message text to send to AI
     */
    public function __construct(
        public readonly int $botUserId,
        public readonly TelegramUpdateDto $updateDto,
        public readonly string $userMessage,
    ) {
    }

    /**
     * Generate an AI draft and post it to the supergroup topic as the AI bot,
     * with inline "Accept / Cancel" buttons for the manager.
     *
     * @param AiBotApi           $aiBotApi
     * @param AiAssistantService $aiService
     *
     * @return void
     */
    public function handle(AiBotApi $aiBotApi, AiAssistantService $aiService): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);
            if ($botUser === null) {
                throw new \RuntimeException('BotUser not found: ' . $this->botUserId, 1);
            }

            // Generate AI draft text using the existing service
            $aiRequest = new AiRequestDto(
                message: $this->userMessage,
                userId: $this->botUserId,
                platform: 'telegram',
                provider: config('ai.default_provider'),
                forceEscalation: false
            );

            $aiResponse = $aiService->processMessage($aiRequest);
            if ($aiResponse === null) {
                throw new \RuntimeException('AI provider returned null', 1);
            }

            $draftText = AiHelper::preparedAiAnswer('', $aiResponse->response);

            // Post draft as AI bot in the supergroup topic
            $response = $aiBotApi->send('sendMessage', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $botUser->topic_id,
                'text' => $draftText,
                'parse_mode' => 'html',
            ]);

            if ($response->ok !== true) {
                throw new \RuntimeException('Telegram API error sending draft: ' . json_encode((array) $response), 1);
            }

            // Persist the draft record
            $aiMessage = AiMessage::create([
                'bot_user_id' => $botUser->id,
                'message_id' => $response->message_id,
                'text_ai' => $aiResponse->response,
                'text_manager' => '',
            ]);

            // Edit the message to add inline buttons
            $aiBotApi->send('editMessageReplyMarkup', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $botUser->topic_id,
                'message_id' => $response->message_id,
                'reply_markup' => AiHelper::preparedAiReplyMarkup((int) $aiMessage->message_id, $aiResponse->response),
            ]);
        } catch (\Throwable $e) {
            Log::channel('loki')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['source' => 'send_ai_draft_error', 'file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }
}
