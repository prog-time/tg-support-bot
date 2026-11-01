<?php

namespace Tests\Feature\Ai;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Admin\Services\ChannelStatusService;
use App\Services\Settings\SettingKeyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiBotRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_route_is_gone(): void
    {
        $this->postJson('/api/ai-bot/webhook', ['update_id' => 1])->assertNotFound();
    }

    public function test_integration_page_is_gone(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get('/admin/settings/integrations/telegram_ai')->assertNotFound();
    }

    public function test_set_webhook_command_is_gone_and_retire_command_exists(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertNotContains('ai-bot:set-webhook', $commands);
        $this->assertContains('ai-bot:retire', $commands);
    }

    public function test_settings_registry_and_channel_status_have_no_ai_bot(): void
    {
        $this->assertSame([], array_filter(
            SettingKeyRegistry::keys(),
            fn (string $key) => str_starts_with($key, 'telegram_ai.')
        ));
        $this->assertArrayNotHasKey('telegram_ai', app(ChannelStatusService::class)->all());
    }
}
