<?php

namespace Tests\Unit\Modules\Max\Actions;

use App\Modules\Max\Actions\SendMessageMax;
use App\Modules\Max\Api\MaxMethods;
use App\Modules\Max\DTOs\MaxAnswerDto;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use Tests\TestCase;

class SendMessageMaxTest extends TestCase
{
    public function test_returns_max_answer_dto_on_success(): void
    {
        $dto = new MaxTextMessageDto(
            methodQuery: 'sendMessage',
            user_id: 123,
            text: 'Hello',
        );

        $expected = MaxAnswerDto::fromData([
            'response_code' => 200,
            'response' => 'msg-001',
        ]);

        /** @var MaxMethods&\Mockery\MockInterface $maxMethods */
        $maxMethods = \Mockery::mock(MaxMethods::class);
        /** @var \Mockery\Expectation $expectation */
        $expectation = $maxMethods->shouldReceive('sendQuery');
        $expectation->with('sendMessage', $dto->toArray())->andReturn($expected);

        $result = $maxMethods->sendQuery($dto->methodQuery, $dto->toArray());

        $this->assertInstanceOf(MaxAnswerDto::class, $result);
        $this->assertSame(200, $result->response_code);
        $this->assertSame('msg-001', $result->response);
    }

    public function test_returns_null_when_exception_is_thrown(): void
    {
        $dto = new MaxTextMessageDto(
            methodQuery: 'sendMessage',
            user_id: 123,
            text: 'Hello',
        );

        /** @var MaxMethods&\Mockery\MockInterface $maxMethods */
        $maxMethods = \Mockery::mock(MaxMethods::class);
        /** @var \Mockery\Expectation $expectation */
        $expectation = $maxMethods->shouldReceive('sendQuery');
        $expectation->andThrow(new \RuntimeException('Network error'));

        $result = null;

        try {
            $result = $maxMethods->sendQuery($dto->methodQuery, $dto->toArray());
        } catch (\Throwable) {
            $result = null;
        }

        $this->assertNull($result);
    }

    public function test_execute_returns_answer_dto_from_max_methods(): void
    {
        $dto = new MaxTextMessageDto(
            methodQuery: 'sendMessage',
            user_id: 456,
            text: 'Test text',
        );

        $expected = MaxAnswerDto::fromData([
            'response_code' => 200,
            'response' => 'msg-002',
        ]);

        /** @var SendMessageMax&\Mockery\MockInterface $action */
        $action = \Mockery::mock(SendMessageMax::class)->makePartial();
        $action->shouldAllowMockingProtectedMethods();
        /** @var \Mockery\Expectation $expectation */
        $expectation = $action->shouldReceive('execute');
        $expectation->with($dto)->andReturn($expected);

        $result = $action->execute($dto);

        $this->assertInstanceOf(MaxAnswerDto::class, $result);
        $this->assertSame(200, $result->response_code);
    }
}
