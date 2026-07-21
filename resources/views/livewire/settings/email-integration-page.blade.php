<div>

    {{-- ── Body ─────────────────────────────────────────────────────────────── --}}
    <div class="p-4 lg:p-8">

        {{-- ── Two-column grid ──────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-4 lg:gap-7 lg:grid-cols-[1fr_320px]">

        {{-- ── Form Card ────────────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-border-light bg-bg-primary p-4 lg:px-8 lg:py-7">

            {{-- Card header: icon + titles --}}
            <div class="flex items-center gap-3.5">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="#6B7280" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-text-primary">Подключить Email</h2>
                    <p class="mt-0.5 text-xs text-text-secondary">
                        Приём писем по IMAP и отправка ответов по SMTP — работает с любым провайдером
                    </p>
                </div>
            </div>

            <div class="my-6 h-px bg-border-light"></div>

            <form wire:submit="connect" novalidate>
                @csrf

                <div class="space-y-5">

                    {{-- Username / password (shared by IMAP and SMTP) --}}
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-admin.form-field
                            label="Логин"
                            for="username"
                            hint="Обычно совпадает с адресом почты"
                            :required="true"
                            :error="$formErrors['username'] ?? null"
                        >
                            <input
                                id="username"
                                type="text"
                                wire:model="username"
                                autocomplete="off"
                                placeholder="support@example.com"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['username'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                        <x-admin.form-field
                            label="Пароль"
                            for="password"
                            hint="Для Gmail/Workspace — пароль приложения"
                            :required="true"
                            :error="$formErrors['password'] ?? null"
                        >
                            <input
                                id="password"
                                type="password"
                                wire:model="password"
                                autocomplete="new-password"
                                placeholder="{{ $hasPassword ? '•••••••••• (пароль сохранён)' : 'Введите пароль' }}"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['password'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>
                    </div>

                    {{-- IMAP --}}
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-text-secondary">IMAP (приём)</p>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-[2fr_1fr_1fr]">
                            <x-admin.form-field label="Хост" for="imap_host" :required="true" :error="$formErrors['imap_host'] ?? null">
                                <input id="imap_host" type="text" wire:model="imap_host" autocomplete="off" placeholder="imap.example.com"
                                    class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                        @if (!empty($formErrors['imap_host'])) border-red-400 @endif" />
                            </x-admin.form-field>

                            <x-admin.form-field label="Порт" for="imap_port">
                                <input id="imap_port" type="number" wire:model="imap_port" autocomplete="off"
                                    class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20" />
                            </x-admin.form-field>

                            <x-admin.form-field label="Шифрование" for="imap_encryption">
                                <select id="imap_encryption" wire:model="imap_encryption"
                                    class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20">
                                    <option value="ssl">SSL</option>
                                    <option value="tls">TLS</option>
                                    <option value="">Нет</option>
                                </select>
                            </x-admin.form-field>
                        </div>
                    </div>

                    {{-- SMTP --}}
                    <div>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-text-secondary">SMTP (отправка)</p>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-[2fr_1fr_1fr]">
                            <x-admin.form-field label="Хост" for="smtp_host" :required="true" :error="$formErrors['smtp_host'] ?? null">
                                <input id="smtp_host" type="text" wire:model="smtp_host" autocomplete="off" placeholder="smtp.example.com"
                                    class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                        @if (!empty($formErrors['smtp_host'])) border-red-400 @endif" />
                            </x-admin.form-field>

                            <x-admin.form-field label="Порт" for="smtp_port">
                                <input id="smtp_port" type="number" wire:model="smtp_port" autocomplete="off"
                                    class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20" />
                            </x-admin.form-field>

                            <x-admin.form-field label="Шифрование" for="smtp_encryption">
                                <select id="smtp_encryption" wire:model="smtp_encryption"
                                    class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">Нет</option>
                                </select>
                            </x-admin.form-field>
                        </div>
                    </div>

                    {{-- From address / name --}}
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-admin.form-field
                            label="Адрес отправителя"
                            for="from_address"
                            hint="Показывается пользователю в поле «От кого»"
                            :required="true"
                            :error="$formErrors['from_address'] ?? null"
                        >
                            <input
                                id="from_address"
                                type="text"
                                wire:model="from_address"
                                autocomplete="off"
                                placeholder="support@example.com"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['from_address'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                        <x-admin.form-field label="Имя отправителя" for="from_name" hint="Необязательно">
                            <input
                                id="from_name"
                                type="text"
                                wire:model="from_name"
                                autocomplete="off"
                                placeholder="Служба поддержки"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>
                    </div>

                </div>

                {{-- Actions row — right-aligned, verify-before-save --}}
                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-admin.button-primary type="submit" wire:loading.attr="disabled" wire:target="connect">
                        <span wire:loading.remove wire:target="connect">Сохранить</span>
                        <span wire:loading wire:target="connect">Проверка...</span>
                    </x-admin.button-primary>
                </div>

                {{-- Verify-before-save result notice --}}
                @if ($verifyMessage)
                    <div class="mt-4 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm
                        @if ($verifySuccess) border-green-200 bg-green-50 text-green-800
                        @else border-red-200 bg-red-50 text-red-800 @endif">
                        @if ($verifySuccess)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-green-500"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-red-500"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M6.938 19h10.124A2 2 0 0019 16.27L13.938 7A2 2 0 0010.062 7L5 16.27A2 2 0 006.938 19z" />
                            </svg>
                        @endif
                        {{ $verifyMessage }}
                    </div>
                @endif

            </form>
        </div>

        {{-- ── Instruction panel ────────────────────────────────────────────── --}}
        <div>
            <div class="rounded-xl border border-border-light bg-bg-primary p-4 lg:p-6">

                {{-- Panel header --}}
                <div class="mb-5 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="text-sm font-semibold text-text-primary">Инструкция</span>
                </div>

                {{-- Numbered steps --}}
                <ol class="space-y-3">
                    @php
                        $steps = [
                            'Активируйте лицензию в разделе «Подписки»',
                            'Для Gmail/Workspace создайте пароль приложения (обычный пароль аккаунта не подойдёт)',
                            'Укажите IMAP- и SMTP-хосты вашего почтового провайдера',
                            'Нажмите «Сохранить» — подключение проверится и по IMAP, и по SMTP',
                            'Письма опрашиваются автоматически раз в минуту (email:poll в планировщике)',
                        ];
                    @endphp
                    @foreach ($steps as $i => $step)
                        <li class="flex items-start gap-3">
                            <span class="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full text-[11px] font-semibold text-accent"
                                  style="background:#EEF2FF">
                                {{ $i + 1 }}
                            </span>
                            <span class="text-[13px] leading-relaxed text-text-secondary">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                {{-- Docs link plate --}}
                <a href="https://docs.tg-support-bot.ru/docs/email.html"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="mt-5 flex items-center gap-2 rounded-lg px-3.5 py-3 text-xs font-medium text-accent transition hover:opacity-80"
                   style="background:#F0F4FF">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Подробнее в документации
                </a>

            </div>
        </div>

        </div>{{-- /two-column grid --}}

    </div>{{-- /body wrapper --}}

</div>
