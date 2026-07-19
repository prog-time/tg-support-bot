{{--
    View for App\Livewire\Admin\ThanksPage — «Поблагодарить автора».

    Links live in the arrays below — the single place to edit them. A card whose
    `url` is empty is skipped by @continue, so an unconfigured link can never
    ship as a dead button.

    Styling uses only the admin design-system tokens declared in
    resources/css/app.css @theme (accent, text-*, bg-*, border-light).
--}}
@php
    $primary = [
        'url'   => 'https://godonate.ru/s/prog-time',
        'title' => 'Поддержать рублём',
        'text'  => 'Разработка идёт в свободное время. Любая сумма помогает проекту жить и развиваться дальше.',
        'cta'   => 'Поддержать',
    ];

    $secondary = [
        [
            'url'   => 'https://github.com/prog-time/tg-support-bot',
            'title' => 'Поставить звезду на GitHub',
            'text'  => 'Звёзды — главный сигнал, по которому проект находят другие разработчики.',
            'cta'   => 'Открыть репозиторий',
            'icon'  => 'star',
        ],
        [
            'url'   => 'https://github.com/prog-time',
            'title' => 'Подписаться на автора',
            'text'  => 'Следите за другими проектами автора и обновлениями этого.',
            'cta'   => 'Открыть профиль',
            'icon'  => 'user',
        ],
        [
            'url'   => 'https://t.me/pt_tg_support',
            'title' => 'Telegram-группа',
            'text'  => 'Анонсы релизов, обсуждение задач и помощь с настройкой.',
            'cta'   => 'Перейти в группу',
            'icon'  => 'send',
        ],
    ];

    $icons = [
        'star'  => 'M12 2l2.6 6.6L21 9.3l-5 4.4 1.5 6.8L12 17l-5.5 3.5L8 13.7l-5-4.4 6.4-.7z',
        'send'  => 'M22 2L11 13M22 2l-7 20-4-9-9-4z',
        'user'  => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
        'heart' => 'M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z',
    ];

    $externalIcon = 'M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3';
@endphp

<div class="p-4 lg:p-8">

    {{-- ── Hero ─────────────────────────────────────────────────────────────────── --}}
    <div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-[#4F6EF7] via-[#5A63F0] to-[#7C5CE6] px-6 py-8 lg:px-10 lg:py-12">

        {{-- Decorative rings — purely ornamental, hidden from assistive tech --}}
        <div aria-hidden="true"
             class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full border border-white/15"></div>
        <div aria-hidden="true"
             class="pointer-events-none absolute -bottom-28 -right-4 h-56 w-56 rounded-full bg-white/5"></div>

        <div class="relative max-w-2xl">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l2.6 6.6L21 9.3l-5 4.4 1.5 6.8L12 17l-5.5 3.5L8 13.7l-5-4.4 6.4-.7z" />
                </svg>
                Открытый исходный код
            </span>

            <h1 class="mt-4 text-2xl font-bold text-white lg:text-3xl">
                Спасибо, что пользуетесь TG&nbsp;Support&nbsp;Bot
            </h1>

            <p class="mt-3 text-sm leading-relaxed text-white/85 lg:text-base">
                Проект развивается в свободное время и остаётся бесплатным. Если он сэкономил вам
                время — вот несколько способов сказать спасибо. Любой из них занимает меньше минуты.
            </p>
        </div>
    </div>

    {{-- Kept for navigation/accessibility parity with the sidebar item --}}
    <h2 class="sr-only">Поблагодарить автора</h2>

    {{-- ── Primary call to action ───────────────────────────────────────────────── --}}
    <a href="{{ $primary['url'] }}"
       target="_blank"
       rel="noopener noreferrer"
       class="group mb-4 flex flex-col gap-4 rounded-xl border border-accent/30 bg-accent/[0.04] p-6 transition duration-200 hover:-translate-y-0.5 hover:border-accent/60 hover:shadow-lg hover:shadow-accent/10 sm:flex-row sm:items-center">

        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-accent text-white transition-transform duration-200 group-hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="{{ $icons['heart'] }}" />
            </svg>
        </span>

        <span class="min-w-0 flex-1">
            <span class="block text-base font-semibold text-text-primary">{{ $primary['title'] }}</span>
            <span class="mt-1 block text-sm text-text-secondary">{{ $primary['text'] }}</span>
        </span>

        <span class="inline-flex shrink-0 items-center justify-center rounded-[10px] bg-accent px-5 py-2.5 text-sm font-medium text-white transition group-hover:bg-blue-600">
            {{ $primary['cta'] }}
            <svg xmlns="http://www.w3.org/2000/svg" class="ml-1.5 h-3.5 w-3.5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="{{ $externalIcon }}" />
            </svg>
        </span>
    </a>

    {{-- ── Secondary ways to help ───────────────────────────────────────────────── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($secondary as $link)
            @continue ($link['url'] === '')

            <a href="{{ $link['url'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="group flex flex-col rounded-xl border border-border-light bg-bg-primary p-6 transition duration-200 hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-md">

                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-bg-secondary text-accent transition-colors duration-200 group-hover:bg-accent group-hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $icons[$link['icon']] }}" />
                    </svg>
                </span>

                <span class="mt-4 block text-sm font-semibold text-text-primary">{{ $link['title'] }}</span>
                <span class="mt-1 block flex-1 text-sm text-text-secondary">{{ $link['text'] }}</span>

                <span class="mt-4 inline-flex items-center text-sm font-medium text-accent">
                    {{ $link['cta'] }}
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="ml-1.5 h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $externalIcon }}" />
                    </svg>
                </span>
            </a>
        @endforeach
    </div>

    {{-- ── Footer note ──────────────────────────────────────────────────────────── --}}
    <div class="mt-6 rounded-xl border border-dashed border-border-light px-6 py-5">
        <p class="text-sm text-text-secondary">
            Нашли баг или есть идея?
            <a href="https://github.com/prog-time/tg-support-bot/issues"
               target="_blank"
               rel="noopener noreferrer"
               class="font-medium text-accent hover:underline">Заведите issue</a> —
            это помогает проекту не меньше звезды.
        </p>
    </div>

</div>
