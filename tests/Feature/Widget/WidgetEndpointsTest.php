<?php

namespace Tests\Feature\Widget;

use App\Models\BotUser;
use App\Models\ExternalSource;
use App\Models\ExternalUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for the JS widget gateway endpoints.
 *
 * Routes:
 *  POST   /api/widget/{external_id}/messages  — sendMessage
 *  POST   /api/widget/{external_id}/files     — sendFile
 *  GET    /api/widget/{external_id}/messages  — getMessages
 *
 * All routes are authenticated by WidgetGate (X-Widget-Key header).
 */
class WidgetEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private ExternalSource $source;

    private string $publicKey;

    private string $externalId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->publicKey = 'pub_' . str_repeat('w', 36);
        $this->externalId = 'session-abc-123';

        $this->source = ExternalSource::factory()->create([
            'name' => 'widget_test',
            'public_key' => $this->publicKey,
            'allowed_ips' => null,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Create an ExternalUser + BotUser pair for the current source/external_id.
     */
    private function seedSession(): BotUser
    {
        $externalUser = ExternalUser::firstOrCreate([
            'external_id' => $this->externalId,
            'source' => $this->source->name,
        ]);

        return BotUser::firstOrCreate([
            'chat_id' => $externalUser->id,
            'external_user_id' => $externalUser->id,
            'platform' => $this->source->name,
        ]);
    }

    /**
     * Create a Message + ExternalMessage row for the current session.
     */
    private function seedMessage(BotUser $botUser, string $text, string $type = 'incoming'): Message
    {
        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $this->source->name,
            'message_type' => $type,
            'from_id' => 0,
            'to_id' => 0,
        ]);

        $message->externalMessage()->create(['text' => $text]);

        return $message;
    }

    // ── POST /api/widget/{external_id}/messages ───────────────────────────────

    public function test_send_message_creates_incoming_message(): void
    {
        $response = $this->postJson(
            "/api/widget/{$this->externalId}/messages",
            ['text' => 'Hello support'],
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_send_message_requires_text(): void
    {
        $response = $this->postJson(
            "/api/widget/{$this->externalId}/messages",
            [],
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertUnprocessable();
    }

    public function test_send_message_rejects_text_over_4000_chars(): void
    {
        $response = $this->postJson(
            "/api/widget/{$this->externalId}/messages",
            ['text' => str_repeat('x', 4001)],
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertUnprocessable();
    }

    // ── POST /api/widget/{external_id}/files ──────────────────────────────────

    public function test_send_file_accepts_valid_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->postJson(
            "/api/widget/{$this->externalId}/files",
            ['uploaded_file' => $file],
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_send_file_requires_uploaded_file_field(): void
    {
        $response = $this->postJson(
            "/api/widget/{$this->externalId}/files",
            [],
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertUnprocessable();
    }

    // ── GET /api/widget/{external_id}/messages ────────────────────────────────

    public function test_get_messages_returns_empty_for_new_session(): void
    {
        $response = $this->getJson(
            "/api/widget/{$this->externalId}/messages",
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertOk()
            ->assertJson(['messages' => []]);
    }

    public function test_get_messages_returns_existing_messages(): void
    {
        $botUser = $this->seedSession();
        $this->seedMessage($botUser, 'First message');
        $this->seedMessage($botUser, 'Second message', 'outgoing');

        $response = $this->getJson(
            "/api/widget/{$this->externalId}/messages",
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertOk();
        $this->assertCount(2, $response->json('messages'));
    }

    public function test_get_messages_with_after_param_returns_only_newer(): void
    {
        $botUser = $this->seedSession();

        $msg1 = $this->seedMessage($botUser, 'One');
        $msg2 = $this->seedMessage($botUser, 'Two');
        $msg3 = $this->seedMessage($botUser, 'Three');
        $msg4 = $this->seedMessage($botUser, 'Four');
        $msg5 = $this->seedMessage($botUser, 'Five');

        $response = $this->getJson(
            "/api/widget/{$this->externalId}/messages?after={$msg3->id}",
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertOk();

        $ids = collect($response->json('messages'))->pluck('id')->all();

        $this->assertContains($msg4->id, $ids);
        $this->assertContains($msg5->id, $ids);
        $this->assertNotContains($msg1->id, $ids);
        $this->assertNotContains($msg2->id, $ids);
        $this->assertNotContains($msg3->id, $ids);
    }

    public function test_get_messages_with_no_after_returns_full_history(): void
    {
        $botUser = $this->seedSession();

        $this->seedMessage($botUser, 'Alpha');
        $this->seedMessage($botUser, 'Beta');
        $this->seedMessage($botUser, 'Gamma');

        $response = $this->getJson(
            "/api/widget/{$this->externalId}/messages",
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertOk();
        $this->assertCount(3, $response->json('messages'));
    }

    public function test_messages_include_direction_field(): void
    {
        $botUser = $this->seedSession();
        $this->seedMessage($botUser, 'Incoming msg', 'incoming');
        $this->seedMessage($botUser, 'Outgoing msg', 'outgoing');

        $response = $this->getJson(
            "/api/widget/{$this->externalId}/messages",
            ['X-Widget-Key' => $this->publicKey]
        );

        $response->assertOk();

        $directions = collect($response->json('messages'))->pluck('direction')->sort()->values()->all();
        $this->assertSame(['in', 'out'], $directions);
    }

    // ── Auth guard — all three routes require X-Widget-Key ────────────────────

    public function test_send_message_returns_401_without_key(): void
    {
        $this->postJson(
            "/api/widget/{$this->externalId}/messages",
            ['text' => 'hi']
        )->assertUnauthorized();
    }

    public function test_send_file_returns_401_without_key(): void
    {
        Storage::fake('public');

        $this->postJson(
            "/api/widget/{$this->externalId}/files",
            ['uploaded_file' => UploadedFile::fake()->create('f.pdf', 10)]
        )->assertUnauthorized();
    }

    public function test_get_messages_returns_401_without_key(): void
    {
        $this->getJson("/api/widget/{$this->externalId}/messages")
            ->assertUnauthorized();
    }
}
