{{-- Раздел «Интеграции»: каналы сгруппированы — мессенджеры и (виджет + ИИ-ассистент) --}}
<div class="p-4 lg:p-8 lg:max-w-3xl">

    {{-- ── Page header --}}
    <div class="mb-5">
        <h1 class="text-lg font-semibold text-text-primary lg:text-2xl lg:font-bold">Интеграции</h1>
        <p class="mt-0.5 text-sm text-text-secondary">Управление каналами поддержки</p>
    </div>

    {{-- ── Channel cards — grouped: messengers and AI assistant ──────────── --}}
    <div class="space-y-6">

        {{-- ══ Группа 1: Мессенджеры ══════════════════════════════════════════ --}}
        <div>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-text-secondary">Мессенджеры</h2>
            <div class="space-y-3">

                {{-- Telegram --}}
                <a href="{{ route('admin.settings.integrations.channel', ['channel' => 'telegram']) }}"
                   class="block rounded-xl border border-border-light bg-bg-primary p-4 transition hover:border-accent hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            {{-- Icon: full-bleed Telegram logo --}}
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full" viewBox="0 0 24 24" fill="#229ED9">
                                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text-primary">Telegram</p>
                                @if ($channelStatuses['telegram']['connected'])
                                    <span class="inline-flex items-center gap-1 text-xs font-medium" style="color:#34C759">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full" style="background:#34C759"></span>
                                        Подключено
                                    </span>
                                @else
                                    <span class="text-xs text-text-secondary">Не подключён</span>
                                @endif
                            </div>
                        </div>
                        @if (! $channelStatuses['telegram']['connected'])
                            <span class="inline-flex items-center justify-center rounded-lg bg-accent px-3.5 py-1.5 text-xs font-medium text-white">
                                Подключить
                            </span>
                        @endif
                    </div>
                    <p class="mt-3 text-[13px] leading-relaxed text-text-secondary">
                        Интеграция с Telegram — приём обращений из бота.
                    </p>
                </a>

                {{-- MAX --}}
                <a href="{{ route('admin.settings.integrations.channel', ['channel' => 'max']) }}"
                   class="block rounded-xl border border-border-light bg-bg-primary p-4 transition hover:border-accent hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full" viewBox="0 0 1000 1000">
                                    <defs>
                                        <linearGradient id="max-b"><stop offset="0" stop-color="#00f"></stop><stop offset="1" stop-opacity="0"></stop></linearGradient>
                                        <linearGradient id="max-a"><stop offset="0" stop-color="#4cf"></stop><stop offset=".662" stop-color="#53e"></stop><stop offset="1" stop-color="#93d"></stop></linearGradient>
                                        <linearGradient id="max-c" x1="117.847" x2="1000" y1="760.536" y2="500" gradientUnits="userSpaceOnUse" href="#max-a"></linearGradient>
                                        <radialGradient id="max-d" cx="-87.392" cy="1166.116" r="500" fx="-87.392" fy="1166.116" gradientTransform="rotate(51.356 1551.478 559.3)scale(2.42703433 1)" gradientUnits="userSpaceOnUse" href="#max-b"></radialGradient>
                                    </defs>
                                    <rect width="1000" height="1000" fill="url(#max-c)" ry="249.681"></rect>
                                    <rect width="1000" height="1000" fill="url(#max-d)" ry="249.681"></rect>
                                    <path fill="#fff" fill-rule="evenodd" d="M508.211 878.328c-75.007 0-109.864-10.95-170.453-54.75-38.325 49.275-159.686 87.783-164.979 21.9 0-49.456-10.95-91.248-23.36-136.873-14.782-56.21-31.572-118.807-31.572-209.508 0-216.626 177.754-379.597 388.357-379.597 210.785 0 375.947 171.001 375.947 381.604.707 207.346-166.595 376.118-373.94 377.224m3.103-571.585c-102.564-5.292-182.499 65.7-200.201 177.024-14.6 92.162 11.315 204.398 33.397 210.238 10.585 2.555 37.23-18.98 53.837-35.587a189.8 189.8 0 0 0 92.71 33.032c106.273 5.112 197.08-75.794 204.215-181.95 4.154-106.382-77.67-196.486-183.958-202.574Z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text-primary">Max</p>
                                @if ($channelStatuses['max']['connected'])
                                    <span class="inline-flex items-center gap-1 text-xs font-medium" style="color:#34C759">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full" style="background:#34C759"></span>
                                        Подключено
                                    </span>
                                @else
                                    <span class="text-xs text-text-secondary">Не подключён</span>
                                @endif
                            </div>
                        </div>
                        @if (! $channelStatuses['max']['connected'])
                            <span class="inline-flex items-center justify-center rounded-lg bg-accent px-3.5 py-1.5 text-xs font-medium text-white">
                                Подключить
                            </span>
                        @endif
                    </div>
                    <p class="mt-3 text-[13px] leading-relaxed text-text-secondary">
                        Интеграция с Max — обращения из мессенджера Max.
                    </p>
                </a>

                {{-- VK --}}
                <a href="{{ route('admin.settings.integrations.channel', ['channel' => 'vk']) }}"
                   class="block rounded-xl border border-border-light bg-bg-primary p-4 transition hover:border-accent hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full" viewBox="0 0 24 24" fill="#0077FF">
                                    <path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.391 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.862-.523-2.049-1.713-1.033-1.01-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.575c0 .424-.135.678-1.253.678-1.846 0-3.896-1.118-5.335-3.202C4.624 10.857 4.03 8.57 4.03 8.096c0-.254.102-.491.593-.491h1.744c.44 0 .61.203.779.678.864 2.49 2.303 4.675 2.896 4.675.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.203.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .372.169.508.271.508.22 0 .407-.136.813-.542 1.253-1.406 2.151-3.574 2.151-3.574.119-.254.322-.491.763-.491h1.744c.525 0 .644.27.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.779 1.203 1.253.745.847 1.32 1.558 1.473 2.05.17.491-.085.745-.576.745z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-text-primary">ВКонтакте</p>
                                @if ($channelStatuses['vk']['connected'])
                                    <span class="inline-flex items-center gap-1 text-xs font-medium" style="color:#34C759">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full" style="background:#34C759"></span>
                                        Подключено
                                    </span>
                                @else
                                    <span class="text-xs text-text-secondary">Не подключён</span>
                                @endif
                            </div>
                        </div>
                        @if (! $channelStatuses['vk']['connected'])
                            <span class="inline-flex items-center justify-center rounded-lg bg-accent px-3.5 py-1.5 text-xs font-medium text-white">
                                Подключить
                            </span>
                        @endif
                    </div>
                    <p class="mt-3 text-[13px] leading-relaxed text-text-secondary">
                        Интеграция с ВКонтакте — сообщения из чата сообщества.
                    </p>
                </a>

                {{-- Avito — только когда установлен платный модуль (Route::has) --}}
                @if ($avitoInstalled)
                <a href="{{ route('admin.settings.avito') }}"
                   class="block rounded-xl border border-border-light bg-bg-primary p-4 transition hover:border-accent hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            {{-- Icon: full-bleed Avito logo --}}
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-white">
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
                                <p class="text-sm font-semibold text-text-primary">Avito</p>
                                @if ($avitoConnected)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium" style="color:#34C759">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full" style="background:#34C759"></span>
                                        Подключено
                                    </span>
                                @else
                                    <span class="text-xs text-text-secondary">Не подключён</span>
                                @endif
                            </div>
                        </div>
                        @if (! $avitoConnected)
                            <span class="inline-flex items-center justify-center rounded-lg bg-accent px-3.5 py-1.5 text-xs font-medium text-white">
                                Подключить
                            </span>
                        @endif
                    </div>
                    <p class="mt-3 text-[13px] leading-relaxed text-text-secondary">
                        Интеграция с Avito — приём сообщений из объявлений по подписке.
                    </p>
                </a>
                @endif

            </div>
        </div>

        {{-- ══ Группа 2: ИИ-ассистент ════════════════════════════════════════ --}}
        <div>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-text-secondary">ИИ-ассистент</h2>
            <div class="space-y-3">

                {{-- Telegram AI bot --}}
                <a href="{{ route('admin.settings.integrations.channel', ['channel' => 'telegram_ai']) }}"
                   class="block rounded-xl border border-border-light bg-bg-primary p-4 transition hover:border-accent hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            {{-- Icon: full-bleed Telegram-blue tile with white AI spark --}}
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl" style="background:#229ED9">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
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
                            <div>
                                <p class="text-sm font-semibold text-text-primary">Бот AI помощника</p>
                                @if ($channelStatuses['telegram_ai']['connected'])
                                    <span class="inline-flex items-center gap-1 text-xs font-medium" style="color:#34C759">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full" style="background:#34C759"></span>
                                        Подключено
                                    </span>
                                @else
                                    <span class="text-xs text-text-secondary">Не подключён</span>
                                @endif
                            </div>
                        </div>
                        @if (! $channelStatuses['telegram_ai']['connected'])
                            <span class="inline-flex items-center justify-center rounded-lg bg-accent px-3.5 py-1.5 text-xs font-medium text-white">
                                Подключить
                            </span>
                        @endif
                    </div>
                    <p class="mt-3 text-[13px] leading-relaxed text-text-secondary">
                        Интеграция с ботом AI-помощника — отдельный бот публикует черновики и ответы ИИ в супергруппе.
                    </p>
                </a>

            </div>
        </div>

    </div>

</div>
