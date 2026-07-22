<?php

namespace Tests\Unit\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\EmailIntegrationPage;
use App\Models\User;
use App\Modules\Admin\Services\EmailVerificationService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit-level tests for the EmailIntegrationPage Livewire component.
 *
 * Manages the built-in Email module's IMAP/SMTP credentials and runs a
 * verify-before-save flow. Admin-only.
 */
class EmailIntegrationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /** Always-succeeding verifier double, so tests don't touch real sockets. */
    private function verifierOk(): EmailVerificationService
    {
        $imapClient = Mockery::mock(\Webklex\PHPIMAP\Client::class);
        $imapClient->shouldReceive('connect')->andReturnSelf();
        $imapClient->shouldReceive('disconnect')->andReturnSelf();

        $smtpTransport = Mockery::mock(\Symfony\Component\Mailer\Transport\Smtp\SmtpTransport::class);
        $smtpTransport->shouldReceive('start')->andReturnNull();
        $smtpTransport->shouldReceive('stop')->andReturnNull();

        return new EmailVerificationService(
            imapClientFactory: fn (array $c) => $imapClient,
            smtpTransportFactory: fn (string $dsn) => $smtpTransport,
        );
    }

    private function verifierFailing(): EmailVerificationService
    {
        return new EmailVerificationService(
            imapClientFactory: fn (array $c) => throw new \RuntimeException('nope'),
            smtpTransportFactory: fn (string $dsn) => throw new \RuntimeException('nope'),
        );
    }

    public function test_mount_loads_values_and_secret_flag(): void
    {
        /** @var Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('email.imap_host')->andReturn('imap.example.com');
        $mock->shouldReceive('get')->with('email.imap_port')->andReturn(993);
        $mock->shouldReceive('get')->with('email.imap_encryption')->andReturn('ssl');
        $mock->shouldReceive('get')->with('email.smtp_host')->andReturn('smtp.example.com');
        $mock->shouldReceive('get')->with('email.smtp_port')->andReturn(587);
        $mock->shouldReceive('get')->with('email.smtp_encryption')->andReturn('tls');
        $mock->shouldReceive('get')->with('email.username')->andReturn('support@example.com');
        $mock->shouldReceive('get')->with('email.from_address')->andReturn('support@example.com');
        $mock->shouldReceive('get')->with('email.from_name')->andReturn('Support');
        $mock->shouldReceive('get')->with('email.ignored_addresses')->andReturn(['newsletter@example.com']);
        $mock->shouldReceive('has')->with('email.password')->andReturn(true);

        $component = new EmailIntegrationPage();
        $component->mount($mock);

        $this->assertSame('imap.example.com', $component->imap_host);
        $this->assertSame('smtp.example.com', $component->smtp_host);
        $this->assertSame('support@example.com', $component->username);
        $this->assertSame('newsletter@example.com', $component->ignored_addresses);
        $this->assertTrue($component->hasPassword);
    }

    public function test_connect_verifies_and_persists(): void
    {
        /** @var Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('set')->with('email.imap_host', 'imap.example.com')->once();
        $mock->shouldReceive('set')->with('email.imap_port', 993)->once();
        $mock->shouldReceive('set')->with('email.imap_encryption', 'ssl')->once();
        $mock->shouldReceive('set')->with('email.smtp_host', 'smtp.example.com')->once();
        $mock->shouldReceive('set')->with('email.smtp_port', 587)->once();
        $mock->shouldReceive('set')->with('email.smtp_encryption', 'tls')->once();
        $mock->shouldReceive('set')->with('email.username', 'support@example.com')->once();
        $mock->shouldReceive('set')->with('email.from_address', 'support@example.com')->once();
        $mock->shouldReceive('set')->with('email.from_name', 'Support')->once();
        $mock->shouldReceive('set')->with('email.ignored_addresses', ['newsletter@example.com', '@promo.example.com'])->once();
        $mock->shouldReceive('set')->with('email.password', 'secret')->once();
        $mock->shouldReceive('has')->with('email.password')->andReturn(true);

        $component = new EmailIntegrationPage();
        $component->imap_host = 'imap.example.com';
        $component->imap_port = '993';
        $component->imap_encryption = 'ssl';
        $component->smtp_host = 'smtp.example.com';
        $component->smtp_port = '587';
        $component->smtp_encryption = 'tls';
        $component->username = 'support@example.com';
        $component->password = 'secret';
        $component->from_address = 'support@example.com';
        $component->from_name = 'Support';
        $component->ignored_addresses = "Newsletter@Example.com\n\n@promo.example.com\n";

        $component->connect($mock, $this->verifierOk());

        $this->assertTrue($component->verifySuccess);
        $this->assertEmpty($component->formErrors);
    }

    public function test_connect_persists_nothing_when_verification_fails(): void
    {
        /** @var Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldNotReceive('set');
        $mock->shouldReceive('has')->andReturn(false);

        $component = new EmailIntegrationPage();
        $component->imap_host = 'imap.example.com';
        $component->smtp_host = 'smtp.example.com';
        $component->username = 'support@example.com';
        $component->password = 'secret';
        $component->from_address = 'support@example.com';

        $component->connect($mock, $this->verifierFailing());

        $this->assertFalse($component->verifySuccess);
        $this->assertNotNull($component->verifyMessage);
    }

    public function test_connect_requires_required_fields(): void
    {
        /** @var Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldNotReceive('set');
        $mock->shouldReceive('has')->andReturn(false);

        $component = new EmailIntegrationPage();
        $component->imap_host = '';
        $component->smtp_host = '';
        $component->username = '';
        $component->password = '';
        $component->from_address = '';

        $component->connect($mock, $this->verifierOk());

        $this->assertFalse($component->verifySuccess);
        $this->assertArrayHasKey('imap_host', $component->formErrors);
        $this->assertArrayHasKey('smtp_host', $component->formErrors);
        $this->assertArrayHasKey('username', $component->formErrors);
        $this->assertArrayHasKey('password', $component->formErrors);
        $this->assertArrayHasKey('from_address', $component->formErrors);
    }

    public function test_connect_uses_stored_password_when_field_blank(): void
    {
        /** @var Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldReceive('get')->with('email.password')->andReturn('stored-secret');
        $mock->shouldReceive('set')->with('email.imap_host', 'imap.example.com')->once();
        $mock->shouldReceive('set')->with('email.imap_port', 993)->once();
        $mock->shouldReceive('set')->with('email.imap_encryption', 'ssl')->once();
        $mock->shouldReceive('set')->with('email.smtp_host', 'smtp.example.com')->once();
        $mock->shouldReceive('set')->with('email.smtp_port', 587)->once();
        $mock->shouldReceive('set')->with('email.smtp_encryption', 'tls')->once();
        $mock->shouldReceive('set')->with('email.username', 'support@example.com')->once();
        $mock->shouldReceive('set')->with('email.from_address', 'support@example.com')->once();
        $mock->shouldReceive('set')->with('email.from_name', '')->once();
        $mock->shouldReceive('set')->with('email.ignored_addresses', [])->once();
        $mock->shouldNotReceive('set')->with('email.password', Mockery::any());
        $mock->shouldReceive('has')->andReturn(true);

        $component = new EmailIntegrationPage();
        $component->hasPassword = true;
        $component->imap_host = 'imap.example.com';
        $component->imap_port = '993';
        $component->imap_encryption = 'ssl';
        $component->smtp_host = 'smtp.example.com';
        $component->smtp_port = '587';
        $component->smtp_encryption = 'tls';
        $component->username = 'support@example.com';
        $component->password = '';
        $component->from_address = 'support@example.com';

        $component->connect($mock, $this->verifierOk());

        $this->assertTrue($component->verifySuccess);
    }

    public function test_connect_is_ignored_for_non_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        /** @var Mockery\MockInterface&SettingsService $mock */
        $mock = Mockery::mock(SettingsService::class);
        $mock->shouldNotReceive('set');

        $component = new EmailIntegrationPage();
        $component->imap_host = 'imap.example.com';
        $component->smtp_host = 'smtp.example.com';
        $component->username = 'support@example.com';
        $component->password = 'secret';
        $component->from_address = 'support@example.com';

        $component->connect($mock, $this->verifierOk());

        $this->assertFalse($component->verifySuccess);
    }
}
