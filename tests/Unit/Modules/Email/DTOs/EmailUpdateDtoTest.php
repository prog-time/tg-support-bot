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

    // ── displayText() ────────────────────────────────────────────────────────

    public function test_display_text_prefixes_body_with_subject(): void
    {
        $dto = new EmailUpdateDto(
            chatId: 'user@example.com',
            senderName: 'user@example.com',
            subject: 'Need help',
            text: 'Hello there',
            messageId: 'id-1',
            references: null,
        );

        $this->assertSame("Тема: Need help\n\nHello there", $dto->displayText());
    }

    public function test_display_text_returns_body_only_when_subject_is_blank(): void
    {
        $dto = new EmailUpdateDto(
            chatId: 'user@example.com',
            senderName: 'user@example.com',
            subject: '   ',
            text: 'Hello there',
            messageId: 'id-1',
            references: null,
        );

        $this->assertSame('Hello there', $dto->displayText());
    }

    public function test_display_text_casts_null_body_to_empty_string(): void
    {
        $dto = new EmailUpdateDto(
            chatId: 'user@example.com',
            senderName: 'user@example.com',
            subject: '',
            text: null,
            messageId: 'id-1',
            references: null,
        );

        $this->assertSame('', $dto->displayText());
    }

    // ── withoutProviderRef() ─────────────────────────────────────────────────

    public function test_without_provider_ref_nulls_the_ref_and_keeps_other_fields(): void
    {
        $dto = new EmailUpdateDto(
            chatId: 'user@example.com',
            senderName: 'John Doe',
            subject: 'Need help',
            text: 'Hello there',
            messageId: 'abc123@example.com',
            references: '<prev1@example.com>',
            attachments: ['a'],
            providerRef: new \stdClass(),
        );

        $copy = $dto->withoutProviderRef();

        $this->assertNull($copy->providerRef);
        $this->assertNotSame($dto, $copy);
        $this->assertSame($dto->chatId, $copy->chatId);
        $this->assertSame($dto->senderName, $copy->senderName);
        $this->assertSame($dto->subject, $copy->subject);
        $this->assertSame($dto->text, $copy->text);
        $this->assertSame($dto->messageId, $copy->messageId);
        $this->assertSame($dto->references, $copy->references);
        $this->assertSame($dto->attachments, $copy->attachments);
    }

    public function test_without_provider_ref_is_serializable_even_when_original_is_not(): void
    {
        // Regression: the original DTO can carry a non-serialization-safe
        // providerRef (e.g. a live IMAP message object with a resource
        // handle) — the stripped copy must always be safe to serialize()/
        // json_encode() for a queue payload.
        $dto = new EmailUpdateDto(
            chatId: 'user@example.com',
            senderName: 'user@example.com',
            subject: 'Subject',
            text: 'Body',
            messageId: 'id-1',
            references: null,
            providerRef: fopen('php://memory', 'r'),
        );

        $copy = $dto->withoutProviderRef();

        $this->assertIsString(serialize($copy));
    }
}
