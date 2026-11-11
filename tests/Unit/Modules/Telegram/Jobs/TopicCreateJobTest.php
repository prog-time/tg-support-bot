<?php

namespace Tests\Unit\Modules\Telegram\Jobs;

use App\Models\BotUser;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopicCreateJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://api.telegram.org/bot*/createForumTopic*' => Http::response([
                'ok' => true,
                'result' => ['message_thread_id' => 555],
            ]),
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    private function topicNameFor(BotUser $botUser): string
    {
        (new TopicCreateJob($botUser->id))->handle();

        $name = null;
        Http::assertSent(function ($request) use (&$name) {
            if (str_contains($request->url(), '/createForumTopic')) {
                $name = $request->data()['name'] ?? null;

                return true;
            }

            return false;
        });

        return (string) $name;
    }

    public function test_ok_topic_uses_the_sender_name_with_an_empty_template(): void
    {
        app(SettingsService::class)->set('telegram.template_topic_name', '');

        $botUser = BotUser::create([
            'chat_id' => 'chat:C5b411ad1c700',
            'platform' => 'ok',
            'display_name' => 'Анна',
        ]);

        $this->assertSame('Анна (ok)', $this->topicNameFor($botUser));
    }

    public function test_ok_topic_without_a_name_hides_the_chat_prefix(): void
    {
        $botUser = BotUser::create([
            'chat_id' => 'chat:C5b411ad1c700',
            'platform' => 'ok',
        ]);

        $this->assertSame('#C5b411ad1c700 (ok)', $this->topicNameFor($botUser));
    }

    public function test_ok_topic_uses_the_template_when_it_is_set(): void
    {
        $botUser = BotUser::create([
            'chat_id' => 'chat:C5b411ad1c700',
            'platform' => 'ok',
            'display_name' => 'Анна',
        ]);

        $name = $this->topicNameFor($botUser);

        $this->assertStringContainsString('Анна', $name);
        $this->assertStringNotContainsString('chat:', $name);
    }

    public function test_other_platforms_keep_the_id_fallback(): void
    {
        app(SettingsService::class)->set('telegram.template_topic_name', '');

        $botUser = BotUser::create([
            'chat_id' => '34343434',
            'platform' => 'max',
            'display_name' => 'Анна',
        ]);

        $this->assertSame('#34343434 (max)', $this->topicNameFor($botUser));
    }
}
