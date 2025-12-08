<?php

namespace Tests\Mocks\Vk\Answer;

use App\DTOs\Vk\VkAnswerDto;

class VkAnswerDtoMock
{
    /**
     * @return array
     */
    public static function getDtoParams(): array
    {
        return [
            'response_code' => 200,
            'response' => time(),
        ];
    }

    /**
     * @param array $dtoParams
     *
     * @return VkAnswerDto
     */
    public static function getDto(array $dtoParams = []): VkAnswerDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        return VkAnswerDto::fromData($dtoParams);
    }
}
