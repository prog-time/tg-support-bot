<?php

namespace Tests\Unit\Modules\Admin\Services;

use App\Modules\Admin\Services\EmailVerificationService;
use Mockery;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;

/**
 * Unit tests for EmailVerificationService.
 *
 * Verifies IMAP and SMTP credentials by opening real connection objects
 * (webklex Client::connect()/disconnect(), Symfony Mailer SmtpTransport::
 * start()/stop()) — but the service accepts factory closures so tests inject
 * Mockery doubles instead of ever opening a real socket. This satisfies the
 * "no real connections in tests" requirement while still exercising every
 * branch of verify().
 */
class EmailVerificationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function credentials(array $overrides = []): array
    {
        return array_merge([
            'host' => 'mail.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
        ], $overrides);
    }

    public function test_verify_succeeds_when_imap_and_smtp_both_connect(): void
    {
        /** @var Client&Mockery\MockInterface $imapClient */
        $imapClient = Mockery::mock(Client::class);
        $imapClient->shouldReceive('connect')->once();
        $imapClient->shouldReceive('disconnect')->once();

        /** @var SmtpTransport&Mockery\MockInterface $transport */
        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('stop')->once();

        $service = new EmailVerificationService(
            imapClientFactory: fn (array $c) => $imapClient,
            smtpTransportFactory: fn (string $dsn) => $transport,
        );

        $result = $service->verify($this->credentials(), $this->credentials(['port' => 587, 'encryption' => 'tls']));

        $this->assertTrue($result['success']);
    }

    public function test_verify_fails_when_imap_connect_throws(): void
    {
        /** @var Client&Mockery\MockInterface $imapClient */
        $imapClient = Mockery::mock(Client::class);
        $imapClient->shouldReceive('connect')->once()->andThrow(new \RuntimeException('auth failed'));

        $service = new EmailVerificationService(
            imapClientFactory: fn (array $c) => $imapClient,
            smtpTransportFactory: fn (string $dsn) => $this->fail('SMTP must not be checked when IMAP already failed'),
        );

        $result = $service->verify($this->credentials(), $this->credentials());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('IMAP', $result['message']);
    }

    public function test_verify_fails_when_smtp_start_throws(): void
    {
        /** @var Client&Mockery\MockInterface $imapClient */
        $imapClient = Mockery::mock(Client::class);
        $imapClient->shouldReceive('connect')->once();
        $imapClient->shouldReceive('disconnect')->once();

        /** @var SmtpTransport&Mockery\MockInterface $transport */
        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')->once()->andThrow(new \RuntimeException('connection refused'));

        $service = new EmailVerificationService(
            imapClientFactory: fn (array $c) => $imapClient,
            smtpTransportFactory: fn (string $dsn) => $transport,
        );

        $result = $service->verify($this->credentials(), $this->credentials());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('SMTP', $result['message']);
    }

    public function test_verify_fails_without_imap_credentials_and_never_touches_smtp(): void
    {
        $service = new EmailVerificationService(
            imapClientFactory: fn (array $c) => $this->fail('IMAP client factory must not be called without credentials'),
            smtpTransportFactory: fn (string $dsn) => $this->fail('SMTP must not be checked when IMAP fields are empty'),
        );

        $result = $service->verify($this->credentials(['host' => '']), $this->credentials());

        $this->assertFalse($result['success']);
    }

    public function test_verify_fails_without_smtp_credentials(): void
    {
        /** @var Client&Mockery\MockInterface $imapClient */
        $imapClient = Mockery::mock(Client::class);
        $imapClient->shouldReceive('connect')->once();
        $imapClient->shouldReceive('disconnect')->once();

        $service = new EmailVerificationService(
            imapClientFactory: fn (array $c) => $imapClient,
            smtpTransportFactory: fn (string $dsn) => $this->fail('SMTP transport factory must not be called without credentials'),
        );

        $result = $service->verify($this->credentials(), $this->credentials(['password' => '']));

        $this->assertFalse($result['success']);
    }

    public function test_smtp_dsn_uses_smtps_scheme_for_ssl_encryption(): void
    {
        /** @var Client&Mockery\MockInterface $imapClient */
        $imapClient = Mockery::mock(Client::class);
        $imapClient->shouldReceive('connect')->once();
        $imapClient->shouldReceive('disconnect')->once();

        /** @var SmtpTransport&Mockery\MockInterface $transport */
        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('stop')->once();

        $capturedDsn = null;

        $service = new EmailVerificationService(
            imapClientFactory: fn (array $c) => $imapClient,
            smtpTransportFactory: function (string $dsn) use ($transport, &$capturedDsn) {
                $capturedDsn = $dsn;

                return $transport;
            },
        );

        $service->verify($this->credentials(), $this->credentials(['encryption' => 'ssl', 'port' => 465]));

        $this->assertStringStartsWith('smtps://', (string) $capturedDsn);
    }

    public function test_smtp_dsn_uses_smtp_scheme_for_tls_encryption(): void
    {
        /** @var Client&Mockery\MockInterface $imapClient */
        $imapClient = Mockery::mock(Client::class);
        $imapClient->shouldReceive('connect')->once();
        $imapClient->shouldReceive('disconnect')->once();

        /** @var SmtpTransport&Mockery\MockInterface $transport */
        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('stop')->once();

        $capturedDsn = null;

        $service = new EmailVerificationService(
            imapClientFactory: fn (array $c) => $imapClient,
            smtpTransportFactory: function (string $dsn) use ($transport, &$capturedDsn) {
                $capturedDsn = $dsn;

                return $transport;
            },
        );

        $service->verify($this->credentials(), $this->credentials(['encryption' => 'tls', 'port' => 587]));

        $this->assertStringStartsWith('smtp://', (string) $capturedDsn);
    }
}
