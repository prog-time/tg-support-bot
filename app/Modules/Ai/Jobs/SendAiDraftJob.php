<?php

namespace App\Modules\Ai\Jobs;

use App\Helpers\AiHelper;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Admin\Services\ChannelStatusService;
use App\Modules\Ai\DTOs\AiRequestDto;
use App\Modules\Ai\Services\AiAssistantService;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Services\Settings\SettingsService;
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
     * Generate an AI draft and persist it for the admin panel workspace.
     * The draft is saved before the Telegram call, so a failed publication
     * leaves it available in /admin/chats with message_id=null.
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
                Log::channel('app')->info('SendAiDraftJob: topic_id not ready, releasing', [
                    'source' => 'send_ai_draft_topic_pending',
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
            if ($aiResponse === null) {
                throw new \RuntimeException('AI provider returned null', 1);
            }

            $aiMessage = AiMessage::create([
                'bot_user_id' => $botUser->id,
                'message_id' => null,
                'text_ai' => $aiResponse->response,
                'text_manager' => '',
                'status' => AiMessage::STATUS_PENDING,
            ]);

            if ($groupConfigured && !empty($botUser->topic_id)) {
                $this->postDraftToSupergroup($aiMessage, $botUser, $groupId);
            }
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['source' => 'send_ai_draft_error', 'file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * Whether a missing topic is worth another attempt: a sync queue never
     * re-runs a released job, and the last attempt must keep the draft.
     *
     * @return bool
     */
    private function canWaitForTopic(): bool
    {
        return $this->attempts() < $this->tries && $this->job?->getConnectionName() !== 'sync';
    }

    /**
     * Publish a saved draft to the topic and attach its action buttons.
     *
     * @param AiMessage $aiMessage
     * @param BotUser   $botUser
     * @param string    $groupId
     *
     * @return void
     */
    private function postDraftToSupergroup(AiMessage $aiMessage, BotUser $botUser, string $groupId): void
    {
        $response = TelegramMethods::sendQueryTelegram('sendMessage', [
            'chat_id' => $groupId,
            'message_thread_id' => $botUser->topic_id,
            'text' => AiHelper::preparedAiAnswer('', (string) $aiMessage->text_ai),
        ]);

        if ($response->ok !== true) {
            $this->logTelegramFailure('sendMessage', $aiMessage, $response);

            return;
        }

        $aiMessage->update(['message_id' => $response->message_id]);

        $markupResponse = TelegramMethods::sendQueryTelegram('editMessageReplyMarkup', [
            'chat_id' => $groupId,
            'message_thread_id' => $botUser->topic_id,
            'message_id' => $response->message_id,
            'reply_markup' => AiHelper::preparedAiReplyMarkup((int) $response->message_id, (string) $aiMessage->text_ai),
        ]);

        if ($markupResponse->ok !== true) {
            $this->logTelegramFailure('editMessageReplyMarkup', $aiMessage, $markupResponse);
        }
    }

    /**
     * Log a failed Telegram call. Only the code and error type are logged:
     * the raw answer of a network failure contains the URL with the bot token.
     *
     * @param string            $method
     * @param AiMessage         $aiMessage
     * @param TelegramAnswerDto $response
     *
     * @return void
     */
    private function logTelegramFailure(string $method, AiMessage $aiMessage, TelegramAnswerDto $response): void
    {
        Log::channel('app')->error('SendAiDraftJob: Telegram call failed, draft stays in admin panel', [
            'source' => 'send_ai_draft_publish_error',
            'method' => $method,
            'ai_message_id' => $aiMessage->id,
            'response_code' => $response->response_code,
            'type_error' => $response->type_error,
        ]);
    }
}
