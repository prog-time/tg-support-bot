<?php

namespace App\Http\Controllers;

use App\DTOs\External\ExternalListMessageDto;
use App\DTOs\External\ExternalMessageDto;
use App\Http\Requests\External\ExternalTrafficStoreRequest;
use App\Services\External\ExternalTrafficService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ExternalTrafficController
 *
 * @package App\Http\Controllers
 */
class ExternalTrafficController
{
    private ExternalMessageDto|ExternalListMessageDto $dataHook;

    private ExternalTrafficService $externalTrafficService;

    protected ?string $platform;

    public function __construct(Request $request)
    {
        if ($request->route()->getName() === 'index') {
            $dataMessage = ExternalListMessageDto::fromRequest($request);
        } else {
            $dataMessage = ExternalMessageDto::fromRequest($request);
        }
        $this->dataHook = !empty($dataMessage) ? $dataMessage : die();

        $this->externalTrafficService = new ExternalTrafficService();
    }

    /**
     * Получить список сообщений
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->externalTrafficService->list($this->dataHook));
    }

    /**
     * Получить одно сообщение
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
     * Создать сообщение
     *
     * @param ExternalTrafficStoreRequest $request
     *
     * @return JsonResponse
     */
    public function store(ExternalTrafficStoreRequest $request): JsonResponse
    {
        $dataDto = $this->dataHook;
        return response()->json($this->externalTrafficService->store($dataDto)->toArray());
    }

    /**
     * Обновить сообщение
     *
     * @return JsonResponse
     */
    public function update(): JsonResponse
    {
        $dataDto = $this->dataHook;
        return response()->json($this->externalTrafficService->update($dataDto)->toArray());
    }

    /**
     * Удалить сообщение
     *
     * @return JsonResponse
     */
    public function destroy(): JsonResponse
    {
        $dataDto = $this->dataHook;
        return response()->json($this->externalTrafficService->destroy($dataDto)->toArray());
    }
}
