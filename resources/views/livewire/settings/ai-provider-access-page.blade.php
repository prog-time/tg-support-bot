<div>

    {{-- ── Body ─────────────────────────────────────────────────────────────────── --}}
    <div class="p-4 lg:p-8">

        {{-- ── Two-column grid ──────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-4 lg:gap-7 lg:grid-cols-[1fr_320px]">

            {{-- ── Form Card ────────────────────────────────────────────────────────── --}}
            <div class="rounded-2xl border border-border-light bg-bg-primary p-4 lg:px-8 lg:py-7">

            {{-- Card header --}}
            <div class="flex items-center gap-3.5">
                @if ($provider === 'openai')
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" style="background:#E8F5E9">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#2E7D32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                @elseif ($provider === 'deepseek')
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" style="background:#E3F2FD">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#1565C0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                @else
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" style="background:#FCE4EC">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color:#C62828" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </div>
                @endif
                <div>
                    <h2 class="text-lg font-bold text-text-primary">
                        @if ($provider === 'openai') Доступ OpenAI
                        @elseif ($provider === 'deepseek') Доступ DeepSeek
                        @else Доступ GigaChat
                        @endif
                    </h2>
                    <p class="mt-0.5 text-xs text-text-secondary">
                        @if ($provider === 'openai') API-ключ и параметры подключения к OpenAI
                        @elseif ($provider === 'deepseek') Учётные данные и параметры DeepSeek API
                        @else Учётные данные и параметры GigaChat API
                        @endif
                    </p>
                </div>
            </div>

            <div class="my-6 h-px bg-border-light"></div>

            <form wire:submit="connect" novalidate>
                @csrf

                @if ($provider === 'openai')

                    <div class="space-y-5">

                        {{-- API Key --}}
                        <x-admin.form-field
                            label="API Key"
                            for="openai_api_key"
                            hint="Оставьте пустым, чтобы не изменять сохранённый ключ"
                            :error="$formErrors['openai_api_key'] ?? null"
                        >
                            <input
                                id="openai_api_key"
                                type="password"
                                wire:model="openai_api_key"
                                autocomplete="new-password"
                                placeholder="sk-..."
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Base URL --}}
                        <x-admin.form-field
                            label="Base URL"
                            for="openai_base_url"
                            hint="Оставьте пустым для использования стандартного эндпоинта OpenAI"
                            :error="$formErrors['openai_base_url'] ?? null"
                        >
                            <input
                                id="openai_base_url"
                                type="text"
                                wire:model="openai_base_url"
                                placeholder="https://api.openai.com/v1"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Model --}}
                        <x-admin.form-field
                            label="Модель"
                            for="openai_model"
                            hint="Например: gpt-4o, gpt-4-turbo"
                            :error="$formErrors['openai_model'] ?? null"
                        >
                            <input
                                id="openai_model"
                                type="text"
                                wire:model="openai_model"
                                placeholder="gpt-4o"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Max tokens --}}
                        <x-admin.form-field
                            label="Макс. токенов ответа"
                            for="openai_max_tokens"
                            hint="Максимальное количество токенов в ответе модели"
                            :error="$formErrors['openai_max_tokens'] ?? null"
                        >
                            <input
                                id="openai_max_tokens"
                                type="number"
                                min="1"
                                wire:model="openai_max_tokens"
                                placeholder="1000"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['openai_max_tokens'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                        {{-- Temperature --}}
                        <x-admin.form-field
                            label="Температура"
                            for="openai_temperature"
                            hint="Диапазон 0.0–2.0. Меньше — более предсказуемые ответы."
                            :error="$formErrors['openai_temperature'] ?? null"
                        >
                            <input
                                id="openai_temperature"
                                type="text"
                                wire:model="openai_temperature"
                                placeholder="0.7"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                    </div>

                @elseif ($provider === 'deepseek')

                    <div class="space-y-5">

                        {{-- Client ID --}}
                        <x-admin.form-field
                            label="Client ID"
                            for="deepseek_client_id"
                            :error="$formErrors['deepseek_client_id'] ?? null"
                        >
                            <input
                                id="deepseek_client_id"
                                type="text"
                                wire:model="deepseek_client_id"
                                placeholder="client-id"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Client Secret --}}
                        <x-admin.form-field
                            label="Client Secret"
                            for="deepseek_client_secret"
                            hint="Оставьте пустым, чтобы не изменять сохранённый секрет"
                            :error="$formErrors['deepseek_client_secret'] ?? null"
                        >
                            <input
                                id="deepseek_client_secret"
                                type="password"
                                wire:model="deepseek_client_secret"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Base URL --}}
                        <x-admin.form-field
                            label="Base URL"
                            for="deepseek_base_url"
                            hint="Оставьте пустым для использования стандартного эндпоинта DeepSeek"
                            :error="$formErrors['deepseek_base_url'] ?? null"
                        >
                            <input
                                id="deepseek_base_url"
                                type="text"
                                wire:model="deepseek_base_url"
                                placeholder="https://api.deepseek.com"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Model --}}
                        <x-admin.form-field
                            label="Модель"
                            for="deepseek_model"
                            hint="Например: deepseek-chat, deepseek-reasoner"
                            :error="$formErrors['deepseek_model'] ?? null"
                        >
                            <input
                                id="deepseek_model"
                                type="text"
                                wire:model="deepseek_model"
                                placeholder="deepseek-chat"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Max tokens --}}
                        <x-admin.form-field
                            label="Макс. токенов ответа"
                            for="deepseek_max_tokens"
                            hint="Максимальное количество токенов в ответе модели"
                            :error="$formErrors['deepseek_max_tokens'] ?? null"
                        >
                            <input
                                id="deepseek_max_tokens"
                                type="number"
                                min="1"
                                wire:model="deepseek_max_tokens"
                                placeholder="1000"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['deepseek_max_tokens'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                        {{-- Temperature --}}
                        <x-admin.form-field
                            label="Температура"
                            for="deepseek_temperature"
                            hint="Диапазон 0.0–1.0."
                            :error="$formErrors['deepseek_temperature'] ?? null"
                        >
                            <input
                                id="deepseek_temperature"
                                type="text"
                                wire:model="deepseek_temperature"
                                placeholder="0.7"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                    </div>

                @else {{-- gigachat --}}

                    <div class="space-y-5">

                        {{-- Client ID --}}
                        <x-admin.form-field
                            label="Client ID"
                            for="gigachat_client_id"
                            :error="$formErrors['gigachat_client_id'] ?? null"
                        >
                            <input
                                id="gigachat_client_id"
                                type="text"
                                wire:model="gigachat_client_id"
                                placeholder="client-id"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Client Secret --}}
                        <x-admin.form-field
                            label="Client Secret"
                            for="gigachat_client_secret"
                            hint="Оставьте пустым, чтобы не изменять сохранённый секрет"
                            :error="$formErrors['gigachat_client_secret'] ?? null"
                        >
                            <input
                                id="gigachat_client_secret"
                                type="password"
                                wire:model="gigachat_client_secret"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Scope --}}
                        <x-admin.form-field
                            label="Scope (тип проекта)"
                            for="gigachat_scope"
                            hint="PERS — для физлиц, B2B / CORP — для юрлиц. Несоответствие даёт ошибку HTTP 400."
                            :error="$formErrors['gigachat_scope'] ?? null"
                        >
                            <select
                                id="gigachat_scope"
                                wire:model="gigachat_scope"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            >
                                <option value="GIGACHAT_API_PERS">GIGACHAT_API_PERS (физлица)</option>
                                <option value="GIGACHAT_API_B2B">GIGACHAT_API_B2B (юрлица)</option>
                                <option value="GIGACHAT_API_CORP">GIGACHAT_API_CORP (корпоративный)</option>
                            </select>
                        </x-admin.form-field>

                        {{-- Base URL --}}
                        <x-admin.form-field
                            label="Base URL"
                            for="gigachat_base_url"
                            hint="Оставьте пустым для использования стандартного эндпоинта GigaChat"
                            :error="$formErrors['gigachat_base_url'] ?? null"
                        >
                            <input
                                id="gigachat_base_url"
                                type="text"
                                wire:model="gigachat_base_url"
                                placeholder="https://gigachat.devices.sberbank.ru/api/v1"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Model --}}
                        <x-admin.form-field
                            label="Модель"
                            for="gigachat_model"
                            hint="Например: GigaChat, GigaChat-Pro, GigaChat-Max"
                            :error="$formErrors['gigachat_model'] ?? null"
                        >
                            <input
                                id="gigachat_model"
                                type="text"
                                wire:model="gigachat_model"
                                placeholder="GigaChat"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Max tokens --}}
                        <x-admin.form-field
                            label="Макс. токенов ответа"
                            for="gigachat_max_tokens"
                            hint="Максимальное количество токенов в ответе модели"
                            :error="$formErrors['gigachat_max_tokens'] ?? null"
                        >
                            <input
                                id="gigachat_max_tokens"
                                type="number"
                                min="1"
                                wire:model="gigachat_max_tokens"
                                placeholder="1000"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['gigachat_max_tokens'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                        {{-- Temperature --}}
                        <x-admin.form-field
                            label="Температура"
                            for="gigachat_temperature"
                            hint="Диапазон 0.0–1.0."
                            :error="$formErrors['gigachat_temperature'] ?? null"
                        >
                            <input
                                id="gigachat_temperature"
                                type="text"
                                wire:model="gigachat_temperature"
                                placeholder="0.7"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Certificate file upload --}}
                        <x-admin.form-field
                            label="Сертификат (CA)"
                            for="gigachat_cert_file"
                            hint="Загрузите CA-сертификат (.crt / .pem). Файл сохраняется как storage/certs/russian_trusted_root_ca_pem.crt"
                            :error="$formErrors['gigachat_cert_file'] ?? null"
                        >
                            <input
                                id="gigachat_cert_file"
                                type="file"
                                wire:model="gigachat_cert_file"
                                accept=".crt,.pem,.cer"
                                class="block w-full cursor-pointer rounded-lg border border-border-light bg-bg-input text-sm text-text-secondary outline-none file:mr-3 file:cursor-pointer file:border-0 file:bg-accent file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-white hover:file:opacity-90"
                            />
                            <div wire:loading wire:target="gigachat_cert_file" class="mt-1.5 text-xs text-text-secondary">Загрузка файла…</div>
                            @if ($gigachat_cert_uploaded)
                                <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-green-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Сертификат уже загружен. Загрузите новый файл, чтобы заменить его.
                                </p>
                            @endif
                        </x-admin.form-field>

                    </div>

                @endif

                {{-- Actions row --}}
                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-admin.button-primary type="submit" wire:loading.attr="disabled" wire:target="connect">
                        <span wire:loading.remove wire:target="connect">Сохранить</span>
                        <span wire:loading wire:target="connect">Проверка подключения...</span>
                    </x-admin.button-primary>
                </div>

                {{-- Verification error notice --}}
                @if ($verifyError)
                    <div class="mt-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-red-500"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ $verifyError }}
                    </div>
                @endif

                {{-- Success notice --}}
                @if ($saved)
                    <div class="mt-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-green-500"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Доступы проверены и сохранены.
                    </div>
                @endif

            </form>
        </div>

        {{-- ── Info panel ────────────────────────────────────────────────────────── --}}
        <div>
            <div class="rounded-xl border border-border-light bg-bg-primary p-4 lg:p-6">

                {{-- Panel header --}}
                <div class="mb-5 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-semibold text-text-primary">Безопасность</span>
                </div>

                <ul class="space-y-3">
                    <li class="flex items-start gap-3">
                        <span class="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full text-[11px] font-semibold text-accent"
                              style="background:#EEF2FF">1</span>
                        <span class="text-[13px] leading-relaxed text-text-secondary">
                            Секретные ключи хранятся в зашифрованном виде в базе данных.
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full text-[11px] font-semibold text-accent"
                              style="background:#EEF2FF">2</span>
                        <span class="text-[13px] leading-relaxed text-text-secondary">
                            Поля секретов не предзаполняются — оставьте поле пустым, чтобы сохранить текущий ключ.
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full text-[11px] font-semibold text-accent"
                              style="background:#EEF2FF">3</span>
                        <span class="text-[13px] leading-relaxed text-text-secondary">
                            Ключи никогда не выводятся в открытом виде и не логируются.
                        </span>
                    </li>
                </ul>

                @php
                    $docsUrl = match ($provider) {
                        'openai' => 'https://docs.tg-support-bot.ru/docs/ai-openai.html',
                        'deepseek' => 'https://docs.tg-support-bot.ru/docs/ai-deepseek.html',
                        'gigachat' => 'https://docs.tg-support-bot.ru/docs/ai-gigachat.html',
                        default => 'https://docs.tg-support-bot.ru/',
                    };
                @endphp
                <a href="{{ $docsUrl }}"
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
