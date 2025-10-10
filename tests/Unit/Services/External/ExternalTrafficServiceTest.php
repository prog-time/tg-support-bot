<?php

namespace Tests\Unit\Services\External;

use App\DTOs\External\ExternalListMessageDto;
use App\Models\ExternalUser;
use App\Services\External\ExternalTrafficService;
use Mockery;
use Tests\TestCase;

class ExternalTrafficServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_list_returns_error_when_external_user_not_found(): void
    {
        $filterDto = ExternalListMessageDto::from([
            'external_id' => 'not_exist',
            'source' => 'live_chat',
        ]);

        $externalUserMock = Mockery::mock('overload:' . ExternalUser::class);
        $externalUserMock->shouldReceive('where->first')->andReturn(null);

        $service = new ExternalTrafficService();
        $result = $service->list($filterDto);

        $this->assertIsArray($result);
        $this->assertFalse($result['status']);
        $this->assertEquals('Чат не найден!', $result['error']);
    }
}
