<?php

namespace Tests\Unit\Services\External;

use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\Message;
use App\Services\External\ExternalFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class ExternalFileServiceTest extends TestCase
{
    private mixed $source;

    private mixed $external_id;

    private BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->source = config('testing.external.source');
        $this->external_id = config('testing.external.external_id');

        $externalUser = ExternalUser::firstOrCreate([
            'external_id' => $this->external_id,
            'source' => $this->source,
        ]);

        $this->botUser = BotUser::firstOrCreate([
            'chat_id' => $externalUser->id,
            'platform' => $this->source,
        ]);
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

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendExternalTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $job->botUserId);
        $this->assertEquals('sendDocument', $job->queryParams->methodQuery);
        $this->assertEquals('private', $job->queryParams->typeSource);
        $this->assertEquals($job->queryParams->message_thread_id, $this->botUser->topic_id);
    }
}
