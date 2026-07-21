<?php

namespace App\Modules\Email\Api;

use App\Modules\Email\DTOs\EmailMessageDto;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends outgoing support replies over SMTP, on top of Laravel's built-in Mail
 * component (`Mail::mailer(...)->raw(...)`), using per-request runtime SMTP
 * settings from {@see SettingsService} rather than a fixed `.env` mailer.
 *
 * Every channel's credentials live only in the `settings` table (see
 * CLAUDE.md), so the SMTP mailer config is registered under a dedicated
 * mailer name at send time and purged immediately after — the resolved
 * transport must never be reused across two sends with different stored
 * credentials.
 */
class EmailMailer
{
    private const MAILER_NAME = 'email_dynamic';

    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * Send an outgoing message. Returns false (and logs) instead of throwing
     * so the caller (a queued job) can decide how to react without depending
     * on this class's exception types.
     *
     * @param EmailMessageDto $dto
     *
     * @return bool
     */
    public function send(EmailMessageDto $dto): bool
    {
        $host = (string) ($this->settings->get('email.smtp_host') ?? '');
        $port = (int) ($this->settings->get('email.smtp_port') ?: 587);
        $encryption = (string) ($this->settings->get('email.smtp_encryption') ?? 'tls');
        $username = (string) ($this->settings->get('email.username') ?? '');
        $password = (string) ($this->settings->get('email.password') ?? '');
        $fromAddress = (string) ($this->settings->get('email.from_address') ?: $username);
        $fromName = (string) ($this->settings->get('email.from_name') ?? '');

        if ($host === '' || $username === '' || $password === '' || $fromAddress === '') {
            Log::channel('app')->error('EmailMailer: SMTP is not configured, message not sent', [
                'to' => $dto->to,
            ]);

            return false;
        }

        Config::set('mail.mailers.' . self::MAILER_NAME, [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption !== '' ? $encryption : null,
            'username' => $username,
            'password' => $password,
            'timeout' => 20,
        ]);

        try {
            Mail::mailer(self::MAILER_NAME)->raw($dto->text, function ($message) use ($dto, $fromAddress, $fromName): void {
                $message->to($dto->to)
                    ->subject($dto->subject)
                    ->from($fromAddress, $fromName !== '' ? $fromName : null);

                $headers = $message->getSymfonyMessage()->getHeaders();

                if (!empty($dto->inReplyTo)) {
                    $headers->addTextHeader('In-Reply-To', '<' . $dto->inReplyTo . '>');
                }

                if (!empty($dto->references)) {
                    $headers->addTextHeader('References', '<' . $dto->references . '>');
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::channel('app')->error('EmailMailer: send failed | ' . $e->getMessage(), [
                'to' => $dto->to,
            ]);

            return false;
        } finally {
            Mail::purge(self::MAILER_NAME);
        }
    }
}
