<?php

namespace App\Modules\Email\Console;

use App\Models\BotUser;
use App\Modules\Email\Actions\SendBannedMessageEmail;
use App\Modules\Email\Contracts\EmailInboxReader;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Modules\Email\Services\EmailIgnoreListMatcher;
use App\Modules\Email\Services\EmailMessageService;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Poll the configured mailbox for unread messages and route them into the
 * support funnel. This command plays the role a webhook controller plays for
 * the other channels (AvitoBotController / MaxBotController): resolve/create
 * the BotUser, short-circuit banned users, then hand the update to
 * {@see EmailMessageService}.
 *
 * A message is marked seen on the server ONLY after it was fully processed —
 * see {@see self::processUpdate()}. A failure anywhere in the pipeline (DB
 * down, BotUser resolution failed, EmailMessageService reports failure)
 * leaves the message unseen so the next run retries it; nothing is ever
 * marked seen speculatively.
 *
 * No-ops quietly (exit success) when the email channel is not configured,
 * so the scheduler entry can run unconditionally regardless of whether the
 * admin has set up the Email integration yet.
 */
class PollInboxCommand extends Command
{
    /** @var string */
    protected $signature = 'email:poll';

    /** @var string */
    protected $description = 'Poll the configured mailbox for unread support messages';

    /**
     * @param EmailInboxReader       $reader
     * @param SettingsService        $settings
     * @param EmailIgnoreListMatcher $ignoreList
     *
     * @return int
     */
    public function handle(EmailInboxReader $reader, SettingsService $settings, EmailIgnoreListMatcher $ignoreList): int
    {
        if (!$this->isConfigured($settings)) {
            return self::SUCCESS;
        }

        try {
            $updates = $reader->fetchUnseen();
        } catch (\Throwable $e) {
            Log::channel('app')->error('PollInboxCommand: fetchUnseen failed | ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($updates as $update) {
            if ($this->processUpdate($update, $ignoreList)) {
                $reader->markSeen($update);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param EmailUpdateDto         $update
     * @param EmailIgnoreListMatcher $ignoreList
     *
     * @return bool true when the email was fully handled and can be marked seen.
     */
    private function processUpdate(EmailUpdateDto $update, EmailIgnoreListMatcher $ignoreList): bool
    {
        try {
            if ($ignoreList->isIgnored($update->chatId)) {
                // Dropped before a BotUser/topic is ever created for this
                // sender — the whole point of the ignore list is to keep
                // newsletter/no-reply senders out of the support funnel.
                return true;
            }

            $botUser = BotUser::getUserByChatId($update->chatId, 'email');

            if ($botUser === null) {
                // Could not resolve/create the BotUser (e.g. DB failure) — retry next poll.
                return false;
            }

            if ($botUser->isBanned()) {
                app(SendBannedMessageEmail::class)->execute($botUser);

                return true;
            }

            $service = new EmailMessageService($update);
            $service->handleUpdate();

            return $service->handledSuccessfully();
        } catch (\Throwable $e) {
            Log::channel('app')->error('PollInboxCommand: failed to process message | ' . $e->getMessage(), [
                'message_id' => $update->messageId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return false;
        }
    }

    /**
     * @param SettingsService $settings
     *
     * @return bool
     */
    private function isConfigured(SettingsService $settings): bool
    {
        foreach (['email.imap_host', 'email.username', 'email.password'] as $key) {
            if (empty($settings->get($key))) {
                return false;
            }
        }

        return true;
    }
}
