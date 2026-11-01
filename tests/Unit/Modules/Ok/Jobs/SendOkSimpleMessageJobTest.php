<?php

namespace Tests\Unit\Modules\Ok\Jobs;

use App\Modules\Ok\Api\OkMethods;
use App\Modules\Ok\DTOs\OkAnswerDto;
use App\Modules\Ok\DTOs\OkTextMessageDto;
use App\Modules\Ok\Jobs\SendOkSimpleMessageJob;
use Mockery;
use Tests\TestCase;

class SendOkSimpleMessageJobTest extends TestCase
{
    public function test_sends_the_message_without_the_internal_method_field(): void
    {
        $methods = Mockery::mock(OkMethods::class);
        $methods->shouldReceive('sendQuery')
            ->once()
            ->with('sendMessage', ['chat_id' => 'chat:C1', 'text' => 'Привет'])
            ->andReturn(OkAnswerDto::fromData([]));

        (new SendOkSimpleMessageJob(OkTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => 'chat:C1',
            'text' => 'Привет',
        ])))->handle($methods);
    }
}
