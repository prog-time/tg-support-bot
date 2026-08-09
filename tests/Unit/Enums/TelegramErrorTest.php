<?php

namespace Tests\Unit\Enums;

use App\Enums\TelegramError;
use Tests\TestCase;

class TelegramErrorTest extends TestCase
{
    public function test_resolves_invalid_forum_topic_identifier_to_topic_id_invalid(): void
    {
        $error = TelegramError::fromResponse('Bad Request: invalid forum topic identifier specified');

        $this->assertSame(TelegramError::TOPIC_ID_INVALID, $error);
    }

    public function test_resolves_message_thread_not_found_to_topic_not_found(): void
    {
        $error = TelegramError::fromResponse('Bad Request: message thread not found');

        $this->assertSame(TelegramError::TOPIC_NOT_FOUND, $error);
    }

    public function test_falls_back_to_generic_bad_request_for_unrecognized_bad_request_text(): void
    {
        $error = TelegramError::fromResponse('Bad Request: some unrelated error');

        $this->assertSame(TelegramError::BAD_REQUEST, $error);
    }

    public function test_returns_null_for_unrecognized_description(): void
    {
        $error = TelegramError::fromResponse('Totally unrelated description');

        $this->assertNull($error);
    }
}
