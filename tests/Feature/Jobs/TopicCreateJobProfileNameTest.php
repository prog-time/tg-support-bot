<?php

namespace Tests\Feature\Jobs;

use App\Models\BotUser;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the cross-platform forum-topic name (issue #205).
 *
 * The topic name used to be built only from Telegram's getChat, so a VK or MAX
 * conversation — whose id getChat cannot resolve — fell back to the bare
 * «#id (platform)». These tests pin that the stored BotUser profile now labels
 * the topic for non-Telegram platforms.
 *
 * The base TestCase seeds the template as «{first_name} {last_name} {platform}».
 */
class TopicCreateJobProfileNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // createForumTopic must succeed; sendContact (SendContactMessage) and any
        // other Telegram call is stubbed so the job never touches the network.
        Http::fake([
            'https://api.telegram.org/bot*/createForumTopic*' => Http::response([
                'ok' => true,
                'result' => ['message_thread_id' => 555],
            ], 200),
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);
    }

    /**
     * @return string The `name` sent to createForumTopic for the given BotUser.
     */
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

    public function test_max_topic_uses_stored_display_name(): void
    {
        $botUser = BotUser::create([
            'chat_id' => '77771111',
            'platform' => 'max',
            'display_name' => 'Иван Петров',
        ]);

        $name = $this->topicNameFor($botUser);

        $this->assertStringContainsString('Иван Петров', $name);
        $this->assertStringNotContainsString('#77771111', $name);
    }

    public function test_missing_last_name_does_not_collapse_the_topic_to_the_bare_id(): void
    {
        // Only a single name is stored (typical for VK/MAX), yet the template has
        // a {last_name} slot. It must resolve to the name, not the #id fallback.
        $botUser = BotUser::create([
            'chat_id' => '88882222',
            'platform' => 'max',
            'display_name' => 'Мария',
        ]);

        $name = $this->topicNameFor($botUser);

        $this->assertStringContainsString('Мария', $name);
        $this->assertStringNotContainsString('  ', $name, 'Whitespace from the empty {last_name} must be collapsed.');
    }

    public function test_vk_profile_is_enriched_on_demand_when_missing(): void
    {
        // VK carries no name in the webhook; it is fetched via users.get. The job
        // must pull it synchronously so the very first topic is already labelled.
        Http::fake([
            'https://api.vk.com/method/users.get*' => Http::response([
                'response' => [[
                    'id' => 99993333,
                    'first_name' => 'Пётр',
                    'last_name' => 'Сидоров',
                ]],
            ], 200),
            'https://api.telegram.org/bot*/createForumTopic*' => Http::response([
                'ok' => true,
                'result' => ['message_thread_id' => 556],
            ], 200),
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $botUser = BotUser::create([
            'chat_id' => '99993333',
            'platform' => 'vk',
        ]);

        $name = $this->topicNameFor($botUser);

        $this->assertStringContainsString('Пётр', $name);
        $this->assertSame('Пётр Сидоров', $botUser->fresh()->display_name);
    }

    public function test_topic_falls_back_to_id_when_no_profile_is_available(): void
    {
        // MAX with no stored name and nothing to enrich → the safe fallback.
        $botUser = BotUser::create([
            'chat_id' => '12121212',
            'platform' => 'max',
        ]);

        $name = $this->topicNameFor($botUser);

        $this->assertStringContainsString('#12121212', $name);
        $this->assertStringContainsString('max', $name);
    }

    public function test_empty_template_falls_back_to_id(): void
    {
        app(SettingsService::class)->set('telegram.template_topic_name', '');

        $botUser = BotUser::create([
            'chat_id' => '34343434',
            'platform' => 'max',
            'display_name' => 'Анна',
        ]);

        $name = $this->topicNameFor($botUser);

        $this->assertStringContainsString('#34343434', $name);
    }
}
