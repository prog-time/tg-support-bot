<?php

namespace Tests\Unit\Modules\Email\DTOs;

use App\Modules\Email\DTOs\EmailUpdateDto;
use Tests\TestCase;

/**
 * EmailUpdateDto is a plain readonly value object populated by
 * {@see \App\Modules\Email\Api\EmailImapClient} from a parsed IMAP message.
 *
 * Unlike Avito/Max (which parse an HTTP webhook `Request` via a static
 * fromRequest()), there is no request-shaped payload to parse here — the
 * webklex/php-imap parsing itself lives in EmailImapClient and is
 * intentionally NOT unit-tested against a real mailbox (see the Completion
 * Report). This test locks in the DTO's shape and default values, which is
 * what every other unit in the module (EmailMessageService, PollInboxCommand,
 * SendEmailTelegramMessageJob) is built against.
 */
class EmailUpdateDtoTest extends TestCase
{
    public function test_constructs_with_all_fields(): void
    {
        $dto = new EmailUpdateDto(
            chatId: 'user@example.com',
            senderName: 'John Doe',
            subject: 'Need help',
            text: 'Hello, I have a problem.',
            messageId: 'abc123@example.com',
            references: '<prev1@example.com> <prev2@example.com>',
            attachments: [],
            providerRef: null,
        );

        $this->assertSame('user@example.com', $dto->chatId);
        $this->assertSame('John Doe', $dto->senderName);
        $this->assertSame('Need help', $dto->subject);
        $this->assertSame('Hello, I have a problem.', $dto->text);
        $this->assertSame('abc123@example.com', $dto->messageId);
        $this->assertSame('<prev1@example.com> <prev2@example.com>', $dto->references);
        $this->assertSame([], $dto->attachments);
        $this->assertNull($dto->providerRef);
    }

    public function test_attachments_and_provider_ref_default_to_empty_and_null(): void
    {
        $dto = new EmailUpdateDto(
            chatId: 'user@example.com',
            senderName: 'user@example.com',
            subject: '',
            text: null,
            messageId: '',
            references: null,
        );

        $this->assertSame([], $dto->attachments);
        $this->assertNull($dto->providerRef);
        $this->assertNull($dto->text);
    }

    public function test_provider_ref_can_carry_an_opaque_handle_for_mark_seen(): void
    {
        $handle = new \stdClass();

        $dto = new EmailUpdateDto(
            chatId: 'user@example.com',
            senderName: 'user@example.com',
            subject: 'Subject',
            text: 'text',
            messageId: 'id-1',
            references: null,
            providerRef: $handle,
        );

        $this->assertSame($handle, $dto->providerRef);
    }
}
