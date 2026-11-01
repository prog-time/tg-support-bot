<?php

namespace App\Modules\Ai\Actions;

use App\Models\AiMessage;
use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Exception;

class AiCancelMessage extends AiAction
{
    /**
     * Cancel AI request — Telegram callback entry point.
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
                        'typeSource' => 'private',
                        'chat_id' => $update->chatId,
                        'message_thread_id' => $update->messageThreadId,
                        'message_id' => $messageData->message_id,
                    ]),
                    'outgoing',
                );
            }

            $messageData->update(['status' => AiMessage::STATUS_CANCELLED]);
        } catch (\Throwable $e) {
            Log::channel('app')->error($e->getMessage(), ['source' => 'ai_error']);
        }
    }

    /**
     * Cancel an AI draft directly — used by the admin panel workspace.
     *
     * Sets status=cancelled. When a supergroup message_id exists,
     * also deletes the draft message from the supergroup.
     *
     * @param AiMessage $aiMessage The pending draft to cancel
     *
     * @return void
     */
    public function executeForDraft(AiMessage $aiMessage): void
    {
        if ($aiMessage->message_id !== null) {
            /** @var BotUser|null $botUser */
            $botUser = $aiMessage->botUser;
            $groupId = (string) app(SettingsService::class)->get('telegram.group_id');

            if ($botUser !== null && $groupId !== '') {
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
                    'outgoing',
                );
            }
        }

        $aiMessage->update(['status' => AiMessage::STATUS_CANCELLED]);

        Log::channel('app')->info('AiCancelMessage::executeForDraft: cancelled', [
            'source' => 'ai_cancel_draft',
            'ai_message_id' => $aiMessage->id,
        ]);
    }
}
