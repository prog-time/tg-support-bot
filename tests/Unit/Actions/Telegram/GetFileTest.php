<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\GetFile;
use Tests\TestCase;

class GetFileTest extends TestCase
{
    private string $photoFileId;

    public function setUp(): void
    {
        parent::setUp();

        $this->photoFileId = config('testing.tg_file.photo');
    }

    public function test_get_file(): void
    {
        $result = GetFile::execute($this->photoFileId);

        $this->assertTrue($result->ok);
        $this->assertNotEmpty($result->rawData);
    }
}
