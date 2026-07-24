<div>

    {{-- ── Body ─────────────────────────────────────────────────────────────── --}}
    <div class="p-4 lg:p-8">

        {{-- ── Two-column grid ──────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-4 lg:gap-7 lg:grid-cols-[1fr_320px]">

        {{-- ── Form Card ────────────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-border-light bg-bg-primary p-4 lg:px-8 lg:py-7">

            {{-- Card header: icon + titles --}}
            <div class="flex items-center gap-3.5">
                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full" viewBox="0 0 90 90">
                        <g fill="none" fill-rule="evenodd">
                            <path fill="#FFF" d="M0 0h90v90H0z" />
                            <circle fill="#97CF26" cx="59" cy="59" r="16" />
                            <circle fill="#0AF" cx="29" cy="29" r="13" />
                            <circle fill="#FF6163" cx="59" cy="29" r="10" />
                            <circle fill="#A169F7" cx="28.5" cy="58.5" r="7.5" />
                        </g>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-text-primary">Подключить Avito</h2>
                    <p class="mt-0.5 text-xs text-text-secondary">
                        Доступ к Avito Messenger API — приём и отправка сообщений из объявлений
                    </p>
                </div>
            </div>

            <div class="my-6 h-px bg-border-light"></div>

            <form wire:submit="connect" novalidate>
                @csrf

                <div class="space-y-5">

                    {{-- Client ID --}}
                    <x-admin.form-field
                        label="Client ID"
                        for="client_id"
                        hint="Идентификатор OAuth-приложения Avito"
                        :required="true"
                        :error="$formErrors['client_id'] ?? null"
                    >
                        <input
                            id="client_id"
                            type="text"
                            wire:model="client_id"
                            autocomplete="off"
                            placeholder="например, a1b2c3d4e5..."
                            class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                @if (!empty($formErrors['client_id'])) border-red-400 @endif"
                        />
                    </x-admin.form-field>

                    {{-- Client Secret --}}
                    <x-admin.form-field
                        label="Client Secret"
                        for="client_secret"
                        hint="Секрет OAuth-приложения. Хранится в зашифрованном виде."
                        :required="true"
                        :error="$formErrors['client_secret'] ?? null"
                    >
                        <input
                            id="client_secret"
                            type="password"
                            wire:model="client_secret"
                            autocomplete="new-password"
                            placeholder="{{ $hasClientSecret ? '•••••••••• (секрет сохранён)' : 'Вставьте Client Secret' }}"
                            class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                @if (!empty($formErrors['client_secret'])) border-red-400 @endif"
                        />
                    </x-admin.form-field>

                    {{-- Base URL --}}
                    <x-admin.form-field
                        label="Base URL"
                        for="base_url"
                        hint="Адрес Avito API. Оставьте пустым для значения по умолчанию."
                        :error="$formErrors['base_url'] ?? null"
                    >
                        <input
                            id="base_url"
                            type="text"
                            wire:model="base_url"
                            autocomplete="off"
                            placeholder="https://api.avito.ru"
                            class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                        />
                    </x-admin.form-field>

                    {{-- Webhook secret --}}
                    <x-admin.form-field
                        label="Секретный ключ Webhook"
                        for="webhook_secret"
                        hint="Встраивается в URL вебхука для проверки входящих запросов. Необязательно."
                        :error="$formErrors['webhook_secret'] ?? null"
                    >
                        <input
                            id="webhook_secret"
                            type="password"
                            wire:model="webhook_secret"
                            autocomplete="new-password"
                            placeholder="{{ $hasWebhookSecret ? '•••••••••• (ключ сохранён)' : 'openssl rand -hex 32' }}"
                            class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                        />
                    </x-admin.form-field>

                </div>

                {{-- Resolved account id — captured automatically from accounts/self --}}
                @if ($user_id !== '')
                    <div class="mt-5 flex items-center justify-between gap-3 rounded-lg border border-border-light bg-bg-input px-3.5 py-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-text-secondary">ID аккаунта Avito</p>
                            <p class="mt-0.5 text-sm font-medium text-text-primary">{{ $user_id }}</p>
                        </div>
                        <span class="text-[11px] text-text-secondary">определяется автоматически</span>
                    </div>
                @endif

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

                {{-- Paid «API мессенджера» plan. Shown separately from the notice
                     above because valid credentials do not imply the channel can
                     receive anything: without this plan Avito accepts the webhook
                     subscription and then never calls it. --}}
                @if ($verifySuccess && $messengerMessage)
                    <div class="mt-3 flex items-start gap-2 rounded-xl border px-4 py-3 text-sm
                        @if ($messengerStatus === 'ok') border-green-200 bg-green-50 text-green-800
                        @elseif ($messengerStatus === 'no_subscription') border-red-200 bg-red-50 text-red-800
                        @else border-amber-200 bg-amber-50 text-amber-800 @endif">

                        @if ($messengerStatus === 'ok')
                            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-green-500"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0
                                        @if ($messengerStatus === 'no_subscription') text-red-500 @else text-amber-500 @endif"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M6.938 19h10.124A2 2 0 0019 16.27L13.938 7A2 2 0 0010.062 7L5 16.27A2 2 0 006.938 19z" />
                            </svg>
                        @endif

                        <span>{{ $messengerMessage }}</span>
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
                            'Создайте OAuth-приложение в кабинете Avito (Профиль → Настройки → API)',
                            'Скопируйте Client ID и Client Secret в поля слева',
                            'Нажмите «Сохранить» — подключение проверится, а ID аккаунта определится автоматически',
                            'Зарегистрируйте вебхук командой: docker exec -it pet php artisan avito:set-webhook',
                        ];
                        $webhookUrl = rtrim((string) config('app.url'), '/') . '/api/avito/bot';
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

                {{-- Webhook URL plate --}}
                <div class="mt-5 rounded-lg px-3.5 py-3" style="background:#F0F4FF">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-accent">URL вебхука</p>
                    <p class="mt-1 break-all text-[12px] text-text-secondary">{{ $webhookUrl }}</p>
                </div>

                {{-- Docs link plate --}}
                <a href="https://docs.tg-support-bot.ru/docs/avito.html"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="mt-3 flex items-center gap-2 rounded-lg px-3.5 py-3 text-xs font-medium text-accent transition hover:opacity-80"
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
