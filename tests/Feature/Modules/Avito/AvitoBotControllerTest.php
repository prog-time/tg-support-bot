<?php

namespace Tests\Feature\Modules\Avito;

use App\Models\BotUser;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AvitoBotControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(string $chatId = 'u2i-abc', string $eventId = 'evt-1'): array
    {
        return [
            'id' => $eventId,
            'payload' => [
                'type' => 'read',
                'value' => [
                    'id' => 'm1',
                    'chat_id' => $chatId,
                    'author_id' => 7,
                    'content' => ['text' => 'hi'],
                ],
            ],
        ];
    }

    public function test_unparseable_payload_is_acked_without_side_effects(): void
    {
        $this->postJson('/api/avito/bot', ['foo' => 'bar'])->assertOk();

        $this->assertDatabaseCount('bot_users', 0);
    }

    public function test_creates_bot_user_with_avito_platform_and_string_chat_id(): void
    {
        $this->postJson('/api/avito/bot', $this->payload('u2i-new-user'))->assertOk();

        $this->assertDatabaseHas('bot_users', [
            'chat_id' => 'u2i-new-user',
            'platform' => 'avito',
        ]);
    }

    public function test_duplicate_event_id_is_deduplicated(): void
    {
        $this->postJson('/api/avito/bot', $this->payload('u2i-dup', 'evt-dup'))->assertOk();
        $this->postJson('/api/avito/bot', $this->payload('u2i-dup', 'evt-dup'))->assertOk();

        $this->assertDatabaseCount('bot_users', 1);
    }

    public function test_banned_user_receives_banned_notice_instead_of_being_processed(): void
    {
        Queue::fake();

        BotUser::create(['chat_id' => 'u2i-banned', 'platform' => 'avito', 'is_banned' => true]);

        $this->postJson('/api/avito/bot', $this->payload('u2i-banned'))->assertOk();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendAvitoSimpleMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];
        $this->assertInstanceOf(AvitoTextMessageDto::class, $job->queryParams);
        $this->assertEquals('u2i-banned', $job->queryParams->chat_id);
    }
}
