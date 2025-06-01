<?php

namespace App\Http\Controllers;

use App\DTOs\External\ExternalListMessageDto;
use App\Http\Requests\External\ExternalTrafficDestroyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\DTOs\External\ExternalMessageDto;
use App\Services\External\ExternalTrafficService;
use App\Http\Requests\External\ExternalTrafficStoreRequest;
use App\Http\Requests\External\ExternalTrafficUpdateRequest;

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
     * Получить список сущностей
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->externalTrafficService->list($this->dataHook));
    }

    /**
     * Получить одну сущность
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        return response()->json($this->externalTrafficService->show($id));
    }

    /**
     * Создать сущность
     *
     * @param ExternalTrafficStoreRequest $request
     * @return JsonResponse
     */
    public function store(ExternalTrafficStoreRequest $request): JsonResponse
    {
        $dataDto = $this->dataHook;
        return response()->json($this->externalTrafficService->store($dataDto)->toArray());
    }

    /**
     * Обновить сущность
     *
     * @param ExternalTrafficUpdateRequest $request
     * @return JsonResponse
     */
    public function update(ExternalTrafficUpdateRequest $request): JsonResponse
    {
        $dataDto = $this->dataHook;
        return response()->json($this->externalTrafficService->update($dataDto)->toArray());
    }

    /**
     * Удалить сущность
     *
     * @return JsonResponse
     */
    public function destroy(ExternalTrafficDestroyRequest $request): JsonResponse
    {
        $dataDto = $this->dataHook;
        return response()->json($this->externalTrafficService->destroy($dataDto)->toArray());
    }

}
