<?php

namespace Tests\Feature\Admin;

use App\Models\BotUser;
use App\Models\User;
use App\Modules\Admin\Filament\Resources\BotUserResource\Pages\ListBotUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BotUserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_can_render_list_page(): void
    {
        BotUser::create(['chat_id' => 1, 'platform' => 'telegram']);
        BotUser::create(['chat_id' => 2, 'platform' => 'vk']);

        Livewire::test(ListBotUsers::class)
            ->assertSuccessful();
    }

    public function test_list_page_shows_bot_users(): void
    {
        $user = BotUser::create(['chat_id' => 42, 'platform' => 'telegram']);

        Livewire::test(ListBotUsers::class)
            ->assertCanSeeTableRecords([$user]);
    }

    public function test_can_ban_user(): void
    {
        $user = BotUser::create(['chat_id' => 100, 'platform' => 'telegram', 'is_banned' => false]);

        Livewire::test(ListBotUsers::class)
            ->callTableAction('ban', $user)
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->isBanned());
        $this->assertNotNull($user->fresh()->banned_at);
    }

    public function test_can_unban_user(): void
    {
        $user = BotUser::create([
            'chat_id'   => 100,
            'platform'  => 'telegram',
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        Livewire::test(ListBotUsers::class)
            ->callTableAction('unban', $user)
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->isBanned());
        $this->assertNull($user->fresh()->banned_at);
    }

    public function test_ban_action_hidden_for_already_banned_user(): void
    {
        $user = BotUser::create([
            'chat_id'   => 100,
            'platform'  => 'telegram',
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        Livewire::test(ListBotUsers::class)
            ->assertTableActionHidden('ban', $user);
    }

    public function test_unban_action_hidden_for_active_user(): void
    {
        $user = BotUser::create(['chat_id' => 100, 'platform' => 'telegram', 'is_banned' => false]);

        Livewire::test(ListBotUsers::class)
            ->assertTableActionHidden('unban', $user);
    }
}
