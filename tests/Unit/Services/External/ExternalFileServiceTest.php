<?php

namespace Tests\Unit\Services\External;

use App\Services\External\ExternalFileService;
use Illuminate\Http\UploadedFile;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class ExternalFileServiceTest extends TestCase
{
    private mixed $source;

    private mixed $external_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->source = config('testing.external.source');
        $this->external_id = config('testing.external.external_id');
    }

    public function test_send_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tmpFile, str_repeat('x', 1000)); // 1 KB

        $file = new UploadedFile(
            $tmpFile,
            'image.jpg',
            'image/jpeg',
            null,
            true // тестовый файл
        );

        $dataMessage = [
            'source' => $this->source,
            'external_id' => $this->external_id,
            'text' => 'Тестовое сообщение',
            'uploaded_file' => $file,
        ];

        $externalDto = ExternalMessageDtoMock::getDto($dataMessage);

        (new ExternalFileService($externalDto))->handleUpdate();
    }
}
