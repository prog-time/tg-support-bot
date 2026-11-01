<?php

namespace App\Modules\Ai\Actions;

use App\Helpers\AiHelper;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Admin\Jobs\MirrorAdminReplyToGroupJob;
use App\Modules\Admin\Services\ChannelStatusService;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Exception;

class AiAcceptMessage extends AiAction
{
    /**
     * Confirm AI response sending — Telegram callback entry point.
     *
     * Resolves the AiMessage from callback_data and delegates to executeForDraft().
     *
     * @param TelegramUpdateDto $update
     *
     * @return void
     */
    public function execute(TelegramUpdateDto $update): void
    {
        try {
            $botUser = BotUser::getOrCreateByTelegramUpdate($update);
            if (!$botUser) {
                throw new Exception('User not found', 1);
            }

            $messageData = $this->getMessageDataByCallbackData($update->callbackData);
            if (empty($messageData)) {
                throw new Exception('Message not found in database!', 1);
            }

            if ($messageData->message_id !== null) {
                SendTelegramMessageJob::dispatch(
                    $botUser->id,
                    $update,
                    TGTextMessageDto::from([
                        'methodQuery' => 'deleteMessage',
                        'typeSource' => 'supergroup',
                        'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
                        'message_id' => $messageData->message_id,
                        'message_thread_id' => $update->messageThreadId,
                    ]),
                    'incoming',
                );
            }

            $messageData->update(['status' => AiMessage::STATUS_ACCEPTED]);

            Log::channel('app')->info('AiAcceptMessage: delivering AI answer to user', [
                'source' => 'ai_accept_deliver',
                'bot_user_id' => $botUser->id,
                'platform' => $botUser->platform,
                'ai_message_id' => $messageData->id,
            ]);

            app(DeliverAiAnswerToUser::class)->execute($botUser, $messageData->text_ai, $update);

            $this->mirrorAnswerToGroup($botUser, (string) $messageData->text_ai);
        } catch (\Throwable $e) {
            Log::channel('app')->error($e->getMessage(), ['source' => 'ai_error']);
        }
    }

    /**
     * Accept an AI draft directly — used by the admin panel workspace.
     *
     * Sets status=accepted, delivers to the user. When a supergroup message_id
     * exists, deletes the draft message from the
     * supergroup so the thread stays clean.
     *
     * @param AiMessage $aiMessage The pending draft to accept
     *
     * @return void
     */
    public function executeForDraft(AiMessage $aiMessage): void
    {
        /** @var BotUser|null $botUser */
        $botUser = $aiMessage->botUser;
        if ($botUser === null) {
            Log::channel('app')->warning('AiAcceptMessage::executeForDraft: botUser not found', [
                'source' => 'ai_error',
                'ai_message_id' => $aiMessage->id,
            ]);

            return;
        }

        $textAi = (string) $aiMessage->text_ai;
        if ($textAi === '') {
            Log::channel('app')->warning('AiAcceptMessage::executeForDraft: empty text_ai', [
                'source' => 'ai_error',
                'ai_message_id' => $aiMessage->id,
            ]);

            return;
        }

        if ($aiMessage->message_id !== null) {
            $groupId = (string) app(SettingsService::class)->get('telegram.group_id');
            if ($groupId !== '') {
                $emptyUpdate = new TelegramUpdateDto(
                    updateId: 0,
                    typeQuery: 'message',
                    aiTechMessage: false,
                    typeSource: 'private',
                );
                SendTelegramMessageJob::dispatch(
                    $botUser->id,
                    $emptyUpdate,
                    TGTextMessageDto::from([
                        'methodQuery' => 'deleteMessage',
                        'typeSource' => 'supergroup',
                        'chat_id' => $groupId,
                        'message_id' => $aiMessage->message_id,
                        'message_thread_id' => $botUser->topic_id,
                    ]),
                    'incoming',
                );
            }
        }

        $aiMessage->update(['status' => AiMessage::STATUS_ACCEPTED]);

        Log::channel('app')->info('AiAcceptMessage::executeForDraft: accepted and delivering', [
            'source' => 'ai_accept_draft_deliver',
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'ai_message_id' => $aiMessage->id,
        ]);

        app(DeliverAiAnswerToUser::class)->execute($botUser, $textAi);

        $this->mirrorAnswerToGroup($botUser, $textAi);
    }

    /**
     * After an AI answer is accepted, post it into the user's Telegram supergroup
     * topic (so the group shows the final answer once its draft is removed).
     *
     * Runs only when the Telegram group is configured (token + group_id). Posts
     * plain text (HTML markup stripped) from the main bot with the AI label. Never creates a messages row and never re-delivers to the user.
     *
     * @param BotUser $botUser
     * @param string  $textAi  AI answer (may contain Telegram HTML markup)
     *
     * @return void
     */
    private function mirrorAnswerToGroup(BotUser $botUser, string $textAi): void
    {
        $groupId = (string) app(SettingsService::class)->get('telegram.group_id');

        if ($groupId === '' || ! app(ChannelStatusService::class)->telegram()['connected']) {
            return;
        }

        $plain = AiHelper::toPlainText($textAi);

        if ($plain === '') {
            return;
        }

        MirrorAdminReplyToGroupJob::dispatch($botUser->id, $plain, AiHelper::AI_LABEL . "\n");
    }
}
