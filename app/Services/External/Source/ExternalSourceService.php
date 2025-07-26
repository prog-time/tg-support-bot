<?php

namespace App\Services\External\Source;

use App\DTOs\ExternalSourceDto;
use App\Models\ExternalSource;

class ExternalSourceService
{
    private ExternalSource $externalSourceModel;

    public function __construct()
    {
        $this->externalSourceModel = new ExternalSource();
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

        (new ExternalSourceTokensService())->setAccessToken($item->id);

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

        (new ExternalSourceTokensService())->setAccessToken($data->id);

        return $this->externalSourceModel->where('id', $data->id)->first();
    }
}
