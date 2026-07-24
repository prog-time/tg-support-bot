{{--
    Admin sidebar layout component.

    Props:
      $title       — sidebar heading text (default "Настройки")
      $backUrl     — URL for the back chevron (default route('admin.chats') or '/admin')
      $version     — version string shown in footer (default "v9.0.0")
      $docsUrl     — URL for "Документация" footer link (default "https://docs.tg-support-bot.ru/")
--}}
@props([
    'title'   => 'Настройки',
    'backUrl' => null,
    'version' => 'v9.0.0',
    'docsUrl' => 'https://docs.tg-support-bot.ru/',
])

@php
    $resolvedBackUrl = $backUrl ?? (Route::has('admin.chats')
        ? route('admin.chats')
        : '/admin');
@endphp

<aside class="flex h-full w-full shrink-0 flex-col bg-sidebar text-text-sidebar lg:w-70">
    {{-- Header --}}
    <div class="flex items-center gap-3 px-4 py-5">
        <a href="{{ $resolvedBackUrl }}"
           class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-text-sidebar-secondary transition hover:bg-sidebar-hover hover:text-text-sidebar"
           aria-label="Назад">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <span class="text-base font-semibold tracking-tight">{{ $title }}</span>
    </div>

    {{-- Nav items --}}
    <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Настройки навигация">
        {{ $slot }}
    </nav>

    {{-- Logout --}}
    <div class="px-3 pb-2">
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-sidebar-secondary transition hover:bg-sidebar-hover hover:text-text-sidebar"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" x2="9" y1="12" y2="12" />
                </svg>
                Выйти
            </button>
        </form>
    </div>

    {{-- Footer --}}
    <div class="border-t border-border-sidebar px-4 py-4 text-xs text-text-sidebar-secondary">
        <span>{{ $version }}</span>
        <span class="mx-1">·</span>
        <a href="{{ $docsUrl }}" target="_blank" rel="noopener noreferrer" class="text-accent transition hover:underline">Документация</a>
    </div>
</aside>
