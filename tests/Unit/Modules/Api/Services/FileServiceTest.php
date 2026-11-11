<?php

namespace Tests\Unit\Modules\Api\Services;

use App\Modules\Api\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class FileServiceTest extends TestCase
{
    use RefreshDatabase;

    private FileService $service;

    private string $tgToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tgToken = '9MUF3Q6Bq88kFBN1';
        app(\App\Services\Settings\SettingsService::class)->set('telegram.token', $this->tgToken);

        $this->service = new FileService();
    }

    public function test_download_file_success(): void
    {
        Http::fake([
            'https://api.telegram.org/bot' . $this->tgToken . '/getFile*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'images/picture.jpg'],
            ], 200),
            'https://api.telegram.org/file/bot' . $this->tgToken . '/images/picture.jpg' => Http::response('IMAGE_CONTENT', 200),
        ]);

        $response = $this->service->downloadFile('456');

        $this->assertInstanceOf(LaravelResponse::class, $response);
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertEquals('attachment; filename="picture.jpg"', $response->headers->get('Content-Disposition'));
        $this->assertEquals('IMAGE_CONTENT', $response->getContent());
    }

    public function test_stream_file_success(): void
    {
        Http::fake([
            'https://api.telegram.org/bot' . $this->tgToken . '/getFile*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'documents/test.pdf'],
            ], 200),
            'https://api.telegram.org/file/bot' . $this->tgToken . '/documents/test.pdf' => Http::response('PDF_CONTENT', 200),
        ]);

        $response = $this->service->streamFile('123');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertEquals('inline; filename="test.pdf"', $response->headers->get('Content-Disposition'));
    }

    public function test_download_file_goes_through_telegram_proxy(): void
    {
        config(['traffic_source.telegram.proxy' => 'socks5h://10.0.0.1:1080']);
        $proxies = [];

        Http::fake(function ($request, array $options) use (&$proxies) {
            $proxies[] = $options['proxy'] ?? null;

            return str_contains($request->url(), '/getFile')
                ? Http::response(['ok' => true, 'result' => ['file_path' => 'images/picture.jpg']])
                : Http::response('IMAGE_CONTENT');
        });

        $this->service->downloadFile('456');

        $this->assertSame(['socks5h://10.0.0.1:1080', 'socks5h://10.0.0.1:1080'], $proxies);
    }
}
