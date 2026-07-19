<?php

namespace App\Modules\Avito\Jobs;

use App\Modules\Avito\Api\AvitoMethods;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send a "fire-and-forget" Avito message (no message persistence), e.g. banned
 * / start notices and the feedback prompt. Mirrors SendMaxSimpleMessageJob.
 */
class SendAvitoSimpleMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    private AvitoMethods $avitoMethods;

    public function __construct(
        public AvitoTextMessageDto $queryParams,
        ?AvitoMethods $avitoMethods = null,
    ) {
        $this->avitoMethods = $avitoMethods ?? new AvitoMethods();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->avitoMethods->sendQuery($this->queryParams->methodQuery, $this->queryParams->toArray());
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()],
            );
        }
    }
}
