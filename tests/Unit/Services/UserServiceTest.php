<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService();
    }

    public function testCreateUser(): void
    {
        $user = $this->userService->createUser('John Doe', 'john@example.com', 'secret123');

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function testGetUserByEmail(): void
    {
        $createdUser = User::factory()->create([
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);

        $user = $this->userService->getUserByEmail('jane@example.com');

        $this->assertNotNull($user);
        $this->assertEquals('Jane Doe', $user->name);
    }

    public function testUpdateUserName(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);

        $updatedUser = $this->userService->updateUserName($user->id, 'New Name');

        $this->assertEquals('New Name', $updatedUser->name);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function testDeleteUser(): void
    {
        $user = User::factory()->create();

        $deletedUser = $this->userService->deleteUser($user->id);

        $this->assertNotNull($deletedUser);
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }
}
