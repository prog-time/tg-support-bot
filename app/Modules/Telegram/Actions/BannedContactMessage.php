<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;

class BannedContactMessage
{
    /**
     * @param BotUser  $botUser
     * @param bool     $banStatus
     * @param int|null $messageId
     *
     * @return void
     */
    public function execute(BotUser $botUser, bool $banStatus, ?int $messageId = null): void
    {
        $botUser->update([
            'is_banned' => $banStatus,
        ]);
        $botUser->save();

        $queryParams = (new SendContactMessage())->getQueryParams($botUser);

        if ($botUser->isBanned()) {
            $queryParams->text = '<b>' . __('messages.ban_status_message') . "</b> \n\n" . $queryParams->text;
        }

        if ($messageId !== null) {
            $queryParams->message_id = $messageId;
            $queryParams->methodQuery = 'editMessageText';
        } else {
            $queryParams->methodQuery = 'sendMessage';
        }

        SendTelegramSimpleQueryJob::dispatch($queryParams);
    }
}
