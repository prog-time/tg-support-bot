<?php

namespace Tests\Stubs\Services\External;

use App\Modules\External\Services\ExternalService;
use Tests\Traits\HasGettersForHasTemplate;

class ExternalServiceStub extends ExternalService
{
    use HasGettersForHasTemplate;

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
    }
}
