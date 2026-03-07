<?php

namespace App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages;

use App\Models\ExternalSource;
use App\Modules\Admin\Filament\Resources\ExternalSourceResource;
use App\Modules\External\Services\Source\ExternalSourceTokensService;
use Filament\Resources\Pages\CreateRecord;

class CreateExternalSource extends CreateRecord
{
    protected static string $resource = ExternalSourceResource::class;

    /**
     * Generate access token after record creation.
     *
     * @return void
     */
    protected function afterCreate(): void
    {
        /** @var ExternalSource $record */
        $record = $this->getRecord();

        app(ExternalSourceTokensService::class)->setAccessToken($record->id);
    }
}
