<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ai\Services;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ai\Services\AiChatHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiChatHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.max_context_tokens', 3000);

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
    }

    private function pushMessage(string $type, ?string $text, int $secondsOffset = 0): Message
    {
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => 'telegram',
            'message_type' => $type,
            'from_id' => random_int(1, PHP_INT_MAX),
            'to_id' => random_int(1, PHP_INT_MAX),
            'text' => $text,
        ]);

        if ($secondsOffset !== 0) {
            $timestamp = now()->addSeconds($secondsOffset);
            $message->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();
        }

        return $message;
    }

    public function test_maps_incoming_to_user_and_outgoing_to_assistant(): void
    {
        $this->pushMessage('incoming', 'Здравствуйте', -30);
        $this->pushMessage('outgoing', 'Добрый день, чем помочь?', -20);
        $this->pushMessage('incoming', 'У меня вопрос про webhook', -10);

        $history = (new AiChatHistoryService())->buildForBotUser($this->botUser->id);

        $this->assertSame([
            ['role' => 'user', 'content' => 'Здравствуйте'],
            ['role' => 'assistant', 'content' => 'Добрый день, чем помочь?'],
            ['role' => 'user', 'content' => 'У меня вопрос про webhook'],
        ], $history);
    }

    public function test_drops_slash_commands_from_user_messages(): void
    {
        $this->pushMessage('incoming', '/start', -30);
        $this->pushMessage('incoming', '/contact', -20);
        $this->pushMessage('incoming', 'Реальный вопрос', -10);

        $history = (new AiChatHistoryService())->buildForBotUser($this->botUser->id);

        $this->assertSame([
            ['role' => 'user', 'content' => 'Реальный вопрос'],
        ], $history);
    }

    public function test_drops_empty_and_null_text(): void
    {
        $this->pushMessage('incoming', null, -40);
        $this->pushMessage('incoming', '   ', -30);
        $this->pushMessage('outgoing', '', -20);
        $this->pushMessage('outgoing', 'Содержательный ответ', -10);

        $history = (new AiChatHistoryService())->buildForBotUser($this->botUser->id);

        $this->assertSame([
            ['role' => 'assistant', 'content' => 'Содержательный ответ'],
        ], $history);
    }

    public function test_preserves_chronological_order(): void
    {
        $this->pushMessage('incoming', 'Первое', -300);
        $this->pushMessage('outgoing', 'Второе', -200);
        $this->pushMessage('incoming', 'Третье', -100);

        $history = (new AiChatHistoryService())->buildForBotUser($this->botUser->id);

        $this->assertSame(['Первое', 'Второе', 'Третье'], array_column($history, 'content'));
    }

    public function test_sliding_window_drops_oldest_when_over_token_limit(): void
    {
        Config::set('ai.max_context_tokens', 3000);

        $largeText = str_repeat('a', 4000); // ≈ 1000 tokens at mb_strlen/4
        $this->pushMessage('incoming', $largeText . '_1', -400);
        $this->pushMessage('outgoing', $largeText . '_2', -300);
        $this->pushMessage('incoming', $largeText . '_3', -200);
        $this->pushMessage('outgoing', $largeText . '_4', -100);
        $this->pushMessage('incoming', $largeText . '_5', -50);

        $history = (new AiChatHistoryService())->buildForBotUser($this->botUser->id);

        $this->assertCount(3, $history);
        $this->assertSame(
            [$largeText . '_3', $largeText . '_4', $largeText . '_5'],
            array_column($history, 'content'),
        );
    }

    public function test_drops_last_user_entry_when_it_matches_exclude_text(): void
    {
        $this->pushMessage('incoming', 'Старое', -200);
        $this->pushMessage('outgoing', 'Ответ', -100);
        $this->pushMessage('incoming', 'Текущее сообщение', -10);

        $history = (new AiChatHistoryService())
            ->buildForBotUser($this->botUser->id, 'Текущее сообщение');

        $this->assertSame([
            ['role' => 'user', 'content' => 'Старое'],
            ['role' => 'assistant', 'content' => 'Ответ'],
        ], $history);
    }

    public function test_idempotent_when_last_user_entry_does_not_match_exclude_text(): void
    {
        $this->pushMessage('incoming', 'Старое', -200);
        $this->pushMessage('outgoing', 'Ответ', -100);
        $this->pushMessage('incoming', 'Что-то другое', -10);

        $history = (new AiChatHistoryService())
            ->buildForBotUser($this->botUser->id, 'Текущее сообщение');

        $this->assertSame([
            ['role' => 'user', 'content' => 'Старое'],
            ['role' => 'assistant', 'content' => 'Ответ'],
            ['role' => 'user', 'content' => 'Что-то другое'],
        ], $history);
    }

    public function test_idempotent_when_no_history_exists(): void
    {
        $history = (new AiChatHistoryService())
            ->buildForBotUser($this->botUser->id, 'Текущее сообщение');

        $this->assertSame([], $history);
    }

    public function test_does_not_drop_last_assistant_even_if_text_matches(): void
    {
        $this->pushMessage('incoming', 'Вопрос', -100);
        $this->pushMessage('outgoing', 'Совпадающий текст', -10);

        $history = (new AiChatHistoryService())
            ->buildForBotUser($this->botUser->id, 'Совпадающий текст');

        $this->assertSame([
            ['role' => 'user', 'content' => 'Вопрос'],
            ['role' => 'assistant', 'content' => 'Совпадающий текст'],
        ], $history);
    }
}
