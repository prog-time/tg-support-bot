<?php

namespace App\Modules\External\Services\Source;

use App\Models\ExternalSource;
use App\Modules\External\DTOs\ExternalSourceDto;

class ExternalSourceService
{
    public function __construct(
        private ExternalSource $externalSourceModel,
        private ExternalSourceTokensService $externalSourceTokensService,
    ) {
    }

    /**
     * Добавление
     *
     * @param ExternalSourceDto $data
     *
     * @return ExternalSource
     *
     * @throws \Exception
     */
    public function create(ExternalSourceDto $data): ExternalSource
    {
        $item = $this->externalSourceModel
            ->create($data->toArray())
            ->getModel();

        $this->externalSourceTokensService->setAccessToken($item->id);

        return $item;
    }

    /**
     * Обновление
     *
     * @param ExternalSourceDto $data
     *
     * @return ExternalSource
     *
     * @throws \Exception
     */
    public function update(ExternalSourceDto $data): ExternalSource
    {
        $this->externalSourceModel
            ->where('id', $data->id)
            ->update($data->toArray());

        $this->externalSourceTokensService->setAccessToken($data->id);

        return $this->externalSourceModel->where('id', $data->id)->first();
    }
}
