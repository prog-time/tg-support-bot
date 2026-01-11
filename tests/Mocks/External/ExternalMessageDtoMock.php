<?php

namespace Tests\Mocks\External;

use App\DTOs\External\ExternalMessageDto;
use Illuminate\Support\Facades\Request;

class ExternalMessageDtoMock
{
    /**
     * @return array
     */
    public static function getDtoParams(): array
    {
        return [
            'source' => 'live_chat',
            'external_id' => '123456',
            'message_id' => time(),
            'text' => 'Тестовое сообщение',
            'uploaded_file' => null,
        ];
    }

    /**
     * @param array $dtoParams
     *
     * @return ExternalMessageDto
     */
    public static function getDto(array $dtoParams = []): ExternalMessageDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        $request = Request::create('api/telegram/bot', 'POST', $dtoParams);
        return ExternalMessageDto::fromRequest($request);
    }
}
