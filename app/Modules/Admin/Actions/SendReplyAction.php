<?php

namespace App\Modules\Admin\Actions;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\External\Jobs\SendWebhookMessage;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Vk\DTOs\VkTextMessageDto;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;

class SendReplyAction
{
    /**
     * Send a manager reply to the user via the appropriate platform.
     *
     * @param BotUser $botUser Target user
     * @param string  $text    Message text
     *
     * @return void
     */
    public static function execute(BotUser $botUser, string $text): void
    {
        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 0,
            'text' => $text,
        ]);

        match (true) {
            $botUser->platform === 'telegram' => SendTelegramSimpleQueryJob::dispatch(
                TGTextMessageDto::from([
                    'methodQuery' => 'sendMessage',
                    'chat_id' => $botUser->chat_id,
                    'text' => $text,
                ])
            ),
            $botUser->platform === 'vk' => SendVkSimpleMessageJob::dispatch(
                VkTextMessageDto::from([
                    'methodQuery' => 'messages.send',
                    'peer_id' => $botUser->chat_id,
                    'message' => $text,
                ])
            ),
            default => self::sendExternalReply($botUser, $text),
        };
    }

    /**
     * Send reply to an external source via webhook.
     *
     * @param BotUser $botUser
     * @param string  $text
     *
     * @return void
     */
    private static function sendExternalReply(BotUser $botUser, string $text): void
    {
        $botUser->load('externalUser.externalSource');
        $webhookUrl = $botUser->externalUser?->externalSource?->webhook_url;

        if (empty($webhookUrl)) {
            return;
        }

        SendWebhookMessage::dispatch($webhookUrl, [
            'type_query' => 'send_message',
            'externalId' => $botUser->externalUser->external_id,
            'message' => [
                'content_type' => 'text',
                'message_type' => 'outgoing',
                'text' => $text,
                'date' => date('d.m.Y H:i'),
            ],
        ]);
    }
}
