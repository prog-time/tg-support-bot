<?php

namespace App\Http\Controllers;

use App\DTOs\VK\VkUpdateDto;
use App\Services\VK\VkMessageService;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Exception;

class VkBotController
{
    private VkUpdateDto $dataHook;

    public function __construct(Request $request)
    {
        $dataHook = VkUpdateDto::fromRequest($request);
        $this->dataHook = !empty($dataHook) ? $dataHook : die();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function bot_query(): void
    {
        if (!empty($this->dataHook)) {
            if ($this->dataHook->type === 'message_new') {
                (new VkMessageService($this->dataHook))->handleUpdate();
            }
        }
    }
}
