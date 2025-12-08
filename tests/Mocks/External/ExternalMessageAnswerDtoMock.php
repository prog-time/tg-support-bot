<?php

namespace Tests\Mocks\External;

use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\TelegramUpdateDto;

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
