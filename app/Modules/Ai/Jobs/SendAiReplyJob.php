<?php

namespace App\Modules\Ai\Jobs;

use App\Helpers\AiHelper;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Admin\Services\ChannelStatusService;
use App\Modules\Ai\Actions\DeliverAiAnswerToUser;
use App\Modules\Ai\DTOs\AiRequestDto;
use App\Modules\Ai\Services\AiAssistantService;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Services\Settings\SettingsService;
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
     * Generate an AI reply, post it to the supergroup topic when the group is
     * configured, and deliver it to the user. A failed topic post never blocks
     * the delivery.
     *
     * @param AiAssistantService $aiService
     *
     * @return void
     */
    public function handle(AiAssistantService $aiService): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);
            if ($botUser === null) {
                throw new \RuntimeException('BotUser not found: ' . $this->botUserId, 1);
            }

            $groupId = (string) app(SettingsService::class)->get('telegram.group_id');
            $groupConfigured = $groupId !== '' && app(ChannelStatusService::class)->telegram()['connected'];

            if ($groupConfigured && empty($botUser->topic_id) && $this->canWaitForTopic()) {
                Log::channel('app')->info('SendAiReplyJob: topic_id not ready, releasing', [
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
                provider: (string) app(SettingsService::class)->get('ai.default_provider'),
                forceEscalation: false
            );

            $aiResponse = $aiService->processMessage($aiRequest);
            if ($aiResponse === null || trim((string) $aiResponse->response) === '') {
                throw new \RuntimeException('AI provider returned empty response', 1);
            }

            $replyText = $aiResponse->response;

            $groupMessageId = $groupConfigured && !empty($botUser->topic_id)
                ? $this->postReplyToSupergroup($botUser, $replyText, $groupId)
                : null;

            AiMessage::create([
                'bot_user_id' => $botUser->id,
                'message_id' => $groupMessageId,
                'text_ai' => $replyText,
                'text_manager' => $replyText,
                'status' => AiMessage::STATUS_ACCEPTED,
            ]);

            $delivered = app(DeliverAiAnswerToUser::class)->execute($botUser, $replyText, $this->updateDto);
            if (!$delivered) {
                throw new \RuntimeException('AI auto-reply delivery skipped: unsupported platform', 1);
            }

            Log::channel('app')->info('SendAiReplyJob: AI reply delivered', [
                'source' => 'ai_reply_sent',
                'bot_user_id' => $botUser->id,
                'platform' => $botUser->platform,
            ]);
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['source' => 'send_ai_reply_error', 'file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * Whether a missing topic is worth another attempt: a sync queue never
     * re-runs a released job, and the last attempt must still deliver the reply.
     *
     * @return bool
     */
    private function canWaitForTopic(): bool
    {
        return $this->attempts() < $this->tries && $this->job?->getConnectionName() !== 'sync';
    }

    /**
     * Post the reply to the topic. Only the code and error type are logged on
     * failure: the raw answer of a network failure contains the bot token.
     *
     * @param BotUser $botUser
     * @param string  $replyText
     * @param string  $groupId
     *
     * @return int|null Telegram message id, null when the post failed
     */
    private function postReplyToSupergroup(BotUser $botUser, string $replyText, string $groupId): ?int
    {
        $response = TelegramMethods::sendQueryTelegram('sendMessage', [
            'chat_id' => $groupId,
            'message_thread_id' => $botUser->topic_id,
            'text' => AiHelper::preparedAiAnswer('', $replyText),
        ]);

        if ($response->ok !== true) {
            Log::channel('app')->error('SendAiReplyJob: topic post failed, delivering to user anyway', [
                'source' => 'send_ai_reply_publish_error',
                'bot_user_id' => $botUser->id,
                'response_code' => $response->response_code,
                'type_error' => $response->type_error,
            ]);

            return null;
        }

        Log::channel('app')->info('SendAiReplyJob: AI reply posted to supergroup', [
            'source' => 'ai_reply_supergroup',
            'bot_user_id' => $botUser->id,
            'supergroup_message_id' => $response->message_id,
        ]);

        return $response->message_id;
    }
}
