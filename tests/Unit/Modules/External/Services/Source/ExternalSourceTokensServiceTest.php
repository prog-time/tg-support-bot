<?php

namespace Tests\Unit\Modules\External\Services\Source;

use App\Models\ExternalSource;
use App\Models\ExternalSourceAccessTokens;
use App\Modules\External\Services\Source\ExternalSourceTokensService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalSourceTokensServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExternalSourceTokensService $service;

    private ExternalSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = ExternalSource::create(['name' => 'test_source']);
        $this->service = app(ExternalSourceTokensService::class);
    }

    public function test_creates_token_for_new_source(): void
    {
        $this->service->setAccessToken($this->source->id);

        $this->assertDatabaseHas('external_source_access_tokens', [
            'external_source_id' => $this->source->id,
            'active' => 1,
        ]);

        $token = ExternalSourceAccessTokens::where('external_source_id', $this->source->id)->first();
        $this->assertEquals(60, strlen($token->token));
    }

    public function test_updates_token_for_existing_source(): void
    {
        $this->service->setAccessToken($this->source->id);

        $firstToken = ExternalSourceAccessTokens::where('external_source_id', $this->source->id)->value('token');

        $this->service->setAccessToken($this->source->id);

        $secondToken = ExternalSourceAccessTokens::where('external_source_id', $this->source->id)->value('token');

        $this->assertNotEquals($firstToken, $secondToken);
    }

    public function test_throws_exception_when_source_not_found(): void
    {
        $this->expectException(\Throwable::class);

        $this->service->setAccessToken(999999);
    }
}
