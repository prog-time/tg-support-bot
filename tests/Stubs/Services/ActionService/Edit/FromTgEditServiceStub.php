<?php

namespace Tests\Stubs\Services\ActionService\Edit;

use App\Modules\Telegram\Services\ActionService\Edit\TemplateEditService;
use Tests\Traits\HasGettersForHasTemplate;

class FromTgEditServiceStub extends TemplateEditService
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
