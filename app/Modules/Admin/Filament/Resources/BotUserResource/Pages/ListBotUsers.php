<?php

namespace App\Modules\Admin\Filament\Resources\BotUserResource\Pages;

use App\Modules\Admin\Filament\Resources\BotUserResource;
use Filament\Resources\Pages\ListRecords;

class ListBotUsers extends ListRecords
{
    protected static string $resource = BotUserResource::class;
}
