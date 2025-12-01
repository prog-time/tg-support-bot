<?php

namespace App\Jobs;

use App\DTOs\TGTextMessageDto;
use App\Logging\LokiLogger;
use App\TelegramBot\TelegramMethods;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramSimpleQueryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    public TGTextMessageDto $queryParams;

    public function __construct(
        TGTextMessageDto $queryParams,
    ) {
        $this->queryParams = $queryParams;
    }

    public function handle(): mixed
    {
        try {
            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            $response = TelegramMethods::sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            if (!$response->ok) {
                throw new \Exception(json_encode($response->rawData), 1);
            }

            return true;
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);
            return false;
        }
    }
}
