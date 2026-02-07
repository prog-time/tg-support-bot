<?php

namespace App\Http\Controllers;

use App\Actions\Vk\SendBannedMessageVk;
use App\DTOs\Vk\VkUpdateDto;
use App\Models\BotUser;
use App\Services\VK\VkEditService;
use App\Services\VK\VkMessageService;
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
            (new SendBannedMessageVk())->execute($botUser);

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
