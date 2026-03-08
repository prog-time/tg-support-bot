<?php

namespace Tests\Feature\Admin;

use App\Models\ExternalSource;
use App\Models\ExternalSourceAccessTokens;
use App\Models\User;
use App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages\CreateExternalSource;
use App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages\EditExternalSource;
use App\Modules\Admin\Filament\Resources\ExternalSourceResource\Pages\ListExternalSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExternalSourceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_can_render_list_page(): void
    {
        Livewire::test(ListExternalSources::class)
            ->assertSuccessful();
    }

    public function test_list_page_shows_sources(): void
    {
        $source = ExternalSource::create(['name' => 'Test Source']);

        Livewire::test(ListExternalSources::class)
            ->assertCanSeeTableRecords([$source]);
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateExternalSource::class)
            ->assertSuccessful();
    }

    public function test_creates_source_and_generates_token(): void
    {
        Livewire::test(CreateExternalSource::class)
            ->set('data.name', 'New Source')
            ->set('data.webhook_url', 'https://example.com/hook')
            ->call('create')
            ->assertHasNoErrors();

        $source = ExternalSource::where('name', 'New Source')->first();

        $this->assertNotNull($source);
        $this->assertDatabaseHas('external_source_access_tokens', [
            'external_source_id' => $source->id,
            'active' => true,
        ]);
    }

    public function test_can_render_edit_page(): void
    {
        $source = ExternalSource::create(['name' => 'Edit Source']);

        Livewire::test(EditExternalSource::class, ['record' => $source->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_saving_edit_regenerates_token(): void
    {
        $source = ExternalSource::create(['name' => 'My Source']);

        ExternalSourceAccessTokens::create([
            'external_source_id' => $source->id,
            'token' => 'old-token-value-that-is-exactly-sixty-characters-long-here!!',
            'active' => true,
        ]);

        Livewire::test(EditExternalSource::class, ['record' => $source->getRouteKey()])
            ->fillForm(['name' => 'My Source Updated'])
            ->call('save')
            ->assertHasNoErrors();

        $newToken = ExternalSourceAccessTokens::where('external_source_id', $source->id)
            ->where('active', true)
            ->value('token');

        $this->assertNotEquals('old-token-value-that-is-exactly-sixty-characters-long-here!!', $newToken);
    }

    public function test_refresh_token_action_generates_new_token(): void
    {
        $source = ExternalSource::create(['name' => 'Token Source']);

        ExternalSourceAccessTokens::create([
            'external_source_id' => $source->id,
            'token' => 'old-token-value-that-is-exactly-sixty-characters-long-here!!',
            'active' => true,
        ]);

        Livewire::test(EditExternalSource::class, ['record' => $source->getRouteKey()])
            ->callAction('refreshToken')
            ->assertHasNoErrors()
            ->assertNotified('Токен обновлён');

        $newToken = ExternalSourceAccessTokens::where('external_source_id', $source->id)
            ->where('active', true)
            ->value('token');

        $this->assertNotEquals('old-token-value-that-is-exactly-sixty-characters-long-here!!', $newToken);
    }

    public function test_can_delete_source_from_list(): void
    {
        $source = ExternalSource::create(['name' => 'Delete Me']);

        Livewire::test(ListExternalSources::class)
            ->callTableAction('delete', $source)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('external_sources', ['id' => $source->id]);
    }
}
