<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the Telescope entries table bounded — drop records older than 24h.
// Runs via the `scheduler` docker service (`php artisan schedule:work`);
// otherwise wire a cron (`* * * * * php artisan schedule:run`) or run
// `php artisan telescope:prune` manually.
Schedule::command('telescope:prune --hours=24')->daily();

// Email channel: poll the configured mailbox for unread support messages
// (IMAP). No-op when email.imap_host/username/password are not configured
// (see App\Modules\Email\Console\PollInboxCommand::isConfigured()). Runs via
// the `scheduler` docker service, same as telescope:prune above.
Schedule::command('email:poll')->everyMinute()->withoutOverlapping();
