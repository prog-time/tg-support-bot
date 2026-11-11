<?php

namespace Tests\Unit\Modules\Telegram\Api;

use App\Modules\Telegram\Api\ParserMethods;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParserMethodsTest extends TestCase
{
    private string $url;

    public function setUp(): void
    {
        parent::setUp();

        $this->url = 'https://example.com/api';
    }

    public function test_post_query_success(): void
    {
        Http::fake([
            $this->url => Http::response(['ok' => true, 'result' => 'Success'], 200),
        ]);

        $response = ParserMethods::postQuery($this->url, ['ok' => true, 'result' => 'Success'], ['Header' => 'value']);

        $this->assertTrue($response['ok']);
        $this->assertEquals('Success', $response['result']);
    }

    public function test_post_query_failure(): void
    {
        Http::fake([
            $this->url => Http::response([], 500),
        ]);

        $response = ParserMethods::postQuery($this->url, ['param' => 'value'], ['Header' => 'value']);

        $this->assertFalse($response['ok']);
        $this->assertEquals('Request caused an error', $response['result']);
    }

    public function test_get_query_success(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => 'Success'], 200),
        ]);

        $response = ParserMethods::getQuery($this->url, ['ok' => true, 'result' => 'Success'], ['Header' => 'value']);

        $this->assertTrue($response['ok']);
        $this->assertEquals('Success', $response['result']);
    }

    public function test_get_query_failure(): void
    {
        Http::fake([
            $this->url => Http::response([], 500),
        ]);

        $response = ParserMethods::getQuery($this->url, ['param' => 'value'], ['Header' => 'value']);

        $this->assertFalse($response['ok']);
        $this->assertEquals('Request caused an error', $response['result']);
    }

    public function test_attach_query_with_valid_file(): void
    {
        Http::fake([
            $this->url => Http::response(['ok' => true, 'result' => 'file uploaded'], 200),
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 1024); // 1 KB

        $response = ParserMethods::attachQuery($this->url, [
            'uploaded_file' => $file,
        ]);

        $this->assertTrue($response['ok']);
        $this->assertEquals('file uploaded', $response['result']);
    }

    public function test_attach_query_throws_exception_on_empty_file(): void
    {
        $file = UploadedFile::fake()->create('empty.pdf', 0);

        $response = ParserMethods::attachQuery($this->url, [
            'uploaded_file' => $file,
        ]);

        $this->assertFalse($response['ok']);
        $this->assertEquals(500, $response['response_code']);
        $this->assertStringContainsString('File is empty and cannot be sent', $response['result']);
    }

    public function test_post_query_uses_telegram_proxy(): void
    {
        config(['traffic_source.telegram.proxy' => 'socks5h://10.0.0.1:1080']);
        $sentOptions = [];

        Http::fake(function ($request, array $options) use (&$sentOptions) {
            $sentOptions = $options;

            return Http::response(['ok' => true, 'result' => []]);
        });

        ParserMethods::postQuery('https://api.telegram.org/botX/sendMessage', ['text' => 'hi']);

        $this->assertSame('socks5h://10.0.0.1:1080', $sentOptions['proxy'] ?? null);
    }

    public function test_post_query_without_proxy_sends_no_proxy_option(): void
    {
        config(['traffic_source.telegram.proxy' => null]);
        $sentOptions = [];

        Http::fake(function ($request, array $options) use (&$sentOptions) {
            $sentOptions = $options;

            return Http::response(['ok' => true, 'result' => []]);
        });

        ParserMethods::postQuery('https://api.telegram.org/botX/sendMessage', ['text' => 'hi']);

        $this->assertArrayNotHasKey('proxy', $sentOptions);
    }

    public function test_attach_query_uses_telegram_proxy(): void
    {
        config(['traffic_source.telegram.proxy' => 'http://10.0.0.1:3128']);
        $sentOptions = [];

        Http::fake(function ($request, array $options) use (&$sentOptions) {
            $sentOptions = $options;

            return Http::response(['ok' => true, 'result' => []]);
        });

        $tempPath = tempnam(sys_get_temp_dir(), 'tgproxy') . '.txt';
        file_put_contents($tempPath, 'content');

        ParserMethods::attachQuery('https://api.telegram.org/botX/sendDocument', ['uploaded_file_path' => $tempPath, 'chat_id' => 1]);

        $this->assertSame('http://10.0.0.1:3128', $sentOptions['proxy'] ?? null);
    }
}
