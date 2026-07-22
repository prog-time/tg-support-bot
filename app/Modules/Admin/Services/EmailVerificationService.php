<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

/**
 * Pre-save verification for the Email module's IMAP/SMTP credentials.
 *
 * Mirrors {@see AvitoVerificationService} / {@see WebhookRegistrationService}'s
 * verifyX() methods: it runs against the form-entered credentials before
 * anything is persisted, so the integration screen can fail fast on bad
 * credentials. Unlike the other channels (a single HTTP API check), Email
 * needs TWO independent checks — the IMAP login and the SMTP handshake — both
 * must succeed for the channel to actually work (one side receives, the other
 * replies).
 *
 * IMAP is verified by opening a real webklex/php-imap connection and closing
 * it immediately. SMTP is verified by building a Symfony Mailer transport
 * from the entered credentials and calling start()/stop() — this performs the
 * real connect + EHLO + AUTH handshake without sending a message.
 *
 * Both connection factories are swappable via the constructor purely for
 * testability (unit tests inject mocks so no real socket is ever opened);
 * production code always uses the real webklex/Symfony implementations.
 * Credentials and any raw exception detail that might embed them are never
 * logged — only a fixed, user-facing failure message.
 */
class EmailVerificationService
{
    /**
     * @param (\Closure(array<string, mixed>): Client)|null $imapClientFactory    Test seam; defaults to a real ClientManager::make().
     * @param (\Closure(string): SmtpTransport)|null        $smtpTransportFactory Test seam; defaults to Transport::fromDsn().
     */
    public function __construct(
        private readonly ?\Closure $imapClientFactory = null,
        private readonly ?\Closure $smtpTransportFactory = null,
    ) {
    }

    /**
     * Verify both IMAP and SMTP credentials. Fails fast on the first failing side.
     *
     * @param array{host: string, port: int, encryption: string, username: string, password: string} $imap
     * @param array{host: string, port: int, encryption: string, username: string, password: string} $smtp
     *
     * @return array{success: bool, message: string}
     */
    public function verify(array $imap, array $smtp): array
    {
        $imapResult = $this->verifyImap($imap);
        if (!$imapResult['success']) {
            return $imapResult;
        }

        $smtpResult = $this->verifySmtp($smtp);
        if (!$smtpResult['success']) {
            return $smtpResult;
        }

        return ['success' => true, 'message' => 'Подключение по IMAP и SMTP подтверждено.'];
    }

    /**
     * @param array{host: string, port: int, encryption: string, username: string, password: string} $c
     *
     * @return array{success: bool, message: string}
     */
    private function verifyImap(array $c): array
    {
        if ($c['host'] === '' || $c['username'] === '' || $c['password'] === '') {
            return ['success' => false, 'message' => 'Заполните хост, логин и пароль IMAP.'];
        }

        try {
            $client = $this->makeImapClient($c);
            $client->connect();
            $client->disconnect();

            return ['success' => true, 'message' => 'IMAP подключение подтверждено.'];
        } catch (\Throwable $e) {
            Log::channel('app')->warning('EmailVerificationService: IMAP verify failed | ' . $e->getMessage());

            return ['success' => false, 'message' => 'Не удалось подключиться по IMAP: проверьте хост, порт, шифрование, логин и пароль.'];
        }
    }

    /**
     * @param array{host: string, port: int, encryption: string, username: string, password: string} $c
     *
     * @return array{success: bool, message: string}
     */
    private function verifySmtp(array $c): array
    {
        if ($c['host'] === '' || $c['username'] === '' || $c['password'] === '') {
            return ['success' => false, 'message' => 'Заполните хост, логин и пароль SMTP.'];
        }

        $scheme = $c['encryption'] === 'ssl' ? 'smtps' : 'smtp';
        $dsn = sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            rawurlencode($c['username']),
            rawurlencode($c['password']),
            $c['host'],
            $c['port'],
        );

        try {
            $transport = $this->makeSmtpTransport($dsn);
            $transport->start();
            $transport->stop();

            return ['success' => true, 'message' => 'SMTP подключение подтверждено.'];
        } catch (\Throwable $e) {
            Log::channel('app')->warning('EmailVerificationService: SMTP verify failed | ' . $e->getMessage());

            return ['success' => false, 'message' => 'Не удалось подключиться по SMTP: проверьте хост, порт, шифрование, логин и пароль.'];
        }
    }

    /**
     * @param array{host: string, port: int, encryption: string, username: string, password: string} $c
     *
     * @return Client
     */
    private function makeImapClient(array $c): Client
    {
        if ($this->imapClientFactory !== null) {
            return ($this->imapClientFactory)($c);
        }

        return (new ClientManager())->make([
            'host' => $c['host'],
            'port' => $c['port'],
            'protocol' => 'imap',
            'encryption' => $c['encryption'] !== '' ? $c['encryption'] : false,
            'validate_cert' => true,
            'username' => $c['username'],
            'password' => $c['password'],
        ]);
    }

    /**
     * @param string $dsn
     *
     * @return SmtpTransport
     */
    private function makeSmtpTransport(string $dsn): SmtpTransport
    {
        if ($this->smtpTransportFactory !== null) {
            return ($this->smtpTransportFactory)($dsn);
        }

        $transport = Transport::fromDsn($dsn);

        if (!$transport instanceof SmtpTransport) {
            throw new \RuntimeException('Unexpected transport type for SMTP DSN: ' . $transport::class);
        }

        return $transport;
    }
}
