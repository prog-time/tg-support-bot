@props([
    'botUser',
    'isActive' => false,
    'hasUnread' => false,
    'unreadCount' => 0,
])

@php
    /*
     * Deterministic avatar colour derived from chat_id (8 options).
     * Matches the hex colours used in the WyN0x / S4QeyR design nodes.
     */
    $avatarHex = [
        '#6366F1', '#E85D75', '#34C759', '#F5A623',
        '#06B6D4', '#10B981', '#8B5CF6', '#EF4444',
    ];
    $avatarIndex  = abs(crc32((string) $botUser->chat_id)) % 8;
    $avatarColor  = $avatarHex[$avatarIndex];
    $displayLabel = $botUser->custom_name ?: ($botUser->display_name ?? (string) $botUser->chat_id);
    $initials     = strtoupper(substr($displayLabel, 0, 2));

    /*
     * Platform badge — small coloured pill (TG / VK / Web / Max).
     * Design: node QeXDw — small frame, cornerRadius 3, fill = platform colour.
     */
    $platformShort = match ($botUser->platform) {
        'telegram' => 'TG',
        'vk'       => 'VK',
        'max'      => 'Max',
        default    => 'Web',
    };
    $platformBgHex = match ($botUser->platform) {
        'telegram' => '#2AABEE',
        'vk'       => '#4C75A3',
        'max'      => '#F5A623',
        default    => '#6B7280',
    };

    $preview = $botUser->lastMessage?->text
        ? mb_substr($botUser->lastMessage->text, 0, 40) . (mb_strlen($botUser->lastMessage->text) > 40 ? '…' : '')
        : 'Нет сообщений';

    /*
     * Last-message timestamp — Telegram-style, so the date is visible and the
     * date-descending dialog order reads correctly (a time-only "H:i" makes a
     * yesterday-22:46 row look out of place above a today-00:16 row).
     *   today      → "15:55"
     *   yesterday  → "Вчера"
     *   this year  → "06.06"
     *   older      → "06.06.25"
     */
    $lastAt = $botUser->lastMessage?->created_at;
    $timestamp = match (true) {
        $lastAt === null         => null,
        $lastAt->isToday()       => $lastAt->format('H:i'),
        $lastAt->isYesterday()   => 'Вчера',
        $lastAt->isCurrentYear() => $lastAt->format('d.m'),
        default                  => $lastAt->format('d.m.y'),
    };

    $isClosed = (bool) $botUser->is_closed;
    $isBanned = (bool) $botUser->is_banned;
@endphp

{{--
    Chat Item — matches design WyN0x
    Structure:
      [Avatar 44×44] [Info column]
        Info:
          Top row:  [Name (14/600 white) + Platform badge]  [Timestamp (12 text-sidebar-secondary)]
          Bottom row: [Message preview (13 text-sidebar-secondary, truncate)]  [Unread badge]
    Active: bg-sidebar-active; hover: bg-sidebar-hover; padding [12,16]; gap 12
--}}
<div
    class="flex items-center w-full cursor-pointer transition-colors"
    style="padding: 12px 16px; gap: 12px;
           background: {{ $isActive ? '#2D3348' : 'transparent' }};"
    onmouseenter="if(!{{ $isActive ? 'true' : 'false' }}) this.style.background='#252A3A'"
    onmouseleave="if(!{{ $isActive ? 'true' : 'false' }}) this.style.background='transparent'"
>
    {{-- Avatar: 44×44 circle — photo if available, else deterministic color + initials --}}
    {{-- Design: node STtU2 — cornerRadius 22, fill = avatar color; Lqpcy — initials text --}}
    @if($botUser->avatar_path)
        <img
            src="{{ route('admin.bot-user-avatar', $botUser->id) }}"
            alt="{{ $displayLabel }}"
            class="relative shrink-0 rounded-full object-cover select-none"
            style="width:44px; height:44px; border-radius:22px; {{ ($isClosed || $isBanned) ? 'opacity:0.5;' : '' }}"
            aria-hidden="true"
        >
    @else
        <div
            class="relative flex shrink-0 items-center justify-center rounded-full text-white select-none"
            style="width:44px; height:44px; background:{{ $avatarColor }}; font-size:15px; font-weight:600; border-radius:22px; {{ ($isClosed || $isBanned) ? 'opacity:0.5;' : '' }}"
            aria-hidden="true"
        >{{ $initials }}</div>
    @endif

    {{-- Info column --}}
    {{-- Design: node s9GO9k — vertical, gap 4, fill_container --}}
    <div class="flex flex-col min-w-0 flex-1" style="gap:4px;">

        {{-- Top row: Name + Platform badge | Timestamp --}}
        {{-- Design: node XHiap — space-between, align center --}}
        <div class="flex items-center justify-between w-full" style="gap:6px;">

            {{-- Name Row: name + platform badge --}}
            {{-- Design: node qwERT — gap 6, align center --}}
            <div class="flex items-center min-w-0" style="gap:6px;">
                <span class="truncate text-text-sidebar font-semibold" style="font-size:14px;">
                    {{ $displayLabel }}
                </span>
                {{-- Platform badge --}}
                {{-- Design: node QeXDw — cornerRadius 3, padding [2,5], platform fill --}}
                <span
                    class="shrink-0 text-white"
                    style="background:{{ $platformBgHex }}; border-radius:3px; padding:2px 5px; font-size:10px; font-weight:600; white-space:nowrap;"
                >{{ $platformShort }}</span>

                {{-- Status badge — banned takes priority over closed --}}
                @if($isBanned)
                    <span
                        class="shrink-0 inline-flex items-center"
                        style="background:#3A2A33; color:#F87171; border-radius:3px; padding:2px 5px; font-size:10px; font-weight:600; white-space:nowrap; gap:3px;"
                        title="Пользователь заблокирован"
                    >
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>
                        </svg>
                        Заблокирован
                    </span>
                @elseif($isClosed)
                    <span
                        class="shrink-0 inline-flex items-center text-text-sidebar-secondary"
                        style="background:#2D3348; border-radius:3px; padding:2px 5px; font-size:10px; font-weight:600; white-space:nowrap; gap:3px;"
                        title="Обращение закрыто"
                    >
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Закрыт
                    </span>
                @endif
            </div>

            {{-- Timestamp --}}
            {{-- Design: node yp2ZE — 12/normal text-sidebar-secondary --}}
            @if($timestamp)
                <span class="shrink-0 text-text-sidebar-secondary" style="font-size:12px;">{{ $timestamp }}</span>
            @endif
        </div>

        @if(!empty($botUser->support_tags))
            <div class="flex flex-wrap gap-1">
                @foreach($botUser->support_tags as $tag)
                    <span class="rounded px-1.5 py-0.5 text-[10px] font-medium" style="background:#343D62;color:#C7D4FF;">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        {{-- Bottom row: preview | unread badge --}}
        {{-- Design: node U5Lqf — space-between, gap 8 --}}
        <div class="flex items-center justify-between w-full" style="gap:8px;">

            {{-- Message preview — truncate --}}
            {{-- Design: node cvN2Z — 13/normal text-sidebar-secondary, fixed-width truncate --}}
            <span class="truncate text-text-sidebar-secondary" style="font-size:13px; min-width:0;">
                {{ $preview }}
            </span>

            {{-- Unread badge — shows the count of new incoming messages --}}
            {{-- Design: node IFQZ6 — cornerRadius 10, bg-badge #4F6EF7, padding [2,7] --}}
            @if($hasUnread)
                @php
                    $unreadLabel = $unreadCount > 99 ? '99+' : (string) max($unreadCount, 1);
                @endphp
                <span
                    class="shrink-0 flex items-center justify-center text-white font-semibold"
                    style="background:#4F6EF7; border-radius:10px; padding:2px 7px; font-size:11px; min-width:20px; line-height:16px;"
                    title="{{ $unreadLabel }} новых сообщений"
                >{{ $unreadLabel }}</span>
            @endif
        </div>
    </div>
</div>
