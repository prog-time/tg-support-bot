<?php

namespace Tests\Mocks\External;

use App\Modules\External\DTOs\ExternalMessageAnswerDto;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;

class ExternalMessageAnswerDtoMock extends TelegramUpdateDto
{
    public static function getDtoParams(): array
    {
        return [
            'status' => true,
            'result' => ExternalMessageResponseDtoMock::getDto(),
        ];
    }

    public static function getDto(array $dtoParams = []): ExternalMessageAnswerDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        return ExternalMessageAnswerDto::from($dtoParams);
    }
}
