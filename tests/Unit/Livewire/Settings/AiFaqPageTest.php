<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Settings;

use App\Livewire\Settings\AiFaqPage;
use App\Models\AiFaqEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiFaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_page_title(): void
    {
        Livewire::test(AiFaqPage::class)
            ->assertOk()
            ->assertSee('Частые вопросы')
            ->assertSee('Добавить вопрос');
    }

    public function test_lists_entries_from_the_database(): void
    {
        AiFaqEntry::create(['question' => 'Доставка?', 'answer' => '300 ₽', 'enabled' => true]);
        AiFaqEntry::create(['question' => 'Возврат?', 'answer' => '14 дней', 'enabled' => false]);

        Livewire::test(AiFaqPage::class)
            ->assertSee('Доставка?')
            ->assertSee('Возврат?')
            ->assertSee('Активен')
            ->assertSee('Выключен')
            ->assertSee('2 вопроса');
    }

    public function test_shows_empty_state_when_no_entries(): void
    {
        Livewire::test(AiFaqPage::class)
            ->assertSee('Вопросов пока нет')
            ->assertSee('0 вопросов');
    }

    public function test_delete_entry_removes_it_from_the_database(): void
    {
        $entry = AiFaqEntry::create(['question' => 'Доставка?', 'answer' => '300 ₽', 'enabled' => true]);

        Livewire::test(AiFaqPage::class)
            ->call('deleteEntry', $entry->id)
            ->assertDontSee('Доставка?');

        $this->assertDatabaseMissing('ai_faq_entries', ['id' => $entry->id]);
    }

    public function test_toggle_entry_flips_enabled_flag(): void
    {
        $entry = AiFaqEntry::create(['question' => 'Доставка?', 'answer' => '300 ₽', 'enabled' => true]);

        $component = Livewire::test(AiFaqPage::class)->call('toggleEntry', $entry->id);
        $this->assertFalse($entry->fresh()?->enabled);

        $component->call('toggleEntry', $entry->id);
        $this->assertTrue($entry->fresh()?->enabled);
    }

    public function test_toggle_unknown_entry_is_a_no_op(): void
    {
        Livewire::test(AiFaqPage::class)
            ->call('toggleEntry', 999)
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.settings.ai.faq'))->assertRedirectContains('/admin/login');
    }

    #[DataProvider('entriesCountProvider')]
    public function test_entries_count_label_uses_russian_plural(int $count, string $expected): void
    {
        $component = Livewire::test(AiFaqPage::class);

        $this->assertSame($expected, $component->instance()->entriesCountLabel($count));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function entriesCountProvider(): array
    {
        return [
            'one' => [1, '1 вопрос'],
            'two' => [2, '2 вопроса'],
            'five' => [5, '5 вопросов'],
            'eleven' => [11, '11 вопросов'],
            'twenty-one' => [21, '21 вопрос'],
        ];
    }
}
