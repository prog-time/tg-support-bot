<?php

namespace App\Http\Controllers;

use App\DTOs\Vk\VkUpdateDto;
use App\Services\VK\VkEditService;
use App\Services\VK\VkMessageService;
use Illuminate\Http\Request;

class VkBotController
{
    private VkUpdateDto $dataHook;

    public function __construct(Request $request)
    {
        if (request()->type === 'confirmation') {
            echo config('traffic_source.settings.vk.confirm_code');
            die();
        }

        $dataHook = VkUpdateDto::fromRequest($request);
        $this->dataHook = !empty($dataHook) ? $dataHook : die();
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    public function bot_query(): void
    {
        switch ($this->dataHook->type) {
            case 'message_new':
                (new VkMessageService($this->dataHook))->handleUpdate();
                break;

            case 'message_edit':
                (new VkEditService($this->dataHook))->handleUpdate();
                break;
        }
    }
}
