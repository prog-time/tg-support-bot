<div class="p-6 lg:p-8">

    {{-- ── Page header: back to AI settings + title ───────────────────────────────── --}}
    <div class="mb-6 flex items-start gap-3">
        <a href="{{ route('admin.settings.ai') }}" wire:navigate
           class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border-light text-text-secondary transition hover:bg-bg-secondary hover:text-text-primary lg:flex"
           aria-label="К настройкам ИИ-ассистента">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5m7-7-7 7 7 7" />
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-text-primary">Частые вопросы</h1>
            <p class="mt-1 text-sm text-text-secondary">Готовые ответы, на которые ИИ-ассистент опирается при ответе клиентам</p>
        </div>
    </div>

    {{-- ── Top row: counter + add button ──────────────────────────────────────────── --}}
    <div class="mb-4 flex items-center justify-between gap-3">
        <span class="text-sm text-text-secondary">{{ $this->entriesCountLabel(count($entries)) }}</span>

        <a href="{{ route('admin.settings.ai.faq.create') }}"
           class="inline-flex shrink-0 items-center justify-center rounded-[10px] bg-accent px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Добавить вопрос
        </a>
    </div>

    {{-- ── Entries list ───────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-border-light bg-bg-primary">

        @forelse ($entries as $entry)

            @if (! $loop->first)
                <div class="border-t border-border-light"></div>
            @endif

            <div class="flex flex-col gap-3 px-4 py-4 lg:flex-row lg:items-start lg:gap-6 lg:px-6">

                {{-- Question + answer --}}
                <div class="min-w-0 flex-1 {{ $entry->enabled ? '' : 'opacity-60' }}">
                    <div class="flex min-w-0 items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="break-words text-sm font-medium text-text-primary">{{ $entry->question }}</span>
                    </div>
                    <p class="mt-1.5 line-clamp-3 whitespace-pre-line break-words pl-6 text-[13px] text-text-secondary">{{ $entry->answer }}</p>
                </div>

                {{-- Actions --}}
                <div class="flex shrink-0 items-center gap-3 pl-6 lg:pl-0">
                    <button type="button" wire:click="toggleEntry({{ $entry->id }})"
                            class="rounded-full px-2.5 py-1 text-[11px] font-medium transition {{ $entry->enabled ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-bg-input text-text-secondary hover:bg-border-light' }}"
                            title="{{ $entry->enabled ? 'Выключить' : 'Включить' }}">
                        {{ $entry->enabled ? 'Активен' : 'Выключен' }}
                    </button>
                    <a href="{{ route('admin.settings.ai.faq.edit', ['entry' => $entry->id]) }}"
                       class="flex h-8 w-8 items-center justify-center rounded-lg text-text-secondary transition hover:bg-bg-secondary hover:text-accent" title="Редактировать">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
                        </svg>
                    </a>
                    <button type="button"
                            wire:click="deleteEntry({{ $entry->id }})"
                            wire:confirm="Удалить вопрос «{{ \Illuminate\Support\Str::limit($entry->question, 60) }}»?"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-text-secondary transition hover:bg-bg-secondary hover:text-red-500" title="Удалить">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>

        @empty
            <div class="px-6 py-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 h-8 w-8 text-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-text-secondary">Вопросов пока нет. Добавьте первый вопрос и ответ на него.</p>
            </div>
        @endforelse
    </div>

</div>
