<?php

namespace Tests\Unit\Modules\Max\Actions;

use App\Modules\Max\Actions\UploadFileMax;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UploadFileMaxTest extends TestCase
{
    public function test_returns_null_when_telegram_download_fails(): void
    {
        $tgFileUrl = 'https://api.telegram.org/file/botTOKEN/photos/missing.jpg';

        Http::fake([
            $tgFileUrl => Http::response('Not Found', 404),
        ]);

        $result = app(UploadFileMax::class)->execute($tgFileUrl, 'missing.jpg', 'image');

        $this->assertNull($result);
    }
}
