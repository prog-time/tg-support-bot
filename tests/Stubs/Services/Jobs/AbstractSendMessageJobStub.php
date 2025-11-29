<?php

namespace Tests\Stubs\Services\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;

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
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
    }

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param mixed $resultQuery
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
    }
}
