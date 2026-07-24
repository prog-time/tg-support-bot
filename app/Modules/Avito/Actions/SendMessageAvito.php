<?php

namespace App\Modules\Avito\Actions;

use App\Modules\Avito\Api\AvitoMethods;
use App\Modules\Avito\DTOs\AvitoAnswerDto;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;

/**
 * Send a message to Avito synchronously (isolated operation). Mirrors
 * the host's SendMessageMax action.
 */
class SendMessageAvito
{
    /**
     * @param AvitoTextMessageDto $queryParams
     *
     * @return AvitoAnswerDto|null
     */
    public function execute(AvitoTextMessageDto $queryParams): ?AvitoAnswerDto
    {
        try {
            return (new AvitoMethods())->sendQuery($queryParams->methodQuery, $queryParams->toArray());
        } catch (\Throwable) {
            return null;
        }
    }
}
