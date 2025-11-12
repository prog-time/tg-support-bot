<?php

namespace Tests\Mocks\External;

use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramUpdateDto;
use Illuminate\Support\Facades\Request;

class ExternalMessageDtoMock extends TelegramUpdateDto
{
    public static function getDtoParams(): array
    {
        return [
            'source' => config('testing.external.source'),
            'external_id' => config('testing.external.external_id'),
            'message_id' => time(),
            'text' => 'Тестовое сообщение',
            'uploaded_file' => null,
        ];
    }

    public static function getDto(array $dtoParams = []): ExternalMessageDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        $request = Request::create('api/telegram/bot', 'POST', $dtoParams);
        return ExternalMessageDto::fromRequest($request);
    }
}
