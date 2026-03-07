<?php

namespace App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages;

use App\Modules\Admin\Filament\Resources\ExternalSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExternalSources extends ListRecords
{
    protected static string $resource = ExternalSourceResource::class;

    /**
     * @return array<CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
