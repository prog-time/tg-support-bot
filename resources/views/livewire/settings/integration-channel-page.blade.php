<div>

    {{-- ── Body ─────────────────────────────────────────────────────────────── --}}
    <div class="p-4 lg:p-8">

        {{-- ── Two-column grid ──────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-4 lg:gap-7 lg:grid-cols-[1fr_320px]">

        {{-- ── Form Card ────────────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-border-light bg-bg-primary p-4 lg:px-8 lg:py-7">

            {{-- Card header: icon + titles --}}
            <div class="flex items-center gap-3.5">
                @if ($channel === 'telegram')
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full" viewBox="0 0 24 24" fill="#229ED9">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                        </svg>
                    </div>
                @elseif ($channel === 'telegram_ai')
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl" style="background:#229ED9">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7"
                             fill="none" viewBox="0 0 24 24" stroke="#ffffff" stroke-width="2">
                            <g stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8V4H8" />
                                <rect width="16" height="12" x="4" y="8" rx="2" />
                                <path d="M2 14h2" />
                                <path d="M20 14h2" />
                                <path d="M15 13v2" />
                                <path d="M9 13v2" />
                            </g>
                        </svg>
                    </div>
                @elseif ($channel === 'vk')
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full" viewBox="0 0 24 24" fill="#0077FF">
                            <path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.391 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.862-.523-2.049-1.713-1.033-1.01-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.575c0 .424-.135.678-1.253.678-1.846 0-3.896-1.118-5.335-3.202C4.624 10.857 4.03 8.57 4.03 8.096c0-.254.102-.491.593-.491h1.744c.44 0 .61.203.779.678.864 2.49 2.303 4.675 2.896 4.675.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.203.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .372.169.508.271.508.22 0 .407-.136.813-.542 1.253-1.406 2.151-3.574 2.151-3.574.119-.254.322-.491.763-.491h1.744c.525 0 .644.27.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.779 1.203 1.253.745.847 1.32 1.558 1.473 2.05.17.491-.085.745-.576.745z" />
                        </svg>
                    </div>
                @else
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full" viewBox="0 0 1000 1000">
                            <defs>
                                <linearGradient id="maxh-b"><stop offset="0" stop-color="#00f"></stop><stop offset="1" stop-opacity="0"></stop></linearGradient>
                                <linearGradient id="maxh-a"><stop offset="0" stop-color="#4cf"></stop><stop offset=".662" stop-color="#53e"></stop><stop offset="1" stop-color="#93d"></stop></linearGradient>
                                <linearGradient id="maxh-c" x1="117.847" x2="1000" y1="760.536" y2="500" gradientUnits="userSpaceOnUse" href="#maxh-a"></linearGradient>
                                <radialGradient id="maxh-d" cx="-87.392" cy="1166.116" r="500" fx="-87.392" fy="1166.116" gradientTransform="rotate(51.356 1551.478 559.3)scale(2.42703433 1)" gradientUnits="userSpaceOnUse" href="#maxh-b"></radialGradient>
                            </defs>
                            <rect width="1000" height="1000" fill="url(#maxh-c)" ry="249.681"></rect>
                            <rect width="1000" height="1000" fill="url(#maxh-d)" ry="249.681"></rect>
                            <path fill="#fff" fill-rule="evenodd" d="M508.211 878.328c-75.007 0-109.864-10.95-170.453-54.75-38.325 49.275-159.686 87.783-164.979 21.9 0-49.456-10.95-91.248-23.36-136.873-14.782-56.21-31.572-118.807-31.572-209.508 0-216.626 177.754-379.597 388.357-379.597 210.785 0 375.947 171.001 375.947 381.604.707 207.346-166.595 376.118-373.94 377.224m3.103-571.585c-102.564-5.292-182.499 65.7-200.201 177.024-14.6 92.162 11.315 204.398 33.397 210.238 10.585 2.555 37.23-18.98 53.837-35.587a189.8 189.8 0 0 0 92.71 33.032c106.273 5.112 197.08-75.794 204.215-181.95 4.154-106.382-77.67-196.486-183.958-202.574Z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                @endif
                <div>
                    <h2 class="text-lg font-bold text-text-primary">
                        @if ($channel === 'telegram') Подключить Telegram
                        @elseif ($channel === 'telegram_ai') Бот AI помощника
                        @elseif ($channel === 'vk') Подключить ВКонтакте
                        @else Подключить MAX
                        @endif
                    </h2>
                    <p class="mt-0.5 text-xs text-text-secondary">
                        @if ($channel === 'telegram') Настройте бота для приёма обращений
                        @elseif ($channel === 'telegram_ai') Отдельный бот ИИ-помощника для черновиков и автоответов
                        @elseif ($channel === 'vk') Настройте сообщество для приёма обращений
                        @else Настройте бота для приёма обращений из MAX
                        @endif
                    </p>
                </div>
            </div>

            <div class="my-6 h-px bg-border-light"></div>

            <form wire:submit="connect" novalidate>
                @csrf

                @if ($channel === 'telegram')

                    <div class="space-y-5">

                        {{-- Токен бота --}}
                        <x-admin.form-field
                            label="Токен бота"
                            for="telegram_token"
                            hint="Токен от @BotFather"
                            :required="true"
                            :error="$formErrors['telegram_token'] ?? null"
                        >
                            <input
                                id="telegram_token"
                                type="password"
                                required
                                wire:model="telegram_token"
                                autocomplete="new-password"
                                placeholder="110201543:AAHdqTcvCH1vGWJxfSeo..."
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['telegram_token'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                        {{-- Секретный ключ Webhook --}}
                        <x-admin.form-field
                            label="Секретный ключ Webhook"
                            for="telegram_secret_key"
                            hint="Для верификации запросов от Telegram"
                            :required="true"
                            :error="$formErrors['telegram_secret_key'] ?? null"
                        >
                            <input
                                id="telegram_secret_key"
                                type="password"
                                required
                                wire:model="telegram_secret_key"
                                autocomplete="new-password"
                                placeholder="your-secret-key"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['telegram_secret_key'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                        {{-- IP-адрес для вебхука --}}
                        <x-admin.form-field
                            label="IP-адрес сервера"
                            for="telegram_webhook_ip_address"
                            hint="Необязательно. Telegram будет стучаться сразу на этот IP вместо резолва домена по DNS при каждом апдейте"
                            :error="$formErrors['telegram_webhook_ip_address'] ?? null"
                        >
                            <input
                                id="telegram_webhook_ip_address"
                                type="text"
                                wire:model="telegram_webhook_ip_address"
                                autocomplete="off"
                                placeholder="149.154.166.110"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['telegram_webhook_ip_address'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                    </div>

                @elseif ($channel === 'telegram_ai')

                    <div class="space-y-5">

                        {{-- Токен AI-бота --}}
                        <x-admin.form-field
                            label="Токен AI-бота"
                            for="telegram_ai_token"
                            hint="Токен отдельного бота для черновиков и автоответов ИИ"
                            :required="true"
                            :error="$formErrors['telegram_ai_token'] ?? null"
                        >
                            <input
                                id="telegram_ai_token"
                                type="password"
                                required
                                wire:model="telegram_ai_token"
                                autocomplete="new-password"
                                placeholder="110201543:AAHdqTcvCH1vGWJxfSeo..."
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['telegram_ai_token'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                        {{-- Секретный ключ Webhook AI-бота --}}
                        <x-admin.form-field
                            label="Секретный ключ Webhook"
                            for="telegram_ai_secret"
                            hint="Для верификации входящих запросов от AI-бота"
                            :required="true"
                            :error="$formErrors['telegram_ai_secret'] ?? null"
                        >
                            <input
                                id="telegram_ai_secret"
                                type="password"
                                required
                                wire:model="telegram_ai_secret"
                                autocomplete="new-password"
                                placeholder="your-secret-key"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20
                                    @if (!empty($formErrors['telegram_ai_secret'])) border-red-400 @endif"
                            />
                        </x-admin.form-field>

                    </div>

                @elseif ($channel === 'vk')

                    <div class="space-y-5">

                        {{-- Токен --}}
                        <x-admin.form-field
                            label="Токен"
                            for="vk_token"
                            hint="Токен доступа сообщества"
                            :required="true"
                            :error="$formErrors['vk_token'] ?? null"
                        >
                            <input
                                id="vk_token"
                                type="password"
                                required
                                wire:model="vk_token"
                                autocomplete="new-password"
                                placeholder="vk1.a.xxxxxxxxxxxx"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Секретный ключ Webhook --}}
                        <x-admin.form-field
                            label="Секретный ключ Webhook"
                            for="vk_secret_key"
                            hint="Для верификации входящих запросов"
                            :required="true"
                            :error="$formErrors['vk_secret_key'] ?? null"
                        >
                            <input
                                id="vk_secret_key"
                                type="password"
                                required
                                wire:model="vk_secret_key"
                                autocomplete="new-password"
                                placeholder="your-secret-key"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Код подтверждения --}}
                        <x-admin.form-field
                            label="Код подтверждения"
                            for="vk_confirm_code"
                            hint="Строка подтверждения сервера из настроек Callback API"
                            :required="true"
                            :error="$formErrors['vk_confirm_code'] ?? null"
                        >
                            <input
                                id="vk_confirm_code"
                                type="password"
                                required
                                wire:model="vk_confirm_code"
                                autocomplete="new-password"
                                placeholder="abc123"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                    </div>

                @else {{-- max --}}

                    <div class="space-y-5">

                        {{-- Токен --}}
                        <x-admin.form-field
                            label="Токен"
                            for="max_token"
                            hint="Токен бота из настроек MAX"
                            :required="true"
                            :error="$formErrors['max_token'] ?? null"
                        >
                            <input
                                id="max_token"
                                type="password"
                                required
                                wire:model="max_token"
                                autocomplete="new-password"
                                placeholder="max-bot-token-xxxxxxxxxxxx"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                        {{-- Секретный ключ Webhook --}}
                        <x-admin.form-field
                            label="Секретный ключ Webhook"
                            for="max_secret_key"
                            hint="Для верификации входящих запросов от MAX"
                            :required="true"
                            :error="$formErrors['max_secret_key'] ?? null"
                        >
                            <input
                                id="max_secret_key"
                                type="password"
                                required
                                wire:model="max_secret_key"
                                autocomplete="new-password"
                                placeholder="your-secret-key"
                                class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                            />
                        </x-admin.form-field>

                    </div>

                @endif

                {{-- Actions row — right-aligned primary action --}}
                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-admin.button-primary type="submit" wire:loading.attr="disabled" wire:target="connect">
                        <span wire:loading.remove wire:target="connect">Сохранить</span>
                        <span wire:loading wire:target="connect">Проверка...</span>
                    </x-admin.button-primary>
                </div>

                {{-- Webhook result notice --}}
                @if ($webhookMessage)
                    <div class="mt-4 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm
                        @if ($webhookSuccess) border-green-200 bg-green-50 text-green-800
                        @else border-red-200 bg-red-50 text-red-800 @endif">
                        @if ($webhookSuccess)
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
                        {{ $webhookMessage }}
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
                        $steps = match ($channel) {
                            'telegram' => [
                                'Создайте группу в Telegram',
                                'Создайте бота в Telegram через @botFather',
                                'Добавьте бота как администратора в группу',
                                'Включите "Темы" в настройках группы',
                            ],
                            'telegram_ai' => [
                                'Создайте второй бот через @BotFather (это AI-бот)',
                                'Добавьте AI-бота как администратора в ту же супергруппу',
                                'Сохраните токен и секретный ключ на этой странице',
                                'Зарегистрируйте вебхук командой: docker exec -it pet php artisan ai-bot:set-webhook',
                            ],
                            'vk' => [
                                'Создайте сообщество VK или откройте существующее',
                                'Перейдите в настройки сообщества Управление → Дополнительно → Работа с API',
                                'Нажмите "Создать ключ"',
                                'Во вкладке "Callback API" укажите адрес "'.rtrim(config('app.url'), '/').'/vk/bot"',
                                'Укажите "Секретный ключ"',
                                'Нажмите "Подтвердить"',
                            ],
                            default => [
                                'Создайте бота в платформе MAX',
                                'Скопируйте токен из настроек бота',
                                'Укажите URL вебхука в настройках',
                            ],
                        };

                        $docsUrl = match ($channel) {
                            'telegram' => 'https://docs.tg-support-bot.ru/docs/telegram-bot.html',
                            'telegram_ai' => 'https://docs.tg-support-bot.ru/docs/ai-bot.html',
                            'vk' => 'https://docs.tg-support-bot.ru/docs/vk-group.html',
                            'max' => 'https://docs.tg-support-bot.ru/docs/max-bot.html',
                            default => 'https://docs.tg-support-bot.ru/',
                        };
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
