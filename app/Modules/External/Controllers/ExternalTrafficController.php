<?php

namespace App\Modules\External\Controllers;

use App\Models\BotUser;
use App\Modules\External\DTOs\ExternalListMessageDto;
use App\Modules\External\DTOs\ExternalMessageDto;
use App\Modules\External\Services\ExternalTrafficService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ExternalTrafficController
 *
 * @package App\Modules\External\Controllers
 */
class ExternalTrafficController
{
    private ExternalMessageDto|ExternalListMessageDto $dataHook;

    protected ?string $platform;

    public function __construct(Request $request, private ExternalTrafficService $externalTrafficService)
    {
        if ($request->route()->getName() === 'index') {
            $dataMessage = ExternalListMessageDto::fromRequest($request);
        } else {
            $dataMessage = ExternalMessageDto::fromRequest($request);
        }
        $this->dataHook = !empty($dataMessage) ? $dataMessage : die();

        $botUser = (new BotUser())->getExternalBotUser($this->dataHook->external_id, $this->dataHook->source);
        if (!empty($botUser)) {
            if ($botUser->isBanned()) {
                die();
            }
        }
    }

    /**
     * Get message list.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json($this->externalTrafficService->list($this->dataHook));
    }

    /**
     * Get single message.
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        return response()->json($this->externalTrafficService->show($id));
    }

    /**
     * Create text message.
     *
     * @return void
     */
    public function store(): void
    {
        $this->externalTrafficService->store($this->dataHook);
    }

    /**
     * Send file.
     *
     * @return void
     */
    public function sendFile(): void
    {
        $this->externalTrafficService->sendFile($this->dataHook);
    }

    /**
     * Update message.
     *
     * @return void
     */
    public function update(): void
    {
        $this->externalTrafficService->update($this->dataHook);
    }

    /**
     * Delete message.
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->externalTrafficService->destroy($this->dataHook);
    }
}
