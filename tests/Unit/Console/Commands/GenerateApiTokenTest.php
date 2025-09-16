<?php

namespace Tests\Unit\Console\Commands;

use App\Models\ExternalSource;
use App\Models\ExternalSourceAccessTokens;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateApiTokenTest extends TestCase
{
    private string $source;

    private string $url;

    private ExternalSource $sourceModel;

    public function setUp(): void
    {
        parent::setUp();

        $this->source = 'phpunit-source';
        $this->url = 'https://example.com/hook';

        if (ExternalSource::where(['name' => $this->source])->exists()) {
            ExternalSource::where(['name' => $this->source])->delete();
        }

        $this->sourceModel = ExternalSource::create(['name' => $this->source]);
    }

    public function test_successful_token_generation(): void
    {
        ExternalSourceAccessTokens::create([
            'external_source_id' => $this->sourceModel->id,
            'token' => Str::random(32),
        ]);

        // создание токена
        $exitCode = Artisan::call('app:generate-token', [
            'source' => $this->source,
            'hook_url' => $this->url,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('external_sources', ['name' => $this->source]);

        // обновление токена
        $exitCode = Artisan::call('app:generate-token', [
            'source' => $this->source,
            'hook_url' => $this->url,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('external_sources', ['name' => $this->source]);
    }

    public function test_invalid_url(): void
    {
        $exitCode = Artisan::call('app:generate-token', [
            'source' => 'invalid-url-source',
            'hook_url' => 'invalid-url',
        ]);

        $this->assertEquals(1, $exitCode);
    }
}
