<?php

namespace Tests\Mocks\External;

use App\DTOs\External\ExternalMessageResponseDto;
use App\DTOs\TelegramUpdateDto;
use Illuminate\Support\Facades\Request;

class ExternalMessageResponseDtoMock extends TelegramUpdateDto
{
    public static function getDtoParams(): array
    {
        return [
            'message_type' => 'outgoing',
            'to_id' => time(),
            'from_id' => time(),
            'text' => 'Тестовое сообщение',
            'date' => date('d.m.Y H:i:s'),
            'content_type' => 'text' ,
            'file_id' => null,
            'file_url' => null,
            'file_type' => null,
        ];
    }

    public static function getDto(array $dtoParams = []): ExternalMessageResponseDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        $request = Request::create('api/telegram/bot', 'POST', $dtoParams);
        return ExternalMessageResponseDto::from($request);
    }
}
