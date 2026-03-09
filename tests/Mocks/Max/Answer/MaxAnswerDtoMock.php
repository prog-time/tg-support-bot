<?php

namespace Tests\Mocks\Max\Answer;

use App\Modules\Max\DTOs\MaxAnswerDto;

class MaxAnswerDtoMock
{
    /**
     * @return array
     */
    public static function getDtoParams(): array
    {
        return [
            'response_code' => 200,
            'response' => 'msg-' . time(),
        ];
    }

    /**
     * @param array $dtoParams
     *
     * @return MaxAnswerDto
     */
    public static function getDto(array $dtoParams = []): MaxAnswerDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        return MaxAnswerDto::fromData($dtoParams);
    }
}
