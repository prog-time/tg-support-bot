<?php

namespace App\Modules\Max\Actions;

use App\Modules\Max\Api\MaxMethods;
use App\Modules\Max\DTOs\MaxAnswerDto;
use App\Modules\Max\DTOs\MaxTextMessageDto;

class SendMessageMax
{
    /**
     * @param MaxTextMessageDto $queryParams
     *
     * @return MaxAnswerDto|null
     */
    public function execute(MaxTextMessageDto $queryParams): ?MaxAnswerDto
    {
        try {
            return (new MaxMethods())->sendQuery($queryParams->methodQuery, $queryParams->toArray());
        } catch (\Throwable) {
            return null;
        }
    }
}
