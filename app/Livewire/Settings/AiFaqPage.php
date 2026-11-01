<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\AiFaqEntry;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * «Частые вопросы» — AI knowledge-base list screen.
 *
 * Lists {@see AiFaqEntry} records (question / answer / status / actions).
 * Enabled entries are appended to the AI system prompt. Delete removes the
 * record; add/edit navigate to {@see AiFaqFormPage}.
 *
 * Route:  GET /admin/settings/ai/faq
 * Name:   admin.settings.ai.faq
 * Layout: layouts.admin-settings (dark sidebar 280px + content area).
 */
#[Layout('layouts.admin-settings')]
class AiFaqPage extends Component
{
    /**
     * Delete a FAQ entry by id.
     *
     * @param int $id
     */
    public function deleteEntry(int $id): void
    {
        AiFaqEntry::whereKey($id)->delete();
    }

    /**
     * Toggle whether a FAQ entry is fed to the AI.
     *
     * @param int $id
     */
    public function toggleEntry(int $id): void
    {
        $entry = AiFaqEntry::find($id);

        if ($entry === null) {
            return;
        }

        $entry->update(['enabled' => ! $entry->enabled]);
    }

    /**
     * Russian-pluralised label for the entries counter ("4 вопроса").
     *
     * @param int $count
     *
     * @return string
     */
    public function entriesCountLabel(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            $word = 'вопрос';
        } elseif ($mod10 >= 2 && $mod10 <= 4 && ! ($mod100 >= 12 && $mod100 <= 14)) {
            $word = 'вопроса';
        } else {
            $word = 'вопросов';
        }

        return "{$count} {$word}";
    }

    /**
     * Render the component view.
     *
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        /** @var Collection<int, AiFaqEntry> $entries */
        $entries = AiFaqEntry::orderBy('id')->get();

        return view('livewire.settings.ai-faq-page', [
            'entries' => $entries,
        ]);
    }
}
