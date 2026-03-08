<?php

namespace Tests\Unit\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_user_successfully(): void
    {
        $this->artisan('app:create-admin-user')
            ->expectsQuestion('Name', 'John Admin')
            ->expectsQuestion('Email', 'john@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'secret123')
            ->expectsQuestion('Confirm password', 'secret123')
            ->expectsChoice('Role', UserRole::Admin->label(), [UserRole::Admin->label(), UserRole::Manager->label()])
            ->expectsOutput('User "John Admin" (john@example.com) created with role "Администратор".')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name' => 'John Admin',
            'email' => 'john@example.com',
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_creates_manager_user_successfully(): void
    {
        $this->artisan('app:create-admin-user')
            ->expectsQuestion('Name', 'Jane Manager')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'secret123')
            ->expectsQuestion('Confirm password', 'secret123')
            ->expectsChoice('Role', UserRole::Manager->label(), [UserRole::Admin->label(), UserRole::Manager->label()])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'role' => UserRole::Manager->value,
        ]);
    }

    public function test_fails_when_email_already_taken(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('app:create-admin-user')
            ->expectsQuestion('Name', 'Duplicate')
            ->expectsQuestion('Email', 'taken@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'secret123')
            ->expectsQuestion('Confirm password', 'secret123')
            ->expectsChoice('Role', UserRole::Admin->label(), [UserRole::Admin->label(), UserRole::Manager->label()])
            ->expectsOutput('A user with this email already exists.')
            ->assertExitCode(1);
    }

    public function test_fails_when_passwords_do_not_match(): void
    {
        $this->artisan('app:create-admin-user')
            ->expectsQuestion('Name', 'Someone')
            ->expectsQuestion('Email', 'someone@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'secret123')
            ->expectsQuestion('Confirm password', 'wrong456')
            ->expectsChoice('Role', UserRole::Admin->label(), [UserRole::Admin->label(), UserRole::Manager->label()])
            ->expectsOutput('Passwords do not match.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'someone@example.com']);
    }

    public function test_fails_when_password_too_short(): void
    {
        $this->artisan('app:create-admin-user')
            ->expectsQuestion('Name', 'Short')
            ->expectsQuestion('Email', 'short@example.com')
            ->expectsQuestion('Password (min 8 characters)', '123')
            ->expectsQuestion('Confirm password', '123')
            ->expectsChoice('Role', UserRole::Admin->label(), [UserRole::Admin->label(), UserRole::Manager->label()])
            ->expectsOutput('Password must be at least 8 characters.')
            ->assertExitCode(1);
    }
}
