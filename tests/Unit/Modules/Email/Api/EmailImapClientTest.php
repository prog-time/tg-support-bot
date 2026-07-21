<?php

namespace Tests\Unit\Modules\Email\Api;

use App\Modules\Email\Api\EmailImapClient;
use App\Services\Settings\SettingsService;
use Tests\TestCase;

/**
 * Covers header decoding in the IMAP client.
 *
 * webklex hands some header values back still RFC 2047 encoded
 * («=?UTF-8?B?…?=»), which previously surfaced verbatim as the conversation
 * title. `decodeMimeHeader()` is a pure helper, so it is exercised directly
 * rather than through a full IMAP-message mock.
 */
class EmailImapClientTest extends TestCase
{
    private function decode(string $value): string
    {
        $client = new EmailImapClient(app(SettingsService::class));

        $method = new \ReflectionMethod($client, 'decodeMimeHeader');

        return (string) $method->invoke($client, $value);
    }

    public function test_decodes_base64_encoded_word_to_utf8(): void
    {
        // The exact value that surfaced as a chat title in production.
        $this->assertSame('Илья Работа', $this->decode('=?UTF-8?B?0JjQu9GM0Y8g0KDQsNCx0L7RgtCw?='));
    }

    public function test_decodes_quoted_printable_encoded_word(): void
    {
        $this->assertSame('Тест', $this->decode('=?utf-8?Q?=D0=A2=D0=B5=D1=81=D1=82?='));
    }

    public function test_leaves_a_plain_ascii_value_unchanged(): void
    {
        $this->assertSame('John Doe', $this->decode('John Doe'));
    }

    public function test_leaves_a_plain_utf8_value_unchanged(): void
    {
        $this->assertSame('Иван Петров', $this->decode('Иван Петров'));
    }

    public function test_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', $this->decode(''));
    }
}
