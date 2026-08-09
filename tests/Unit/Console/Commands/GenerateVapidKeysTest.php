<?php

namespace Tests\Unit\Console\Commands;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsSettings;

class GenerateVapidKeysTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSettings;

    public function test_generates_and_stores_a_vapid_keypair(): void
    {
        $this->artisan('webpush:generate-vapid-keys')->assertExitCode(0);

        $settings = app(SettingsService::class);

        $this->assertNotEmpty($settings->get('webpush.vapid_public_key'));
        $this->assertNotEmpty($settings->get('webpush.vapid_private_key'));
        $this->assertNotEmpty($settings->get('webpush.vapid_subject'));
    }

    public function test_refuses_to_overwrite_existing_keys_without_force(): void
    {
        $this->seedSetting('webpush.vapid_public_key', 'existing-public-key');
        $this->seedSetting('webpush.vapid_private_key', 'existing-private-key');

        $this->artisan('webpush:generate-vapid-keys')->assertExitCode(1);

        $this->assertSame('existing-public-key', app(SettingsService::class)->get('webpush.vapid_public_key'));
    }

    public function test_overwrites_existing_keys_with_force(): void
    {
        $this->seedSetting('webpush.vapid_public_key', 'existing-public-key');
        $this->seedSetting('webpush.vapid_private_key', 'existing-private-key');

        $this->artisan('webpush:generate-vapid-keys', ['--force' => true])->assertExitCode(0);

        $this->assertNotSame('existing-public-key', app(SettingsService::class)->get('webpush.vapid_public_key'));
    }
}
