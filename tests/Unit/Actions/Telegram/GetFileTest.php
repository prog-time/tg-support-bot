<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\GetFile;
use Tests\TestCase;

class GetFileTest extends TestCase
{
    public function test_get_file(): void
    {
        $fileId = 'AQADAgADsbQxG5utIUkACAMAA29h6lQABGVsNy9gzekINgQ';

        $result = GetFile::execute($fileId);

        $this->assertTrue($result->ok);
        $this->assertNotEmpty($result->rawData);
    }
}
