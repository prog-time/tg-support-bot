<?php

namespace App\Modules\Ai\Jobs;

use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Ai\DTOs\AiRequestDto;
use App\Modules\Ai\Services\AiAssistantService;
use App\Modules\Ai\Services\AiBotApi;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAiReplyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param int               $botUserId   BotUser primary key
     * @param TelegramUpdateDto $updateDto   Parsed webhook update from the main bot
     * @param string            $userMessage Original user message text to send to AI
     */
    public function __construct(
        public readonly int $botUserId,
        public readonly TelegramUpdateDto $updateDto,
        public readonly string $userMessage,
    ) {
    }

    /**
     * Generate an AI reply and deliver it to both audiences:
     *   1. Post into the supergroup topic as the AI bot (visual marker for managers).
     *   2. Send to the user privately as the main bot.
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

            $aiRequest = new AiRequestDto(
                message: $this->userMessage,
                userId: $this->botUserId,
                platform: 'telegram',
                provider: config('ai.default_provider'),
                forceEscalation: false
            );

            $aiResponse = $aiService->processMessage($aiRequest);
            if ($aiResponse === null || trim((string) $aiResponse->response) === '') {
                throw new \RuntimeException('AI provider returned empty response', 1);
            }

            $replyText = $aiResponse->response;

            $supergroupResponse = $aiBotApi->send('sendMessage', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_thread_id' => $botUser->topic_id,
                'text' => $replyText,
                'parse_mode' => 'html',
            ]);

            if ($supergroupResponse->ok !== true) {
                throw new \RuntimeException('Telegram API error posting AI reply to supergroup: ' . json_encode((array) $supergroupResponse), 1);
            }

            AiMessage::create([
                'bot_user_id' => $botUser->id,
                'message_id' => $supergroupResponse->message_id,
                'text_ai' => $replyText,
                'text_manager' => $replyText,
            ]);

            SendTelegramMessageJob::dispatch(
                $botUser->id,
                $this->updateDto,
                TGTextMessageDto::from([
                    'methodQuery' => 'sendMessage',
                    'typeSource' => 'private',
                    'chat_id' => $botUser->chat_id,
                    'text' => $replyText,
                    'parse_mode' => 'html',
                ]),
                'outgoing',
            );

            Log::channel('loki')->info('SendAiReplyJob: AI reply delivered', [
                'source' => 'ai_reply_sent',
                'bot_user_id' => $botUser->id,
                'supergroup_message_id' => $supergroupResponse->message_id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('loki')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['source' => 'send_ai_reply_error', 'file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }
}
