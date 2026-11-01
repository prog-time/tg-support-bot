<div>

    <div class="p-4 lg:p-8">

        {{-- Header: back-to-list button + title --}}
        <div class="mb-5 flex items-center gap-3">
            <a href="{{ route('admin.settings.ai.faq') }}"
               class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border-light text-text-secondary transition hover:bg-bg-secondary hover:text-text-primary"
               aria-label="К списку частых вопросов">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5m7-7-7 7 7 7" />
                </svg>
            </a>
            <h1 class="text-lg font-bold text-text-primary lg:text-2xl">
                {{ $isEdit ? 'Редактирование вопроса' : 'Новый вопрос' }}
            </h1>
        </div>

        <div class="space-y-5 rounded-xl border border-border-light bg-bg-primary p-4 lg:p-6">

            <h2 class="text-base font-semibold text-text-primary">Вопрос и ответ</h2>

            <x-admin.form-field
                label="Вопрос"
                for="question"
                hint="Как клиенты обычно его задают — ИИ сопоставляет по смыслу, а не дословно"
                :error="$errors->first('question')"
            >
                <input
                    id="question"
                    type="text"
                    wire:model="question"
                    maxlength="500"
                    placeholder="Например: Сколько стоит доставка?"
                    autocomplete="off"
                    class="block h-[42px] w-full rounded-lg border bg-bg-primary px-3.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 {{ $errors->has('question') ? 'border-red-400' : 'border-border-light' }}"
                />
            </x-admin.form-field>

            <x-admin.form-field
                label="Ответ"
                for="answer"
                hint="Факты, на которые ИИ опирается. Сформулирует ответ своими словами в стиле системного промпта"
                :error="$errors->first('answer')"
            >
                <textarea
                    id="answer"
                    wire:model="answer"
                    rows="8"
                    maxlength="5000"
                    placeholder="Например: Доставка по Москве — 300 ₽, бесплатно от 3000 ₽. По России — по тарифам СДЭК, 3–7 дней."
                    class="block w-full resize-y rounded-lg border bg-bg-primary p-3.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 {{ $errors->has('answer') ? 'border-red-400' : 'border-border-light' }}"
                ></textarea>
            </x-admin.form-field>

            <div class="flex items-center justify-between gap-3 pt-1">
                <span class="text-sm font-medium text-text-primary">Использовать в ответах ИИ</span>
                <x-admin.toggle wire:model="enabled" />
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <x-admin.button-primary type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Сохранить</span>
                <span wire:loading wire:target="save">Сохраняем...</span>
            </x-admin.button-primary>
        </div>
    </div>

</div>
