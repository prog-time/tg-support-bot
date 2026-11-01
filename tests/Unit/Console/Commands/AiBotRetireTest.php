<?php

namespace Tests\Unit\Console\Commands;

use App\Models\AiMessage;
use App\Models\BotUser;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiBotRetireTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '111:legacy-ai-token';

    private const KEYS = ['telegram_ai.token', 'telegram_ai.secret', 'telegram_ai.username', 'telegram_ai.id'];

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::forceCreate(['key' => 'telegram_ai.token', 'value' => Crypt::encrypt(self::TOKEN), 'is_secret' => true]);
        Setting::forceCreate(['key' => 'telegram_ai.secret', 'value' => Crypt::encrypt('legacy-secret'), 'is_secret' => true]);
        Setting::forceCreate(['key' => 'telegram_ai.username', 'value' => '@old_ai_bot', 'is_secret' => false]);
        Setting::forceCreate(['key' => 'telegram_ai.id', 'value' => '111', 'is_secret' => false]);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
    }

    private function draft(int $messageId, string $status = AiMessage::STATUS_PENDING): AiMessage
    {
        return AiMessage::create([
            'bot_user_id' => $this->botUser->id,
            'message_id' => $messageId,
            'text_ai' => 'draft',
            'text_manager' => '',
            'status' => $status,
        ]);
    }

    private function assertKeysRemoved(): void
    {
        foreach (self::KEYS as $key) {
            $this->assertDatabaseMissing('settings', ['key' => $key]);
        }
    }

    public function test_deletes_drafts_with_old_token_removes_webhook_and_keys(): void
    {
        $draft = $this->draft(10);
        $accepted = $this->draft(11, AiMessage::STATUS_ACCEPTED);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]);

        $exit = Artisan::call('ai-bot:retire');

        $this->assertSame(0, $exit);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'bot' . self::TOKEN . '/deleteMessage') && (int) $r['message_id'] === 10);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'bot' . self::TOKEN . '/deleteWebhook') && (bool) $r['drop_pending_updates']);
        $this->assertNull($draft->fresh()->message_id);
        $this->assertSame(AiMessage::STATUS_PENDING, $draft->fresh()->status);
        $this->assertSame(11, (int) $accepted->fresh()->message_id);
        $this->assertKeysRemoved();
        $this->assertStringNotContainsString(self::TOKEN, Artisan::output());
    }

    public function test_strips_buttons_when_message_cannot_be_deleted(): void
    {
        $draft = $this->draft(10);
        Http::fake([
            '*/deleteMessage' => Http::response(['ok' => false, 'error_code' => 400, 'description' => "message can't be deleted"], 400),
            '*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->assertSame(0, Artisan::call('ai-bot:retire'));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/editMessageReplyMarkup') && $r['reply_markup']['inline_keyboard'] === []);
        $this->assertNull($draft->fresh()->message_id);
    }

    public function test_revoked_token_skips_telegram_and_removes_keys(): void
    {
        $first = $this->draft(10);
        $second = $this->draft(11);
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)]);

        $this->assertSame(0, Artisan::call('ai-bot:retire'));

        Http::assertSentCount(1);
        $this->assertNull($first->fresh()->message_id);
        $this->assertNull($second->fresh()->message_id);
        $this->assertKeysRemoved();
    }

    public function test_telegram_outage_fails_keeps_keys_and_repeat_run_continues(): void
    {
        $draft = $this->draft(10);
        Http::fake(['*' => Http::response(['ok' => false, 'error_code' => 502, 'description' => 'Bad Gateway'], 502)]);

        $this->assertNotSame(0, Artisan::call('ai-bot:retire'));

        $this->assertSame(10, (int) $draft->fresh()->message_id);
        foreach (self::KEYS as $key) {
            $this->assertDatabaseHas('settings', ['key' => $key]);
        }
    }

    public function test_network_failure_does_not_expose_token_in_logs_or_console(): void
    {
        $this->draft(10);
        Http::fake(fn ($request) => throw new ConnectionException('cURL error 28 for ' . $request->url()));

        $logged = [];
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('log')->andReturnUsing(function ($level, $message) use (&$logged) {
            $logged[] = $message;
        });

        $this->assertNotSame(0, Artisan::call('ai-bot:retire'));

        $this->assertNotEmpty($logged);
        $this->assertStringNotContainsString(self::TOKEN, implode(' ', $logged) . Artisan::output());
    }

    public function test_webhook_outage_fails_keeps_keys(): void
    {
        Http::fake(['*/deleteWebhook' => Http::response(['ok' => false, 'error_code' => 500, 'description' => 'Server error'], 500)]);

        $this->assertNotSame(0, Artisan::call('ai-bot:retire'));

        $this->assertDatabaseHas('settings', ['key' => 'telegram_ai.token']);
    }

    public function test_repeat_run_without_token_is_a_no_op(): void
    {
        $draft = $this->draft(10);
        Setting::whereIn('key', self::KEYS)->delete();
        Cache::flush();
        Http::fake();

        $this->assertSame(0, Artisan::call('ai-bot:retire'));

        Http::assertNothingSent();
        $this->assertNull($draft->fresh()->message_id);
    }

    public function test_undecryptable_token_skips_telegram_and_removes_keys(): void
    {
        Setting::where('key', 'telegram_ai.token')->update(['value' => 'not-encrypted']);
        Http::fake();

        $this->assertSame(0, Artisan::call('ai-bot:retire'));

        Http::assertNothingSent();
        $this->assertKeysRemoved();
    }
}
