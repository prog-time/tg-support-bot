<?php

namespace App\Jobs;

use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use Illuminate\Support\Facades\Log;
use App\TelegramBot\TelegramMethods;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramSimpleQueryJob extends AbstractSendMessageJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    public mixed $queryParams;

    public function __construct(
        TGTextMessageDto $queryParams,
    ) {
        $this->queryParams = $queryParams;
    }

    public function handle(): void
    {
        try {
            if (!empty($this->queryParams->chat_id)) {
                $botUser = BotUser::where([
                    'chat_id' => $this->queryParams->chat_id
                ])->first();
                $this->botUserId = $botUser->id ?? 0;
            }

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            $response = TelegramMethods::sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            if (!$response->ok) {
                $this->telegramResponseHandler($response);
            }

        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * @param BotUser $botUser
     * @param mixed $resultQuery
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }

    /**
     * @param BotUser $botUser
     * @param mixed $resultQuery
     * @return void
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
