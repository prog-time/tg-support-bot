<?php

namespace App\Modules\Email;

use App\Modules\Email\Api\EmailImapClient;
use App\Modules\Email\Console\PollInboxCommand;
use App\Modules\Email\Contracts\EmailInboxReader;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Email module: binds the inbox reader interface to the
 * webklex/php-imap-backed implementation and registers the `email:poll`
 * console command.
 *
 * Unlike Telegram/VK/MAX/Avito, this module has no Controllers/Middleware/
 * routes.php — incoming mail arrives via IMAP polling (Console/PollInboxCommand),
 * not an HTTP webhook. The scheduler entry itself lives in routes/console.php
 * (next to the existing telescope:prune entry), consistent with how the rest
 * of the app's scheduled commands are registered.
 */
class EmailServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(EmailInboxReader::class, EmailImapClient::class);
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PollInboxCommand::class,
            ]);
        }
    }
}
