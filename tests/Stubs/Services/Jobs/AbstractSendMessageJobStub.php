<?php

namespace Tests\Stubs\Services\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;

class AbstractSendMessageJobStub extends AbstractSendMessageJob
{
    public function handle(): void
    {
    }

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param mixed $resultQuery
     */
    protected function saveMessage(mixed $resultQuery): void
    {
    }

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param mixed $resultQuery
     */
    protected function editMessage(mixed $resultQuery): void
    {
    }
}
