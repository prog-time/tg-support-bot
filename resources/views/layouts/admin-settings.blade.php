<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" sizes="any" />

    {{-- PWA: installable admin app --}}
    <link rel="manifest" href="{{ route('admin.pwa.manifest') }}" />
    <meta name="theme-color" content="#1B1F2E" />
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="TG Support" />

    <title>{{ $title ?? 'Настройки' }} — Admin</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak]{display:none !important;}</style>
</head>
<body class="h-full bg-bg-secondary font-sans text-text-primary antialiased">

{{-- Alpine scope wrapper (display:contents — layout-neutral). x-data on a real
     element initialises reliably under Livewire, unlike x-data on <body>. --}}
<div x-data="{ navOpen: false }" x-on:keydown.escape.window="navOpen = false" class="contents">

    {{-- Mobile: top bar with back + section title (hidden on lg+) --}}
    @php
        // The mobile back button steps one level up and shows the destination name:
        //  • a section sub-page → its list page;
        //  • a top-level settings page → the chat workspace («Чаты»).
        $chatsUrl = Route::has('admin.chats') ? route('admin.chats') : '/admin/chats';
        [$mobileBackUrl, $mobileBackLabel] = match (true) {
            request()->routeIs('admin.settings.integrations.channel') => [route('admin.settings.integrations'), 'Интеграции'],
            request()->routeIs('admin.settings.avito') => [route('admin.settings.integrations'), 'Интеграции'],
            request()->routeIs('admin.settings.ai.provider') => [route('admin.settings.ai'), 'ИИ-ассистент'],
            request()->routeIs('admin.settings.api-webhooks.source') => [route('admin.settings.api-webhooks'), 'API и вебхуки'],
            request()->routeIs('admin.settings.auto-replies.create', 'admin.settings.auto-replies.edit') => [route('admin.settings.auto-replies'), 'Автоответы'],
            request()->routeIs('admin.settings.team.create', 'admin.settings.team.edit') => [route('admin.settings.team'), 'Команда'],
            default => [$chatsUrl, 'Чаты'],
        };
    @endphp
    <header class="flex items-center gap-3 border-b border-border-light bg-sidebar px-4 py-4 lg:hidden">
        <a href="{{ $mobileBackUrl }}"
           class="-ml-1 flex items-center gap-1.5 rounded-lg px-1 py-1 text-sm font-medium text-text-sidebar-secondary transition hover:text-text-sidebar"
           aria-label="Назад: {{ $mobileBackLabel }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5m7-7-7 7 7 7" />
            </svg>
            <span>{{ $mobileBackLabel }}</span>
        </a>

        {{-- Hamburger — opens the settings nav drawer --}}
        <button
            type="button"
            x-on:click="navOpen = true"
            class="ml-auto flex h-8 w-8 items-center justify-center rounded-lg text-text-sidebar-secondary transition hover:bg-sidebar-hover hover:text-text-sidebar"
            aria-label="Меню настроек"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>
            </svg>
        </button>
    </header>

    <div class="flex h-full min-h-screen lg:h-screen lg:overflow-hidden">

        {{-- Backdrop — mobile only, dismisses the drawer --}}
        <div
            x-show="navOpen"
            x-cloak
            x-transition.opacity
            x-on:click="navOpen = false"
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Settings nav — full-screen slide-in drawer on mobile, static 280px column on lg+ --}}
        <div
            class="fixed inset-y-0 left-0 z-50 flex w-full -translate-x-full transition-transform duration-200 ease-out
                   lg:static lg:z-auto lg:w-auto lg:translate-x-0 lg:shrink-0"
            :style="navOpen ? 'translate: 0' : ''"
        >
            <x-admin.sidebar>
                <x-admin.nav-item
                    href="{{ route('admin.settings.general') }}"
                    :active="request()->routeIs('admin.settings.general')"
                >
                    <x-slot name="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </x-slot>
                    Основные
                </x-admin.nav-item>

                {{-- Settings below «Основные» are admin-only; managers see notifications only. --}}
                @if (auth()->user()?->isAdmin())
                <x-admin.nav-item
                    href="{{ route('admin.settings.integrations') }}"
                    :active="request()->routeIs('admin.settings.integrations') || request()->routeIs('admin.settings.integrations.channel')"
                >
                    <x-slot name="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </x-slot>
                    Интеграции
                </x-admin.nav-item>

                <x-admin.nav-item
                    href="{{ route('admin.settings.ai') }}"
                    :active="request()->routeIs('admin.settings.ai') || request()->routeIs('admin.settings.ai.provider')"
                >
                    <x-slot name="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2" />
                        </svg>
                    </x-slot>
                    ИИ-ассистент
                </x-admin.nav-item>

                <x-admin.nav-item
                    href="{{ route('admin.settings.api-webhooks') }}"
                    :active="request()->routeIs('admin.settings.api-webhooks')"
                >
                    <x-slot name="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </x-slot>
                    API и вебхуки
                </x-admin.nav-item>

                <x-admin.nav-item
                    href="{{ route('admin.settings.team') }}"
                    :active="request()->routeIs('admin.settings.team')"
                >
                    <x-slot name="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </x-slot>
                    Команда
                </x-admin.nav-item>

                <x-admin.nav-item
                    href="{{ route('admin.settings.auto-replies') }}"
                    :active="request()->routeIs('admin.settings.auto-replies')"
                >
                    <x-slot name="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </x-slot>
                    Автоответы
                </x-admin.nav-item>
                @endif

            </x-admin.sidebar>

            {{-- Mobile close — placed after the sidebar so it paints above the full-width panel --}}
            <button
                type="button"
                x-on:click="navOpen = false"
                class="absolute right-4 top-5 z-10 flex h-8 w-8 items-center justify-center rounded-lg text-text-sidebar-secondary transition hover:bg-sidebar-hover hover:text-text-sidebar lg:hidden"
                aria-label="Закрыть меню"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Main content --}}
        <main class="flex-1 overflow-y-auto bg-bg-secondary">
            {{ $slot }}
        </main>

    </div>

</div>{{-- /Alpine scope wrapper --}}

    @include('partials.notification-sounds')

    @livewireScripts
</body>
</html>
