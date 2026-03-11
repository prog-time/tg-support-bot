<?php

namespace Tests\Unit\Modules\Telegram\Services\TgMax;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Max\Actions\UploadFileMax;
use App\Modules\Max\Jobs\SendMaxMessageJob;
use App\Modules\Telegram\Services\TgMax\TgMaxMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

class TgMaxMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Message::truncate();
        BotUser::truncate();

        $this->botUser = BotUser::getUserByChatId(time(), 'max');
        $this->botUser->topic_id = 123;
        $this->botUser->save();

        $this->basicPayload = TelegramUpdateDto_VKMock::getDtoParams($this->botUser);
    }

    public function test_send_text_message(): void
    {
        $dto = TelegramUpdateDto_VKMock::getDto($this->basicPayload);

        (new TgMaxMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendMaxMessageJob::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals((int) $this->botUser->chat_id, $jobData->queryParams->user_id);
        $this->assertEquals($dto->text, $jobData->queryParams->text);
        $this->assertEquals($dto, $jobData->updateDto);
    }

    public function test_send_document_with_caption(): void
    {
        $fileToken = 'max_file_token_xyz';

        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => ['file_id' => 'doc_file_id', 'file_path' => 'documents/file.pdf'],
            ], 200),
        ]);

        $this->mock(UploadFileMax::class, function ($mock) use ($fileToken) {
            $mock->shouldReceive('execute')
                ->once()
                ->with(\Mockery::type('string'), 'file.pdf', 'file')
                ->andReturn($fileToken);
        });

        $payload = $this->basicPayload;
        unset($payload['message']['text']);
        $payload['message']['caption'] = 'Подпись к документу';
        $payload['message']['document'] = ['file_id' => 'doc_file_id', 'file_name' => 'file.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1024];

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgMaxMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendMaxMessageJob::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];
        $this->assertEquals('sendFile', $jobData->queryParams->methodQuery);
        $this->assertEquals('Подпись к документу', $jobData->queryParams->text);
        $this->assertEquals($fileToken, $jobData->queryParams->file_token);
    }

    public function test_send_photo_with_caption(): void
    {
        $fileToken = 'max_image_token_abc';

        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => ['file_id' => 'photo_file_id', 'file_path' => 'photos/file_123.jpg'],
            ], 200),
        ]);

        $this->mock(UploadFileMax::class, function ($mock) use ($fileToken) {
            $mock->shouldReceive('execute')
                ->once()
                ->with(\Mockery::type('string'), \Mockery::type('string'), 'image')
                ->andReturn($fileToken);
        });

        $payload = $this->basicPayload;
        unset($payload['message']['text']);
        $payload['message']['photo'] = [['file_id' => 'photo_file_id', 'file_size' => 1024]];
        $payload['message']['caption'] = 'Подпись к фото';

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgMaxMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendMaxMessageJob::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];
        $this->assertEquals('sendImage', $jobData->queryParams->methodQuery);
        $this->assertEquals($fileToken, $jobData->queryParams->file_token);
        $this->assertEquals('Подпись к фото', $jobData->queryParams->text);
        $this->assertEquals((int) $this->botUser->chat_id, $jobData->queryParams->user_id);
    }

    public function test_send_document(): void
    {
        $fileToken = 'max_file_token_xyz';

        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => ['file_id' => 'doc_file_id', 'file_path' => 'documents/region_city.txt'],
            ], 200),
        ]);

        $this->mock(UploadFileMax::class, function ($mock) use ($fileToken) {
            $mock->shouldReceive('execute')
                ->once()
                ->with(\Mockery::type('string'), 'region_city.txt', 'file')
                ->andReturn($fileToken);
        });

        $payload = $this->basicPayload;
        unset($payload['message']['text']);
        $payload['message']['document'] = ['file_id' => 'doc_file_id', 'file_name' => 'region_city.txt', 'mime_type' => 'text/plain', 'file_size' => 427];

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgMaxMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendMaxMessageJob::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];
        $this->assertEquals('sendFile', $jobData->queryParams->methodQuery);
        $this->assertEquals($fileToken, $jobData->queryParams->file_token);
        $this->assertEquals((int) $this->botUser->chat_id, $jobData->queryParams->user_id);
    }

    public function test_no_job_dispatched_when_no_text_and_no_caption(): void
    {
        $payload = $this->basicPayload;
        unset($payload['message']['text']);

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgMaxMessageService($dto))->handleUpdate();

        Queue::assertNothingPushed();
    }
}
