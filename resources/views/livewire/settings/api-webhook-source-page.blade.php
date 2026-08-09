<div>

    {{-- ── Body ─────────────────────────────────────────────────────────────── --}}
    <div class="p-4 lg:p-8">

        {{-- ── Notices ───────────────────────────────────────────────────────── --}}
        @if ($tokenError)
            <div class="mb-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-red-500"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v2m0 4h.01M6.938 19h10.124A2 2 0 0019 16.27L13.938 7A2 2 0 0010.062 7L5 16.27A2 2 0 006.938 19z" />
                </svg>
                {{ $tokenError }}
            </div>
        @endif

        {{-- ── Two-column grid ──────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-4 lg:gap-7 lg:grid-cols-[1fr_320px]">

            {{-- ── Form Card ────────────────────────────────────────────────────── --}}
            <div class="rounded-2xl border border-border-light bg-bg-primary p-4 lg:px-8 lg:py-7">

            {{-- Card header: icon + titles --}}
            <div class="flex items-center gap-3.5">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" style="background:#EEF2FF">
                    @if ($isWidget)
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-accent" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-accent" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    @endif
                </div>
                <div>
                    <h2 class="text-lg font-bold text-text-primary">{{ $sourceName }}</h2>
                    <p class="mt-0.5 text-xs text-text-secondary">
                        {{ $isWidget ? 'Публичный ключ для JS-виджета живого чата' : 'Bearer-токен и вебхук внешнего источника' }}
                    </p>
                </div>
            </div>

            {{-- ── Source name ─────────────────────────────────────────────────── --}}
            <x-admin.form-field
                label="Название источника"
                for="sourceName"
                hint="Отображается в списке источников"
                :error="$nameError"
                class="mt-6"
            >
                <input
                    id="sourceName"
                    type="text"
                    wire:model="sourceName"
                    placeholder="Например: CRM, Интернет-магазин"
                    autocomplete="off"
                    class="block w-full rounded-lg border bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 {{ $nameError ? 'border-red-400' : 'border-border-light' }}"
                />
            </x-admin.form-field>

            <div class="my-6 h-px bg-border-light"></div>

            {{-- ── URL вебхука ───────────────────────────────────────────────── --}}
            <div class="space-y-5">

                @unless ($isWidget)
                    <x-admin.form-field
                        label="URL вебхука"
                        for="webhook_url"
                        hint="URL для получения событий"
                        :error="$webhookError"
                    >
                        <input
                            id="webhook_url"
                            type="url"
                            wire:model="webhookUrl"
                            placeholder="https://example.com/webhook"
                            class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                @if ($webhookError) border-red-400 @endif"
                        />
                    </x-admin.form-field>
                @endunless

                {{-- Разрешённые IP (API) или домены (виджет) — WidgetGate checks domains, ApiQuery checks IP only --}}
                <x-admin.form-field
                    :label="$isWidget ? 'Разрешённые домены' : 'Разрешённые IP-адреса'"
                    for="allowed_ips"
                    :hint="$isWidget
                        ? 'По одной записи в строке. Домены сайтов, где будет встроен виджет (например: example.com, *.example.com для поддоменов). Пусто — без ограничений.'
                        : 'По одной записи в строке. IP-адреса, с которых разрешены запросы к API. Пусто — без ограничений.'"
                    :error="$allowedIpsError"
                >
                    <textarea
                        id="allowed_ips"
                        wire:model="allowedIps"
                        rows="4"
                        placeholder="{{ $isWidget ? "example.com\n*.example.com" : "203.0.113.10\n198.51.100.5" }}"
                        class="block w-full resize-y rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 font-mono text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                            @if ($allowedIpsError) border-red-400 @endif"
                    ></textarea>
                </x-admin.form-field>

            </div>

            {{-- Actions row — right-aligned «Сохранить» --}}
            <div class="mt-6 flex items-center justify-end gap-3">
                <x-admin.button-primary type="button" wire:click="saveWebhookUrl" wire:loading.attr="disabled" wire:target="saveWebhookUrl">
                    <span wire:loading.remove wire:target="saveWebhookUrl">Сохранить</span>
                    <span wire:loading wire:target="saveWebhookUrl">Сохранение...</span>
                </x-admin.button-primary>
            </div>

            {{-- Save result notice --}}
            @if ($saved)
                <div class="mt-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-green-500"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $isWidget ? 'Настройки сохранены.' : 'URL вебхука сохранён.' }}
                </div>
            @endif

            @unless ($isWidget)
            <div class="my-6 h-px bg-border-light"></div>

            {{-- ── API-ключ block ────────────────────────────────────────────── --}}
            <div class="space-y-4">

                <div class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-medium text-text-primary">Ключ API</span>
                    <div class="flex items-center rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5">
                        <span class="flex-1 truncate font-mono text-sm text-text-primary">
                            @if ($hasToken)
                                ••••••••••••••••••••••••<span class="text-text-secondary">{{ $tokenLast6 }}</span>
                            @else
                                <span class="italic text-text-secondary">токен не выпущен</span>
                            @endif
                        </span>
                    </div>
                    <span class="text-xs text-text-secondary">Используйте для интеграции с внешними сервисами</span>
                </div>

                {{-- One-time reveal banner --}}
                @if ($newToken)
                    <div class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-green-600"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <div class="min-w-0 flex-1">
                            <p class="mb-1 text-xs font-semibold text-green-800">Новый токен сгенерирован. Скопируйте его — он больше не будет показан.</p>
                            <code class="block break-all rounded bg-green-100 px-2 py-1 font-mono text-xs text-green-900 select-all">{{ $newToken }}</code>
                        </div>
                        <button type="button"
                                wire:click="dismissNewToken"
                                class="shrink-0 text-green-500 transition hover:text-green-700"
                                aria-label="Закрыть">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endif

                {{-- Key actions --}}
                <div class="flex flex-wrap items-center gap-3">
                    @if ($hasToken)
                        <x-admin.button-secondary
                            type="button"
                            x-data
                            @click.prevent="if (navigator.clipboard) { navigator.clipboard.writeText('{{ $copyToken }}'); }"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-4 w-4" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                            </svg>
                            Скопировать
                        </x-admin.button-secondary>
                    @endif

                    <x-admin.button-primary
                        type="button"
                        wire:click="regenerateToken"
                        wire:loading.attr="disabled"
                        wire:target="regenerateToken"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-4 w-4" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span wire:loading.remove wire:target="regenerateToken">Сгенерировать новый</span>
                        <span wire:loading wire:target="regenerateToken">Генерация...</span>
                    </x-admin.button-primary>
                </div>
            </div>
            @endunless

        {{-- ── Публичный ключ виджета ──────────────────────────────────────── --}}
        @if ($isWidget)
        <div class="mt-8">

            <div class="my-6 h-px bg-border-light"></div>

            {{-- Public key error --}}
            @if ($publicKeyError)
                <div class="mb-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-red-500"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v2m0 4h.01M6.938 19h10.124A2 2 0 0019 16.27L13.938 7A2 2 0 0010.062 7L5 16.27A2 2 0 006.938 19z" />
                    </svg>
                    {{ $publicKeyError }}
                </div>
            @endif

            <div class="space-y-4">

                <div class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-medium text-text-primary">Публичный ключ виджета</span>
                    <div class="flex items-center rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5">
                        <span class="flex-1 truncate font-mono text-sm text-text-primary">
                            @if ($publicKey)
                                ••••••••••••••••••••••••••••••••<span class="text-text-secondary">{{ substr($publicKey, -8) }}</span>
                            @else
                                <span class="italic text-text-secondary">ключ не сгенерирован</span>
                            @endif
                        </span>
                    </div>
                    <span class="text-xs text-text-secondary">Используйте в JS-виджете через заголовок X-Widget-Key</span>
                </div>

                {{-- One-time reveal banner for new public key --}}
                @if ($newPublicKey)
                    <div class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0 text-green-600"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <div class="min-w-0 flex-1">
                            <p class="mb-1 text-xs font-semibold text-green-800">Новый публичный ключ сгенерирован. Скопируйте его — он больше не будет показан.</p>
                            <code class="block break-all rounded bg-green-100 px-2 py-1 font-mono text-xs text-green-900 select-all">{{ $newPublicKey }}</code>
                        </div>
                        <button type="button"
                                wire:click="dismissPublicKey"
                                class="shrink-0 text-green-500 transition hover:text-green-700"
                                aria-label="Закрыть">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endif

                {{-- Generate key button --}}
                <div class="flex flex-wrap items-center gap-3">
                    <x-admin.button-primary
                        type="button"
                        wire:click="generatePublicKey"
                        wire:loading.attr="disabled"
                        wire:target="generatePublicKey"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-4 w-4" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span wire:loading.remove wire:target="generatePublicKey">Сгенерировать ключ</span>
                        <span wire:loading wire:target="generatePublicKey">Генерация...</span>
                    </x-admin.button-primary>
                </div>

            </div>

            {{-- Ready-to-paste embed snippet --}}
            @if ($publicKey)
                <div class="mt-6 flex flex-col gap-1.5">
                    <span class="text-[13px] font-medium text-text-primary">Код для вставки на сайт</span>
                    <code class="block break-all rounded-lg border border-border-light bg-bg-input px-3.5 py-3 font-mono text-xs text-text-primary select-all">&lt;script src="{{ rtrim(config('app.url'), '/') }}/widget/widget.js" data-domain="{{ rtrim(config('app.url'), '/') }}" data-key="{{ $publicKey }}" defer&gt;&lt;/script&gt;</code>
                    <span class="text-xs text-text-secondary">Вставьте перед закрывающим &lt;/body&gt; на стороннем сайте.</span>
                </div>
            @endif

        </div>{{-- /public key block --}}
        @endif

        </div>

        {{-- ── Instruction panel (REST API reference / live-chat embed) ─────── --}}
        <div>
            <div class="rounded-xl border border-border-light bg-bg-primary p-4 lg:p-6">

                @if ($isWidget)
                    {{-- Panel header --}}
                    <div class="mb-5 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-semibold text-text-primary">Живой чат</span>
                    </div>

                    <p class="mb-3 text-xs text-text-secondary">
                        Сгенерируйте публичный ключ слева, затем вставьте готовый код (появится под кнопкой «Сгенерировать ключ») перед закрывающим &lt;/body&gt; на стороннем сайте.
                    </p>

                    <div class="rounded-lg bg-bg-input px-3 py-2.5">
                        <p class="mb-1 text-[11px] font-semibold text-text-primary">Разрешённые домены</p>
                        <p class="text-[11px] text-text-secondary">Укажите домен сайта в поле «Разрешённые домены» слева — иначе виджет будет работать с любого сайта, использующего этот ключ.</p>
                    </div>
                @else
                    {{-- Panel header --}}
                    <div class="mb-5 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm font-semibold text-text-primary">REST API</span>
                    </div>

                    {{-- Base URL --}}
                    <p class="mb-3 text-xs text-text-secondary">Базовый URL:</p>
                    <code class="mb-4 block break-all rounded bg-bg-input px-2.5 py-1.5 font-mono text-xs text-text-primary">{{ rtrim(config('app.url'), '/') }}</code>

                    {{-- Auth note --}}
                    <div class="mt-4 rounded-lg bg-bg-input px-3 py-2.5">
                        <p class="mb-1 text-[11px] font-semibold text-text-primary">Авторизация</p>
                        <code class="block font-mono text-[11px] text-text-secondary">Authorization: Bearer {token}</code>
                    </div>

                    {{-- Swagger link plate --}}
                    <a href="/docs/swagger-v1-ui"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="mt-5 flex items-center gap-2 rounded-lg px-3.5 py-3 text-xs font-medium text-accent transition hover:opacity-80"
                       style="background:#F0F4FF">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Открыть Swagger UI
                    </a>
                @endif

            </div>
        </div>

        </div>{{-- /two-column grid --}}

    </div>{{-- /body wrapper --}}

</div>
