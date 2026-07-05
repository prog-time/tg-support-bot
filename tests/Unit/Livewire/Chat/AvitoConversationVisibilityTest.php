<?php

namespace Tests\Unit\Livewire\Chat;

use App\Livewire\Chat\ConversationPage;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reproduces the "Avito webhook passed (200) but nothing shows in /admin/chats"
 * report. The admin workspace reads exclusively from `bot_users` + `messages`
 * (no platform filter), so an Avito message is visible IFF the module persisted
 * a `messages` row (message_type='incoming') for a BotUser(platform='avito').
 */
class AvitoConversationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * The contract the module MUST satisfy: BotUser + incoming Message row →
     * the dialog appears in the list and the text shows in the thread.
     */
    public function test_avito_message_appears_when_module_persists_a_messages_row(): void
    {
        $botUser = BotUser::create(['chat_id' => 98765432, 'platform' => 'avito']);

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'avito',
            'message_type' => 'incoming',
            'from_id' => 98765432,
            'to_id' => 0,
            'text' => 'Здравствуйте, товар ещё в наличии?',
        ]);

        $component = Livewire::test(ConversationPage::class);

        // Dialog appears in the list.
        $this->assertTrue(
            $component->get('dialogList')->contains(fn ($u) => $u->id === $botUser->id),
            'Avito dialog is missing from the dialog list',
        );

        // Opening it shows the incoming text in the thread.
        $component->call('selectChat', $botUser->id);

        $this->assertTrue(
            $component->get('chatMessages')->contains(fn ($m) => $m->text === 'Здравствуйте, товар ещё в наличии?'),
            'Avito incoming message is missing from the thread',
        );
    }

    /**
     * The suspected failure mode: the module created the BotUser but did NOT
     * write a row to the shared `messages` table (e.g. it saved to its own
     * table). The dialog may still list, but the thread is EMPTY — exactly the
     * "message did not appear" symptom.
     */
    public function test_thread_is_empty_when_module_skips_the_messages_table(): void
    {
        $botUser = BotUser::create(['chat_id' => 98765432, 'platform' => 'avito']);

        $component = Livewire::test(ConversationPage::class)
            ->call('selectChat', $botUser->id);

        $this->assertTrue(
            $component->get('chatMessages')->isEmpty(),
            'Thread should be empty when no messages row was persisted',
        );
    }
}
