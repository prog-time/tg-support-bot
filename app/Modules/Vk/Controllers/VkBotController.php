<?php

namespace App\Modules\Vk\Controllers;

use App\Models\BotUser;
use App\Modules\Vk\Actions\SendBannedMessageVk;
use App\Modules\Vk\DTOs\VkUpdateDto;
use App\Modules\Vk\Services\VkEditService;
use App\Modules\Vk\Services\VkMessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class VkBotController
{
    /**
     * @return Response
     *
     * @throws \Exception
     */
    public function bot_query(Request $request): Response
    {
        if ($request->type === 'confirmation') {
            return response(config('traffic_source.settings.vk.confirm_code'), 200);
        }

        $dataHook = VkUpdateDto::fromRequest($request);
        if (empty($dataHook)) {
            return response('ok', 200);
        }

        $cacheKey = 'vk_event_' . $dataHook->event_id;
        if (Cache::has($cacheKey)) {
            return response('ok', 200);
        }
        Cache::put($cacheKey, true, 600);

        $botUser = (new BotUser())->getUserByChatId($dataHook->from_id, 'vk');

        if ($botUser->isBanned()) {
            app(SendBannedMessageVk::class)->execute($botUser);

            return response('ok', 200);
        }

        switch ($dataHook->type) {
            case 'message_new':
                (new VkMessageService($dataHook))->handleUpdate();
                break;

            case 'message_edit':
                (new VkEditService($dataHook))->handleUpdate();
                break;
        }

        return response('ok', 200);
    }
}
