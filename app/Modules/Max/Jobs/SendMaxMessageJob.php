<?php

namespace App\Modules\Max\Jobs;

use App\Jobs\SendMessage\AbstractSendMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Max\Api\MaxMethods;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Support\Facades\Log;

class SendMaxMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = 'outgoing';

    private mixed $maxMethods;

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
        MaxTextMessageDto $queryParams,
        mixed $maxMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;
        $this->maxMethods = $maxMethods ?? new MaxMethods();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);

            $response = $this->maxMethods->sendQuery(
                $this->queryParams->methodQuery,
                $this->queryParams->toArray()
            );

            $retryDelays = [2, 4, 8, 16, 30];

            foreach ($retryDelays as $attempt => $delay) {
                if ($response->response_code === 200) {
                    $this->saveMessage($botUser, $response);
                    $this->updateTopic($botUser, $this->typeMessage);

                    return;
                }

                if (str_contains($response->error_message ?? '', 'attachment.not.ready')) {
                    Log::channel('loki')->info('SendMaxMessageJob: attachment not ready, retrying', [
                        'attempt' => $attempt + 1,
                        'delay' => $delay,
                    ]);
                    sleep($delay);
                    $response = $this->maxMethods->sendQuery(
                        $this->queryParams->methodQuery,
                        $this->queryParams->toArray()
                    );
                    continue;
                }

                throw new \Exception($response->error_message ?? 'SendMaxMessageJob: unknown error', 1);
            }

            if ($response->response_code === 200) {
                $this->saveMessage($botUser, $response);
                $this->updateTopic($botUser, $this->typeMessage);

                return;
            }

            throw new \Exception($response->error_message ?? 'SendMaxMessageJob: attachment not ready after all retries', 1);
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
        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $this->updateDto->messageId,
            'to_id' => 0,
            'text' => $this->updateDto->text ?? $this->updateDto->caption ?? null,
        ]);

        if (!empty($this->updateDto->fileId)) {
            $message->attachments()->create([
                'file_id' => $this->updateDto->fileId,
                'file_type' => $this->updateDto->fileType ?? 'document',
            ]);
        }
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
