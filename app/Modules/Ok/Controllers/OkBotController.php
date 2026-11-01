<?php

namespace App\Modules\Ok\Controllers;

use App\Models\BotUser;
use App\Modules\Ok\Actions\SendBannedMessageOk;
use App\Modules\Ok\Actions\SendStartMessageOk;
use App\Modules\Ok\DTOs\OkUpdateDto;
use App\Modules\Ok\Services\OkMessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class OkBotController
{
    /**
     * OK repeats an event that was not answered with 200 within 5 seconds, for
     * up to 8 hours, so events are remembered a little longer than that.
     */
    private const DEDUPE_SECONDS = 32400;

    /**
     * Receive an OK webhook event.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function bot_query(Request $request): Response
    {
        $update = OkUpdateDto::fromRequest($request);

        if ($update === null || !Cache::add('ok_event_' . $update->event_id, true, self::DEDUPE_SECONDS)) {
            return response('ok', 200);
        }

        $isStart = $update->type === OkUpdateDto::TYPE_SYSTEM && $update->systemType === OkUpdateDto::SYSTEM_CHAT_STARTED;

        if ($update->type !== OkUpdateDto::TYPE_MESSAGE && !$isStart) {
            return response('ok', 200);
        }

        $botUser = BotUser::getUserByChatId($update->chatId, 'ok');

        if ($botUser === null) {
            return response('ok', 200);
        }

        if ($botUser->isBanned()) {
            app(SendBannedMessageOk::class)->execute($botUser);
        } elseif ($isStart) {
            app(SendStartMessageOk::class)->execute($botUser);
        } else {
            (new OkMessageService($update))->handleUpdate();
        }

        return response('ok', 200);
    }
}
