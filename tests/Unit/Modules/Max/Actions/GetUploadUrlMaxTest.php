<?php

namespace Tests\Unit\Modules\Max\Actions;

use App\Modules\Max\Actions\GetUploadUrlMax;
use MaxBotApi\DTO\UploadResult;
use Tests\TestCase;

class GetUploadUrlMaxTest extends TestCase
{
    public function test_returns_upload_result_on_success(): void
    {
        $expected = new UploadResult('https://upload.max.ru/files/upload', 'token123');

        /** @var GetUploadUrlMax&\Mockery\MockInterface $action */
        $action = \Mockery::mock(GetUploadUrlMax::class)->makePartial();
        $action->shouldAllowMockingProtectedMethods();
        $action->shouldReceive('fetchUploadUrl')
            ->with('image')
            ->andReturn($expected);

        $result = $action->execute('image');

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertSame('https://upload.max.ru/files/upload', $result->url);
        $this->assertSame('token123', $result->token);
    }

    public function test_returns_null_when_exception_is_thrown(): void
    {
        /** @var GetUploadUrlMax&\Mockery\MockInterface $action */
        $action = \Mockery::mock(GetUploadUrlMax::class)->makePartial();
        $action->shouldAllowMockingProtectedMethods();
        /** @var \Mockery\Expectation $expectation */
        $expectation = $action->shouldReceive('fetchUploadUrl');
        $expectation->andThrow(new \RuntimeException('Network error'));

        $result = $action->execute('image');

        $this->assertNull($result);
    }

    public function test_passes_type_to_fetch_upload_url(): void
    {
        $expected = new UploadResult('https://upload.max.ru/files/upload', null);

        /** @var GetUploadUrlMax&\Mockery\MockInterface $action */
        $action = \Mockery::mock(GetUploadUrlMax::class)->makePartial();
        $action->shouldAllowMockingProtectedMethods();
        $action->shouldReceive('fetchUploadUrl')
            ->with('video')
            ->once()
            ->andReturn($expected);

        $result = $action->execute('video');

        $this->assertInstanceOf(UploadResult::class, $result);
    }
}
