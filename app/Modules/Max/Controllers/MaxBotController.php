<?php

namespace App\Modules\Max\Controllers;

use App\Models\BotUser;
use App\Modules\Max\Actions\SendBannedMessageMax;
use App\Modules\Max\Actions\SendStartMessageMax;
use App\Modules\Max\DTOs\MaxUpdateDto;
use App\Modules\Max\Services\MaxMessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class MaxBotController
{
    /**
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function bot_query(Request $request): Response
    {
        if ($request->input('update_type') === 'bot_started') {
            $userId = $request->input('user.user_id');
            if (!empty($userId)) {
                $botUser = (new BotUser())->getUserByChatId((int) $userId, 'max');
                if (!$botUser->isBanned()) {
                    app(SendStartMessageMax::class)->execute($botUser);
                }
            }

            return response('ok', 200);
        }

        $dataHook = MaxUpdateDto::fromRequest($request);
        if (empty($dataHook)) {
            return response('ok', 200);
        }

        $cacheKey = 'max_event_' . $dataHook->event_id;
        if (Cache::has($cacheKey)) {
            return response('ok', 200);
        }
        Cache::put($cacheKey, true, 600);

        $botUser = (new BotUser())->getUserByChatId($dataHook->from_id, 'max');

        if ($botUser->isBanned()) {
            app(SendBannedMessageMax::class)->execute($botUser);

            return response('ok', 200);
        }

        if ($dataHook->type === 'message_created') {
            (new MaxMessageService($dataHook))->handleUpdate();
        }

        return response('ok', 200);
    }
}
