<?php

namespace Tests\Unit\Modules\Ok\Services;

use App\Modules\Ok\Services\OkAttachmentStorage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OkAttachmentStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_downloads_and_stores_a_file_with_extension_from_mime(): void
    {
        Http::fake(['*' => Http::response('bytes', 200, ['Content-Type' => 'image/jpeg; charset=binary'])]);

        $file = (new OkAttachmentStorage())->download('https://cdn.test/i?sig=1');

        $this->assertStringEndsWith('.jpg', $file['path']);
        $this->assertSame('image/jpeg', $file['mime']);
        Storage::disk('local')->assertExists($file['path']);
    }

    public function test_web_page_instead_of_a_file_is_a_failure(): void
    {
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html;charset=utf-8'])]);

        $this->assertNull((new OkAttachmentStorage())->download('https://ok.ru/video/1'));
    }

    public function test_http_error_is_a_failure(): void
    {
        Http::fake(['*' => Http::response('', 403)]);

        $this->assertNull((new OkAttachmentStorage())->download('https://cdn.test/1'));
    }

    public function test_network_error_is_a_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertNull((new OkAttachmentStorage())->download('https://cdn.test/1'));
    }

    public function test_store_keeps_the_original_name(): void
    {
        $file = (new OkAttachmentStorage())->store('bytes', 'application/pdf', 'a.pdf');

        $this->assertSame('a.pdf', $file['name']);
        Storage::disk('local')->assertExists($file['path']);
    }
}
