<?php

namespace Tests\Unit\TG;

use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramUpdateDto;
use App\Services\External\ExternalTrafficService;
use Illuminate\Support\Facades\Request;
use Tests\Factories\TelegramUpdateFactory;
use Tests\TestCase;

class TgMessageServiceTest extends TestCase
{
    protected function getDto(): TelegramUpdateDto
    {
        $fakeUpdate = TelegramUpdateFactory::make([
            'message' => [
                'text' => 'Проверочный текст',
            ],
        ]);

        $request = Request::create('/bot/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($fakeUpdate));

        return TelegramUpdateDto::fromRequest($request);
    }

    protected function sendNewMessage(array $dataMessage): ExternalMessageAnswerDto
    {
        return (new ExternalTrafficService())->store(ExternalMessageDto::from($dataMessage));
    }
}
