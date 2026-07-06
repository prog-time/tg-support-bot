<?php

namespace Tests\Unit\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\AvitoIntegrationPage;
use App\Models\User;
use App\Modules\Admin\Services\AvitoVerificationService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Unit-level tests for the AvitoIntegrationPage Livewire component.
 *
 * Manages the paid Avito module's Messenger API credentials and runs a
 * verify-before-save flow: connect() checks the credentials against the Avito
 * API and auto-captures the account id (user_id). Admin-only. The subscription
 * license key lives on the «Основные» screen.
 */
class AvitoIntegrationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /** Fake a successful Avito OAuth token + accounts/self round-trip. */
    private function fakeAvitoOk(string $accountId = '777', string $name = 'Test Shop'): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['id' => $accountId, 'name' => $name], 200),
        ]);
    }

    public function test_mount_loads_values_and_secret_flags(): void
    {
        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('avito.client_id')->andReturn('cid');
        $mock->shouldReceive('get')->with('avito.client_secret')->andReturn('csecret');
        $mock->shouldReceive('get')->with('avito.base_url')->andReturn('https://api.avito.ru');
        $mock->shouldReceive('get')->with('avito.webhook_secret')->andReturn('whs');
        $mock->shouldReceive('get')->with('avito.user_id')->andReturn('777');
        $mock->shouldReceive('has')->with('avito.client_secret')->andReturn(true);
        $mock->shouldReceive('has')->with('avito.webhook_secret')->andReturn(true);

        $component = new AvitoIntegrationPage();
        $component->mount($mock);

        $this->assertSame('cid', $component->client_id);
        $this->assertSame('777', $component->user_id);
        $this->assertTrue($component->hasClientSecret);
    }

    public function test_connect_verifies_captures_user_id_and_persists(): void
    {
        $this->fakeAvitoOk('777');

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('set')->with('avito.client_id', 'cid')->once();
        $mock->shouldReceive('set')->with('avito.base_url', 'https://api.avito.ru')->once();
        $mock->shouldReceive('set')->with('avito.client_secret', 'csecret')->once();
        $mock->shouldReceive('set')->with('avito.webhook_secret', 'whs')->once();
        $mock->shouldReceive('set')->with('avito.user_id', '777')->once();
        $mock->shouldReceive('has')->with('avito.client_secret')->andReturn(true);
        $mock->shouldReceive('has')->with('avito.webhook_secret')->andReturn(true);

        $component = new AvitoIntegrationPage();
        $component->client_id = 'cid';
        $component->client_secret = 'csecret';
        $component->base_url = 'https://api.avito.ru';
        $component->webhook_secret = 'whs';
        $component->connect($mock, app(AvitoVerificationService::class));

        $this->assertTrue($component->verifySuccess);
        $this->assertSame('777', $component->user_id);
        $this->assertEmpty($component->formErrors);
    }

    public function test_connect_persists_nothing_when_verification_fails(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldNotReceive('set');

        $component = new AvitoIntegrationPage();
        $component->client_id = 'cid';
        $component->client_secret = 'wrong';
        $component->connect($mock, app(AvitoVerificationService::class));

        $this->assertFalse($component->verifySuccess);
        $this->assertNotNull($component->verifyMessage);
        $this->assertSame('', $component->user_id);
    }

    public function test_connect_uses_stored_secret_when_field_blank(): void
    {
        $this->fakeAvitoOk('888');

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        // Blank secret field → resolved from storage for verification.
        $mock->shouldReceive('get')->with('avito.client_secret')->andReturn('stored-secret');
        $mock->shouldReceive('set')->with('avito.client_id', 'cid')->once();
        $mock->shouldReceive('set')->with('avito.base_url', 'https://api.avito.ru')->once();
        $mock->shouldNotReceive('set')->with('avito.client_secret', Mockery::any());
        $mock->shouldReceive('set')->with('avito.webhook_secret', '')->once();
        $mock->shouldReceive('set')->with('avito.user_id', '888')->once();
        $mock->shouldReceive('has')->andReturn(true);

        $component = new AvitoIntegrationPage();
        $component->hasClientSecret = true; // already stored
        $component->client_id = 'cid';
        $component->client_secret = '';
        $component->base_url = 'https://api.avito.ru';
        $component->connect($mock, app(AvitoVerificationService::class));

        $this->assertTrue($component->verifySuccess);
        $this->assertSame('888', $component->user_id);
    }

    public function test_connect_requires_client_id_and_secret(): void
    {
        Http::fake();

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldNotReceive('set');

        $component = new AvitoIntegrationPage();
        $component->client_id = '';
        $component->client_secret = '';
        $component->connect($mock, app(AvitoVerificationService::class));

        $this->assertFalse($component->verifySuccess);
        $this->assertArrayHasKey('client_id', $component->formErrors);
        $this->assertArrayHasKey('client_secret', $component->formErrors);
        Http::assertNothingSent();
    }

    public function test_connect_is_ignored_for_non_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        Http::fake();

        /** @var \Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldNotReceive('set');

        $component = new AvitoIntegrationPage();
        $component->client_id = 'cid';
        $component->client_secret = 'csecret';
        $component->connect($mock, app(AvitoVerificationService::class));

        $this->assertFalse($component->verifySuccess);
        Http::assertNothingSent();
    }
}
