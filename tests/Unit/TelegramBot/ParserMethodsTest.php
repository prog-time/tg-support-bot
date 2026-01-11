<?php

namespace Tests\Unit\TelegramBot;

use App\TelegramBot\ParserMethods;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParserMethodsTest extends TestCase
{
    public function testPostQuerySuccess(): void
    {
        Http::fake([
            'example.com/*' => Http::response(['ok' => true, 'result' => 'Success'], 200),
        ]);

        $response = ParserMethods::postQuery('https://example.com/api', ['param' => 'value'], ['Header' => 'value']);

        $this->assertTrue($response['ok']);
        $this->assertEquals('Success', $response['result']);
    }

    public function testPostQueryFailure(): void
    {
        Http::fake([
            'example.com/*' => Http::response([], 500),
        ]);

        $response = ParserMethods::postQuery('https://example.com/api', ['param' => 'value'], ['Header' => 'value']);

        $this->assertFalse($response['ok']);
        $this->assertEquals('Ошибка отправки запроса', $response['result']);
    }

    public function testGetQuerySuccess(): void
    {
        Http::fake([
            'example.com/*' => Http::response(['ok' => true, 'result' => 'Success'], 200),
        ]);

        $response = ParserMethods::getQuery('https://example.com/api', ['param' => 'value'], ['Header' => 'value']);

        $this->assertTrue($response['ok']);
        $this->assertEquals('Success', $response['result']);
    }

    public function testGetQueryFailure(): void
    {
        Http::fake([
            'example.com/*' => Http::response([], 500),
        ]);

        $response = ParserMethods::getQuery('https://example.com/api', ['param' => 'value'], ['Header' => 'value']);

        $this->assertFalse($response['ok']);
        $this->assertEquals('Ошибка отправки запроса', $response['result']);
    }
}
