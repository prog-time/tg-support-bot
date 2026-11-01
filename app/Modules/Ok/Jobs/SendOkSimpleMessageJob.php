<?php

namespace App\Modules\Ok\Jobs;

use App\Modules\Ok\Api\OkMethods;
use App\Modules\Ok\DTOs\OkTextMessageDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Send a message to OK without saving it: callers own the `messages` row.
 * OkMethods::sendQuery() logs failures.
 */
class SendOkSimpleMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    /**
     * @param OkTextMessageDto $queryParams
     */
    public function __construct(public OkTextMessageDto $queryParams)
    {
    }

    /**
     * @param OkMethods $okMethods
     *
     * @return void
     */
    public function handle(OkMethods $okMethods): void
    {
        $okMethods->sendQuery($this->queryParams->methodQuery, $this->queryParams->toArray());
    }
}
