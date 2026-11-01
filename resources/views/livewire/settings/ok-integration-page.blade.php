<div>
    <div class="p-4 lg:p-8">
        <div class="grid grid-cols-1 gap-4 lg:gap-7 lg:grid-cols-[1fr_320px]">

        <div class="rounded-2xl border border-border-light bg-bg-primary p-4 lg:px-8 lg:py-7">

            <div class="flex items-center gap-3.5">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-base font-bold text-white" style="background:#EE8208">OK</div>
                <div>
                    <h2 class="text-lg font-bold text-text-primary">Подключить Одноклассники</h2>
                    <p class="mt-0.5 text-xs text-text-secondary">Приём и отправка сообщений группы через Bot API</p>
                </div>
            </div>

            <div class="my-6 h-px bg-border-light"></div>

            <form wire:submit="connect" novalidate>
                @csrf

                <div class="space-y-5">
                    <x-admin.form-field
                        label="Токен группы"
                        for="token"
                        hint="Новый токен в настройках группы отменяет старый: после перевыпуска введите его здесь и нажмите «Сохранить»."
                        :required="true"
                        :error="$formErrors['token'] ?? null"
                    >
                        <input
                            id="token"
                            type="password"
                            wire:model="token"
                            autocomplete="new-password"
                            placeholder="{{ $hasToken ? '•••••••••• (токен сохранён)' : 'Вставьте токен группы' }}"
                            class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                @if (!empty($formErrors['token'])) border-red-400 @endif"
                        />
                    </x-admin.form-field>

                    <x-admin.form-field
                        label="Секретный ключ вебхука"
                        for="webhook_secret"
                        hint="Встраивается в URL вебхука. Без него входящие запросы отклоняются."
                        :required="true"
                        :error="$formErrors['webhook_secret'] ?? null"
                    >
                        <input
                            id="webhook_secret"
                            type="password"
                            wire:model="webhook_secret"
                            autocomplete="new-password"
                            placeholder="{{ $hasWebhookSecret ? '•••••••••• (ключ сохранён)' : 'openssl rand -hex 32' }}"
                            class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                @if (!empty($formErrors['webhook_secret'])) border-red-400 @endif"
                        />
                    </x-admin.form-field>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-admin.button-primary type="submit" wire:loading.attr="disabled" wire:target="connect">
                        <span wire:loading.remove wire:target="connect">Сохранить</span>
                        <span wire:loading wire:target="connect">Проверка...</span>
                    </x-admin.button-primary>
                </div>

                @if ($verifyMessage)
                    <div class="mt-4 rounded-xl border px-4 py-3 text-sm
                        @if ($verifySuccess) border-green-200 bg-green-50 text-green-800
                        @else border-red-200 bg-red-50 text-red-800 @endif">
                        {{ $verifyMessage }}
                    </div>
                @endif
            </form>
        </div>

        <div>
            <div class="rounded-xl border border-border-light bg-bg-primary p-4 lg:p-6">
                <div class="mb-5 text-sm font-semibold text-text-primary">Инструкция</div>

                <ol class="space-y-3">
                    @php
                        $steps = [
                            'Откройте настройки группы OK → «Сообщения» → «Работа с API» и получите ключ доступа. Это может только администратор группы',
                            'Вставьте токен и придумайте секретный ключ',
                            'Нажмите «Сохранить»: токен проверится запросом к OK, а вебхук подпишется автоматически',
                        ];
                    @endphp
                    @foreach ($steps as $i => $step)
                        <li class="flex items-start gap-3">
                            <span class="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full text-[11px] font-semibold text-accent" style="background:#EEF2FF">{{ $i + 1 }}</span>
                            <span class="text-[13px] leading-relaxed text-text-secondary">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-5 rounded-lg px-3.5 py-3" style="background:#F0F4FF">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-accent">URL вебхука</p>
                    <p class="mt-1 break-all text-[12px] text-text-secondary">{{ rtrim((string) config('app.url'), '/') }}/api/ok/bot/&lt;секретный ключ&gt;</p>
                </div>
            </div>
        </div>

        </div>
    </div>
</div>
