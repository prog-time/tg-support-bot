<?php

namespace App\Actions\Telegram;

use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;

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
            $queryParams->text = "<b>🚫 ПОЛЬЗОВАТЕЛЬ ЗАБЛОКИРОВАН 🚫</b> \n\n" . $queryParams->text;
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
