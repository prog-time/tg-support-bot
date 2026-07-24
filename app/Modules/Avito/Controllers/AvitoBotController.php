<?php

namespace App\Modules\Avito\Controllers;

use App\Models\BotUser;
use App\Modules\Avito\Actions\SendBannedMessageAvito;
use App\Modules\Avito\DTOs\AvitoUpdateDto;
use App\Modules\Avito\Services\AvitoMessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Thin webhook controller for Avito. Mirrors the host's MaxBotController:
 * dedupe by event id, find/create the BotUser (platform='avito'), short-circuit
 * banned users, then hand the update to AvitoMessageService.
 */
class AvitoBotController
{
    /**
     * @param Request $request
     *
     * @return Response
     */
    public function bot_query(Request $request): Response
    {
        $update = AvitoUpdateDto::fromRequest($request);
        if ($update === null) {
            return response('ok', 200);
        }

        // Idempotency: Avito may redeliver. Skip if we've seen this event id.
        $cacheKey = 'avito_event_' . $update->event_id;
        if (Cache::has($cacheKey)) {
            return response('ok', 200);
        }
        Cache::put($cacheKey, true, 600);

        // The Avito chat id is the conversation key and is a string
        // (e.g. "u2i-..."), which is why bot_users.chat_id is a string column.
        $botUser = BotUser::getUserByChatId($update->chatId, 'avito');

        if ($botUser === null) {
            return response('ok', 200);
        }

        if ($botUser->isBanned()) {
            app(SendBannedMessageAvito::class)->execute($botUser);

            return response('ok', 200);
        }

        // No feedback-rating callback route: Avito Messenger has no
        // inline-keyboard mechanism, so SendFeedbackForm asks Avito users for
        // a free-text review instead of a tappable 1-5 rating (deliberate
        // product decision, not a pending TODO). Any reply the user sends
        // just arrives below as a normal inbound message.

        if ($update->type === 'message' && $update->text !== null) {
            (new AvitoMessageService($update))->handleUpdate();
        }

        return response('ok', 200);
    }
}
