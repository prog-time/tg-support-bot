<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\AiFaqEntry;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * «Новый вопрос» / «Редактирование вопроса» form screen.
 *
 * Create/edit form for an {@see AiFaqEntry} — question, reference answer and
 * an active toggle. On save the record is created or updated and the user is
 * redirected back to the FAQ list.
 *
 * Routes:
 *   GET /admin/settings/ai/faq/create         → name ai.faq.create
 *   GET /admin/settings/ai/faq/{entry}/edit   → name ai.faq.edit
 * Layout: layouts.admin-settings (dark sidebar 280px + content area).
 */
#[Layout('layouts.admin-settings')]
class AiFaqFormPage extends Component
{
    /**
     * Id of the edited entry (null when creating).
     */
    public ?int $entryId = null;

    /**
     * Customer question as it is usually asked.
     */
    public string $question = '';

    /**
     * Reference answer the AI should base its reply on.
     */
    public string $answer = '';

    /**
     * Whether the entry is fed to the AI.
     */
    public bool $enabled = true;

    /**
     * Boot the form. When an existing entry id is given, prefill the fields
     * from the database and switch the screen into edit mode.
     *
     * @param int|null $entry AiFaqEntry id, or null for the create screen.
     */
    public function mount(?int $entry = null): void
    {
        if ($entry === null) {
            return;
        }

        $existing = AiFaqEntry::find($entry);

        if ($existing === null) {
            return;
        }

        $this->entryId = $existing->id;
        $this->question = $existing->question;
        $this->answer = $existing->answer;
        $this->enabled = $existing->enabled;
    }

    /**
     * Whether the screen is editing an existing entry (vs. creating a new one).
     */
    public function isEdit(): bool
    {
        return $this->entryId !== null;
    }

    /**
     * Validate and persist the entry (create or update), then return to the list.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'enabled' => ['boolean'],
        ], [
            'question.required' => 'Введите вопрос.',
            'question.max' => 'Вопрос не должен превышать 500 символов.',
            'answer.required' => 'Введите ответ.',
            'answer.max' => 'Ответ не должен превышать 5000 символов.',
        ]);

        if ($this->entryId !== null) {
            AiFaqEntry::whereKey($this->entryId)->update($validated);
        } else {
            AiFaqEntry::create($validated);
        }

        $this->redirectRoute('admin.settings.ai.faq');
    }

    /**
     * Render the component view.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.settings.ai-faq-form-page', [
            'isEdit' => $this->isEdit(),
        ]);
    }
}
