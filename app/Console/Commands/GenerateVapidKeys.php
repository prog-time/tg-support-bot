<?php

namespace App\Console\Commands;

use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * One-time setup command: generates the VAPID keypair used to sign Web Push
 * notifications for the admin PWA and stores it via SettingsService — no
 * settings screen, same "auto-captured" pattern as avito.user_id/telegram_ai.id.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:generate-vapid-keys {--force : Overwrite an existing keypair}';

    protected $description = 'Generate the VAPID keypair for admin PWA Web Push notifications';

    /**
     * @return int
     */
    public function handle(): int
    {
        $settings = app(SettingsService::class);

        if (!$this->option('force') && !empty($settings->get('webpush.vapid_public_key'))) {
            $this->error('VAPID keys are already set. Pass --force to overwrite them (this invalidates every existing browser subscription).');

            return Command::FAILURE;
        }

        $keys = VAPID::createVapidKeys();

        $settings->set('webpush.vapid_public_key', $keys['publicKey']);
        $settings->set('webpush.vapid_private_key', $keys['privateKey']);
        $settings->set('webpush.vapid_subject', rtrim((string) config('app.url'), '/'));

        $this->info('VAPID keypair generated and saved.');

        return Command::SUCCESS;
    }
}
