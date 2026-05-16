<?php

namespace App\Modules\Ai\Jobs;

use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Ai\Actions\DeliverAiAnswerToUser;
use App\Modules\Ai\DTOs\AiRequestDto;
use App\Modules\Ai\Services\AiAssistantService;
use App\Modules\Ai\Services\AiBotApi;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
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
     * @param int                    $botUserId   BotUser primary key
     * @param TelegramUpdateDto|null $updateDto   Parsed webhook update; null when AI is triggered
     *                                            from a non-Telegram source (e.g. VK/Max).
     * @param string                 $userMessage Original user message text to send to AI
     */
    public function __construct(
        public readonly int $botUserId,
        public readonly ?TelegramUpdateDto $updateDto,
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

            // For brand-new VK/Max users the supergroup topic may still be in flight;
            // wait for it before we try to post the AI reply marker into thread_id=null.
            if (empty($botUser->topic_id)) {
                Log::channel('loki')->info('SendAiReplyJob: topic_id not ready, releasing', [
                    'source' => 'send_ai_reply_topic_pending',
                    'bot_user_id' => $botUser->id,
                    'platform' => $botUser->platform,
                ]);
                $this->release(5);
                return;
            }

            $aiRequest = new AiRequestDto(
                message: $this->userMessage,
                userId: $this->botUserId,
                platform: $botUser->platform ?? 'telegram',
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

            $delivered = app(DeliverAiAnswerToUser::class)->execute($botUser, $replyText, $this->updateDto);
            if (!$delivered) {
                throw new \RuntimeException('AI auto-reply delivery skipped: unsupported platform', 1);
            }

            Log::channel('loki')->info('SendAiReplyJob: AI reply delivered', [
                'source' => 'ai_reply_sent',
                'bot_user_id' => $botUser->id,
                'platform' => $botUser->platform,
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
