<?php

namespace App\Modules\Max\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Modules\Max\Api\MaxMethods;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use Illuminate\Support\Facades\Log;

class SendMaxSimpleMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = 'outgoing';

    private mixed $maxMethods;

    public function __construct(
        MaxTextMessageDto $queryParams,
        mixed $maxMethods = null,
    ) {
        $this->queryParams = $queryParams;
        $this->maxMethods = $maxMethods ?? new MaxMethods();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->maxMethods->sendQuery($this->queryParams->methodQuery, $this->queryParams->toArray());
        } catch (\Throwable $e) {
            Log::channel('loki')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }

    /**
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
