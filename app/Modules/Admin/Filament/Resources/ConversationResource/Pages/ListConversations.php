<?php

namespace App\Modules\Admin\Filament\Resources\ConversationResource\Pages;

use App\Modules\Admin\Filament\Resources\ConversationResource;
use Filament\Resources\Pages\ListRecords;

class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;
}
