<?php

namespace App\Modules\Ai\Jobs;

use App\Models\BotUser;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Services\Ai\AiAssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AiBotWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param TelegramUpdateDto $updateDto Parsed Telegram update from the AI bot webhook
     */
    public function __construct(
        public readonly TelegramUpdateDto $updateDto,
    ) {
    }

    /**
     * Process the AI bot webhook event.
     *
     * Applies ShouldAiReply filters and then either:
     * - Dispatches SendAiDraftJob (AI_AUTO_REPLY=false) — posts a draft with inline buttons
     * - Dispatches SendAiReplyJob  (AI_AUTO_REPLY=true)  — posts the reply directly
     *
     * @param ShouldAiReply      $shouldAiReply
     * @param AiAssistantService $aiService
     *
     * @return void
     */
    public function handle(ShouldAiReply $shouldAiReply, AiAssistantService $aiService): void
    {
        try {
            // Guard: AI-bot webhook is only active in telegram_group manager mode.
            if (config('app.manager_interface') === 'admin_panel') {
                return;
            }

            $botUser = BotUser::getByTopicId($this->updateDto->messageThreadId);

            if (!$shouldAiReply->shouldReply($this->updateDto, $botUser)) {
                return;
            }

            $incomingText = (string) $this->updateDto->text;

            $autoReply = (bool) config('ai.auto_reply', false);

            if ($autoReply) {
                $replyText = $aiService->generateReply(
                    $botUser->id,
                    'telegram',
                    $incomingText
                );

                if (empty($replyText)) {
                    Log::channel('loki')->warning('AI generateReply returned null, skipping auto-reply', [
                        'source' => 'ai_bot_webhook',
                        'bot_user_id' => $botUser->id,
                    ]);

                    return;
                }

                SendAiReplyJob::dispatch($botUser->id, $this->updateDto, $replyText);
            } else {
                SendAiDraftJob::dispatch($botUser->id, $this->updateDto, $incomingText);
            }
        } catch (\Throwable $e) {
            Log::channel('loki')->error($e->getMessage(), [
                'source' => 'ai_bot_webhook_error',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
