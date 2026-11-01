<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Settings;

use App\Livewire\Settings\AiFaqFormPage;
use App\Models\AiFaqEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiFaqFormPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_mode_renders_blank_form_with_new_title(): void
    {
        Livewire::test(AiFaqFormPage::class)
            ->assertSet('question', '')
            ->assertSet('answer', '')
            ->assertSet('entryId', null)
            ->assertSee('Новый вопрос')
            ->assertSee('Вопрос и ответ');
    }

    public function test_edit_mode_prefills_fields_from_the_database(): void
    {
        $entry = AiFaqEntry::create(['question' => 'Доставка?', 'answer' => '300 ₽', 'enabled' => false]);

        Livewire::test(AiFaqFormPage::class, ['entry' => $entry->id])
            ->assertSet('entryId', $entry->id)
            ->assertSet('question', 'Доставка?')
            ->assertSet('answer', '300 ₽')
            ->assertSet('enabled', false)
            ->assertSee('Редактирование вопроса');
    }

    public function test_unknown_id_falls_back_to_create_mode(): void
    {
        Livewire::test(AiFaqFormPage::class, ['entry' => 999])
            ->assertSet('entryId', null)
            ->assertSet('question', '');
    }

    public function test_save_creates_a_new_entry_and_redirects(): void
    {
        Livewire::test(AiFaqFormPage::class)
            ->set('question', 'Сколько стоит доставка?')
            ->set('answer', '300 ₽, бесплатно от 3000 ₽.')
            ->set('enabled', true)
            ->call('save')
            ->assertRedirect(route('admin.settings.ai.faq'));

        $this->assertDatabaseHas('ai_faq_entries', [
            'question' => 'Сколько стоит доставка?',
            'answer' => '300 ₽, бесплатно от 3000 ₽.',
            'enabled' => true,
        ]);
    }

    public function test_save_updates_an_existing_entry_without_creating_a_duplicate(): void
    {
        $entry = AiFaqEntry::create(['question' => 'Доставка?', 'answer' => 'Старый ответ', 'enabled' => true]);

        Livewire::test(AiFaqFormPage::class, ['entry' => $entry->id])
            ->set('answer', 'Новый ответ')
            ->set('enabled', false)
            ->call('save')
            ->assertRedirect(route('admin.settings.ai.faq'));

        $this->assertDatabaseHas('ai_faq_entries', [
            'id' => $entry->id,
            'answer' => 'Новый ответ',
            'enabled' => false,
        ]);
        $this->assertSame(1, AiFaqEntry::query()->count());
    }

    public function test_save_requires_question_and_answer(): void
    {
        Livewire::test(AiFaqFormPage::class)
            ->set('question', '')
            ->set('answer', '')
            ->call('save')
            ->assertHasErrors(['question', 'answer']);

        $this->assertDatabaseCount('ai_faq_entries', 0);
    }

    public function test_save_rejects_too_long_question(): void
    {
        Livewire::test(AiFaqFormPage::class)
            ->set('question', str_repeat('а', 501))
            ->set('answer', 'Ответ')
            ->call('save')
            ->assertHasErrors(['question' => 'max']);

        $this->assertDatabaseCount('ai_faq_entries', 0);
    }
}
