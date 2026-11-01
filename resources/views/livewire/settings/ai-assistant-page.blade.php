<div class="p-6 lg:p-8">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-text-primary">ИИ-ассистент</h1>
        <p class="mt-1 text-sm text-text-secondary">Настройка AI-помощника для автоматических ответов</p>
    </div>

    <form wire:submit="save" novalidate class="space-y-6">

        {{-- ── Master toggle ───────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between rounded-xl border border-border-light bg-bg-primary p-5 lg:px-6">
            <div class="flex items-center gap-3.5">
                <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl" style="background:#EEF2FF">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[15px] font-semibold text-text-primary">Включить AI помощника</p>
                    <p class="mt-0.5 text-[13px] text-text-secondary">ИИ будет помогать операторам составлять ответы · применяется сразу</p>
                </div>
            </div>
            <x-admin.toggle name="ai_enabled" id="ai_enabled" wire:model.live="ai_enabled" />
        </div>

        {{-- All detail settings are hidden while the assistant is disabled --}}
        @if ($ai_enabled)

        {{-- ── AI provider ─────────────────────────────────────────────────── --}}
        <div>
            <h2 class="mb-4 text-base font-semibold text-text-primary">AI-провайдер</h2>

            @if (!empty($formErrors['default_provider']))
                <p class="mb-3 text-xs text-red-500">{{ $formErrors['default_provider'] }}</p>
            @endif

            @php
                $providers = [
                    'openai' => ['name' => 'OpenAI', 'desc' => 'AI-провайдер от OpenAI с моделями GPT-4o и GPT-4o-mini.', 'bg' => '#ECFDF5', 'color' => '#10A37F'],
                    'deepseek' => ['name' => 'DeepSeek', 'desc' => 'AI-провайдер от DeepSeek с моделями DeepSeek-V3 и R1.', 'bg' => '#EEF2FF', 'color' => '#4F6EF7'],
                    'gigachat' => ['name' => 'GigaChat', 'desc' => 'AI-провайдер от Сбера с моделями GigaChat Pro и Lite.', 'bg' => '#FFF3E0', 'color' => '#F57C00'],
                ];
            @endphp

            <div class="space-y-4">
                @foreach ($providers as $slug => $p)
                    @php
                        $active = $default_provider === $slug;
                        $configured = $providerConfigured[$slug] ?? false;
                    @endphp
                    <div class="rounded-xl bg-bg-primary p-5 lg:px-6 {{ $active ? 'border-2' : 'border border-border-light' }} {{ ! $configured && ! $active ? 'opacity-75' : '' }}"
                         @if ($active) style="border-color:#10A37F" @endif>

                        {{-- Top row --}}
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" style="background:{{ $p['bg'] }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" style="color:{{ $p['color'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-semibold text-text-primary">{{ $p['name'] }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                @if (! $active && $configured)
                                    <button type="button" wire:click="$set('default_provider', '{{ $slug }}')"
                                            class="inline-flex items-center justify-center rounded-lg bg-bg-input px-3.5 py-1.5 text-xs font-medium text-text-primary transition hover:bg-border-light">
                                        Выбрать
                                    </button>
                                @endif
                                <a href="{{ route('admin.settings.ai.provider', $slug) }}" wire:navigate
                                   class="flex h-8 w-8 items-center justify-center rounded-lg text-text-secondary transition hover:bg-bg-input hover:text-text-primary"
                                   aria-label="Настроить доступ {{ $p['name'] }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </a>
                            </div>
                        </div>

                        {{-- Description --}}
                        <p class="mt-4 text-[13px] text-text-secondary">{{ $p['desc'] }}</p>

                        {{-- Details — shown identically for every provider on mobile and desktop --}}
                        @php
                            $statusLabel = $active ? 'Активен' : ($configured ? 'Подключён' : 'Доступы не указаны');
                            $statusColor = $configured ? '#10A37F' : '#A16207';
                        @endphp
                        <div class="mt-4 flex gap-6">
                            <div>
                                <p class="text-[11px] text-text-secondary">Статус</p>
                                <p class="text-xs font-medium" style="color:{{ $statusColor }}">{{ $statusLabel }}</p>
                            </div>
                            @if ($configured)
                                <div>
                                    <p class="text-[11px] text-text-secondary">Модель</p>
                                    <p class="text-xs font-medium text-text-primary">{{ $providerModels[$slug] ?: '—' }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── General settings ────────────────────────────────────────────── --}}
        <div>
            <h2 class="mb-4 text-base font-semibold text-text-primary">Общие настройки</h2>

            <div class="space-y-5 rounded-xl border border-border-light bg-bg-primary p-6">

                {{-- Auto-reply warning — appears right above the toggle after it is clicked --}}
                @if ($showAutoReplyWarning)
                    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-yellow-800">Включить автоответ?</p>
                                <p class="mt-1 text-xs text-yellow-700">В режиме автоответа ИИ будет отправлять сообщения пользователям напрямую без проверки менеджером. Убедитесь, что системный промпт настроен корректно.</p>
                                <div class="mt-3 flex gap-2">
                                    <x-admin.button-primary wire:click="confirmAutoReply" type="button" class="py-1.5 text-xs">
                                        Включить автоответ
                                    </x-admin.button-primary>
                                    <x-admin.button-secondary wire:click="cancelAutoReply" type="button" class="py-1.5 text-xs">
                                        Отмена
                                    </x-admin.button-secondary>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Auto-reply --}}
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-text-primary">Включить автоответы</p>
                        <p class="mt-0.5 text-xs text-text-secondary">ИИ будет автоматически отвечать на первое сообщение пользователя</p>
                    </div>
                    <x-admin.toggle name="auto_reply" id="auto_reply" wire:model.live="auto_reply" />
                </div>

                <div class="h-px bg-border-light"></div>

                {{-- Clarifying questions (grill me) --}}
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-text-primary">Уточняющие вопросы (grill me)</p>
                        <p class="mt-0.5 text-xs text-text-secondary">Если вопрос клиента неоднозначен, ИИ сначала задаёт уточняющие вопросы по одному и только потом отвечает</p>
                    </div>
                    <x-admin.toggle name="clarify_mode" id="clarify_mode" wire:model="clarify_mode" />
                </div>

                <div class="h-px bg-border-light"></div>

                {{-- FAQ knowledge base --}}
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-text-primary">Частые вопросы</p>
                        <p class="mt-0.5 text-xs text-text-secondary">Готовые ответы, которые ИИ использует как источник фактов · активных: {{ $faqEnabledCount }}</p>
                    </div>
                    <a href="{{ route('admin.settings.ai.faq') }}" wire:navigate
                       class="inline-flex shrink-0 items-center justify-center rounded-lg bg-bg-input px-3.5 py-1.5 text-xs font-medium text-text-primary transition hover:bg-border-light">
                        Настроить
                    </a>
                </div>

                <div class="h-px bg-border-light"></div>

                {{-- System prompt --}}
                <div x-data="{ count: $refs.prompt ? $refs.prompt.value.length : {{ mb_strlen($system_prompt) }} }">
                    <div class="mb-2 flex items-center justify-between">
                        <label for="system_prompt" class="text-sm font-semibold text-text-primary">Системный промпт</label>
                        <span class="text-[11px] text-gray-400"><span x-text="count">{{ mb_strlen($system_prompt) }}</span> / 10000 символов</span>
                    </div>
                    <textarea
                        id="system_prompt"
                        x-ref="prompt"
                        wire:model="system_prompt"
                        x-on:input="count = $event.target.value.length"
                        maxlength="10000"
                        rows="16"
                        class="block w-full resize-y rounded-[10px] border border-border-light bg-bg-primary px-3.5 py-3 font-mono text-[13px] leading-relaxed text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                        placeholder="Ты — помощник службы поддержки. Отвечай вежливо и по существу..."
                    ></textarea>
                    <p class="mt-2 text-[11px] text-gray-400">
                        Промпт хранится в базе данных и используется как есть, без подстановки переменных. К нему автоматически добавляются активные частые вопросы и, если включено, инструкция уточняющих вопросов.
                    </p>
                </div>

            </div>
        </div>
        @endif

        {{-- Save (always visible — allows persisting the disabled state too) --}}
        <div class="flex justify-end">
            <x-admin.button-primary type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Сохранить настройки</span>
                <span wire:loading wire:target="save">Сохранение...</span>
            </x-admin.button-primary>
        </div>

        {{-- Success banner --}}
        @if ($saved)
            <div class="mt-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Настройки сохранены.
            </div>
        @endif

    </form>

</div>
