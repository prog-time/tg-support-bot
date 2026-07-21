<?php

namespace Tests\Unit\Modules\Email\Console;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Email\Actions\SendBannedMessageEmail;
use App\Modules\Email\Contracts\EmailInboxReader;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\Traits\SeedsSettings;

/**
 * Covers App\Modules\Email\Console\PollInboxCommand::handle().
 *
 * Plays the role AvitoBotControllerTest / a webhook feature test plays for
 * the other channels, adapted for polling: EmailInboxReader is mocked (no
 * real IMAP connection), and the key invariant under test is that
 * markSeen() is called ONLY for messages that were fully processed — a
 * failure must leave the message unseen so the next run retries it.
 */
class PollInboxCommandTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSettings;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seedSetting('email.imap_host', 'imap.example.com');
        $this->seedSetting('email.username', 'support@example.com');
        $this->seedSetting('email.password', 'secret');
        $this->seedSetting('telegram.group_id', '');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeDto(string $chatId = 'user@example.com', ?string $text = 'Здравствуйте'): EmailUpdateDto
    {
        return new EmailUpdateDto(
            chatId: $chatId,
            senderName: 'User Name',
            subject: 'Subject',
            text: $text,
            messageId: 'msg-1@example.com',
            references: null,
        );
    }

    public function test_noop_when_email_not_configured(): void
    {
        $this->seedSetting('email.imap_host', '');

        $reader = Mockery::mock(EmailInboxReader::class);
        $reader->shouldNotReceive('fetchUnseen');
        $this->app->instance(EmailInboxReader::class, $reader);

        $this->artisan('email:poll')->assertExitCode(0);
    }

    public function test_processes_message_and_marks_seen_on_success(): void
    {
        $dto = $this->makeDto();

        $reader = Mockery::mock(EmailInboxReader::class);
        $reader->shouldReceive('fetchUnseen')->once()->andReturn([$dto]);
        $reader->shouldReceive('markSeen')->once()->with($dto);
        $this->app->instance(EmailInboxReader::class, $reader);

        $this->artisan('email:poll')->assertExitCode(0);

        $platform = BotUser::getPlatformByChatId($dto->chatId);
        $this->assertSame('email', $platform);
        $this->assertDatabaseHas('messages', [
            'platform' => 'email',
            'message_type' => 'incoming',
            'text' => 'Здравствуйте',
        ]);
    }

    public function test_does_not_mark_seen_when_processing_throws(): void
    {
        $dto = $this->makeDto(chatId: 'boom@example.com');
        BotUser::getUserByChatId($dto->chatId, 'email')->update(['is_banned' => true]);

        // Force a failure inside processUpdate()'s try block by making the
        // banned-notification action throw — proves the failure is caught
        // and the message is left unseen for a retry on the next poll,
        // instead of being silently dropped.
        $bannedAction = Mockery::mock(SendBannedMessageEmail::class);
        $bannedAction->shouldReceive('execute')->once()->andThrow(new \RuntimeException('mail send exploded'));
        $this->app->instance(SendBannedMessageEmail::class, $bannedAction);

        $reader = Mockery::mock(EmailInboxReader::class);
        $reader->shouldReceive('fetchUnseen')->once()->andReturn([$dto]);
        $reader->shouldNotReceive('markSeen');
        $this->app->instance(EmailInboxReader::class, $reader);

        $this->artisan('email:poll')->assertExitCode(0);
    }

    public function test_fetch_failure_returns_nonzero_and_marks_nothing_seen(): void
    {
        $reader = Mockery::mock(EmailInboxReader::class);
        $reader->shouldReceive('fetchUnseen')->once()->andThrow(new \RuntimeException('IMAP down'));
        $reader->shouldNotReceive('markSeen');
        $this->app->instance(EmailInboxReader::class, $reader);

        $this->artisan('email:poll')->assertExitCode(1);
    }

    public function test_banned_user_receives_banned_message_and_is_marked_seen(): void
    {
        $dto = $this->makeDto(chatId: 'banned@example.com');

        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');
        $botUser->update(['is_banned' => true, 'banned_at' => now()]);

        $reader = Mockery::mock(EmailInboxReader::class);
        $reader->shouldReceive('fetchUnseen')->once()->andReturn([$dto]);
        $reader->shouldReceive('markSeen')->once()->with($dto);
        $this->app->instance(EmailInboxReader::class, $reader);

        $this->artisan('email:poll')->assertExitCode(0);

        Queue::assertPushed(SendEmailMessageJob::class);
        // Banned users do not get routed into the normal funnel.
        $this->assertSame(0, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_multiple_messages_each_marked_seen_independently(): void
    {
        $dtoA = $this->makeDto(chatId: 'a@example.com', text: 'A');
        $dtoB = $this->makeDto(chatId: 'b@example.com', text: 'B');

        $reader = Mockery::mock(EmailInboxReader::class);
        $reader->shouldReceive('fetchUnseen')->once()->andReturn([$dtoA, $dtoB]);
        $reader->shouldReceive('markSeen')->once()->with($dtoA);
        $reader->shouldReceive('markSeen')->once()->with($dtoB);
        $this->app->instance(EmailInboxReader::class, $reader);

        $this->artisan('email:poll')->assertExitCode(0);

        $this->assertSame(2, Message::where('platform', 'email')->count());
    }
}
