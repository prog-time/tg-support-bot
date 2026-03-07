<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use Tests\Stubs\Services\Jobs\AbstractSendMessageJobStub;
use Tests\TestCase;

class AbstractSendMessageJobTest extends TestCase
{
    public function test_construct_with_private_source(): void
    {
        $service = new AbstractSendMessageJobStub();

        $this->assertInstanceOf(AbstractSendMessageJob::class, $service);
    }
}
