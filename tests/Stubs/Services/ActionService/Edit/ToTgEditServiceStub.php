<?php

namespace Tests\Stubs\Services\ActionService\Edit;

use App\Services\ActionService\Edit\ToTgEditService;
use Tests\Traits\HasGettersForHasTemplate;

class ToTgEditServiceStub extends ToTgEditService
{
    use HasGettersForHasTemplate;

    public function handleUpdate(): void
    {
    }

    public function editMessageText(): void
    {
    }

    protected function editMessageCaption(): void
    {
    }
}
