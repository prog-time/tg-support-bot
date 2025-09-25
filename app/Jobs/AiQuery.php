<?php

namespace App\Jobs;

use App\Actions\Telegram\SendAiAutoMessage;
use App\DTOs\TelegramUpdateDto;
use App\Logging\LokiLogger;
use App\Services\Ai\AiAssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AiQuery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected TelegramUpdateDto $dataHook;

    public int $tries = 3;

    public array $backoff = [60, 180, 300];

    public function __construct(TelegramUpdateDto $dataHook)
    {
        $this->dataHook = $dataHook;
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            (new SendAiAutoMessage(app(AiAssistantService::class)))->execute($this->dataHook);
        } catch (\Throwable $e) {
            (new LokiLogger())->log('error', $e->getMessage());

            $this->fail($e);
        }
    }
}
