<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\EditUser;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\ListUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $authUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authUser = User::factory()->create();
        $this->actingAs($this->authUser);
    }

    public function test_can_render_list_page(): void
    {
        Livewire::test(ListUsers::class)
            ->assertSuccessful();
    }

    public function test_list_page_shows_users(): void
    {
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$user]);
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateUser::class)
            ->assertSuccessful();
    }

    public function test_can_create_user(): void
    {
        Livewire::test(CreateUser::class)
            ->set('data.name', 'New Manager')
            ->set('data.email', 'manager@example.com')
            ->set('data.password', 'secret123')
            ->set('data.password_confirmation', 'secret123')
            ->set('data.role', 'manager')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'New Manager',
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);
    }

    public function test_create_requires_name_and_email(): void
    {
        Livewire::test(CreateUser::class)
            ->set('data.name', '')
            ->set('data.email', '')
            ->set('data.password', 'secret123')
            ->set('data.password_confirmation', 'secret123')
            ->call('create')
            ->assertHasFormErrors(['name', 'email']);
    }

    public function test_create_requires_matching_password_confirmation(): void
    {
        Livewire::test(CreateUser::class)
            ->set('data.name', 'User')
            ->set('data.email', 'user@example.com')
            ->set('data.password', 'secret123')
            ->set('data.password_confirmation', 'different')
            ->call('create')
            ->assertHasFormErrors(['password_confirmation']);
    }

    public function test_email_must_be_unique_on_create(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::test(CreateUser::class)
            ->set('data.name', 'Another')
            ->set('data.email', 'taken@example.com')
            ->set('data.password', 'secret123')
            ->set('data.password_confirmation', 'secret123')
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    public function test_can_render_edit_page(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_can_edit_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->set('data.name', 'New Name')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_edit_allows_empty_password(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['password' => '', 'password_confirmation' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'password' => $oldHash,
        ]);
    }

    public function test_edit_password_must_match_confirmation(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->set('data.password', 'newpassword')
            ->set('data.password_confirmation', 'different')
            ->call('save')
            ->assertHasFormErrors(['password_confirmation']);
    }

    public function test_can_delete_user_from_edit_page(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_manager_cannot_access_user_resource(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $this->assertFalse(\App\Modules\Admin\Filament\Resources\UserResource::canAccess());
    }

    public function test_admin_can_access_user_resource(): void
    {
        $this->assertTrue(\App\Modules\Admin\Filament\Resources\UserResource::canAccess());
    }

    public function test_role_badge_shown_in_table(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $manager = User::factory()->manager()->create(['name' => 'Manager User']);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin, $manager]);
    }
}
