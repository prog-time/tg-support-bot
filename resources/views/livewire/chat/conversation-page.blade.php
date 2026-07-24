{{--
    Manager Chat Workspace — full-screen 3-column layout
    ─────────────────────────────────────────────────────────────────────────────
    Design: admin-tg-support-bot.pen  node S4QeyR (desktop), AwhSc (media),
            K1dijv / lo3D5 (mobile)
    ─────────────────────────────────────────────────────────────────────────────
    Desktop  [Left 360px | Center flex-1 | Right 300px]
    Tablet   [Left 360px | Center flex-1 ]   (right panel hidden < lg)
    Mobile   list-only ↔ chat screen  (toggled by activeBotUserId)
    ─────────────────────────────────────────────────────────────────────────────
    Polling: 5 s (wire:poll.5s → pollUpdates: refresh list + active thread)
--}}

<div
    class="flex h-screen overflow-hidden"
    {{-- .keep-alive keeps polling while the tab is in the background, so desktop
         notifications / sound / favicon badge fire even when the operator is on
         another tab (Livewire pauses a plain wire:poll when the tab is hidden). --}}
    wire:poll.5s.keep-alive="pollUpdates"
    x-data="{
        lightboxSrc: '',
        lightboxOpen: false,
        infoPanelOpen: false,
        originalFavicon: null,
        originalFaviconType: null,
        pendingCount: 0,
        showNotification(detail) {
            // Preferences are managed in Settings → Основные (browser-level).
            if (typeof Notification === 'undefined' || Notification.permission !== 'granted') { return; }
            // Don't interrupt while the operator is actively looking at the workspace —
            // the dialog-list badge already covers that case.
            if (document.hasFocus()) { return; }
            const n = new Notification(detail.title, {
                body: detail.body,
                tag: 'tg-support-chat',
                renotify: true,
            });
            n.onclick = () => { window.focus(); n.close(); };
        },
        playSound() {
            // Sound on/off lives in localStorage; the chosen preset and the actual
            // Web Audio engine live in window.tgSupportSound (Settings → Основные).
            if (localStorage.getItem('tg-support-sound') === '0') { return; }
            if (window.tgSupportSound) { window.tgSupportSound.playSelected(); }
        },
        faviconEl() {
            let el = document.querySelector('link[rel~=icon]');
            if (!el) {
                el = document.createElement('link');
                el.setAttribute('rel', 'icon');
                document.head.appendChild(el);
            }
            return el;
        },
        setFaviconAlert(count) {
            // Redraw the favicon with a red badge (count) over the current icon.
            try {
                const size = 64;
                const canvas = document.createElement('canvas');
                canvas.width = size; canvas.height = size;
                const ctx = canvas.getContext('2d');
                const render = (baseImg) => {
                    ctx.clearRect(0, 0, size, size);
                    if (baseImg) {
                        ctx.drawImage(baseImg, 0, 0, size, size);
                    } else {
                        ctx.fillStyle = '#4F6EF7';
                        ctx.beginPath(); ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2); ctx.fill();
                    }
                    const r = 24, cx = size - r, cy = r;
                    ctx.fillStyle = '#EF4444';
                    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
                    ctx.fillStyle = '#FFFFFF';
                    ctx.font = 'bold 34px sans-serif';
                    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                    ctx.fillText(count > 9 ? '9+' : String(count), cx, cy + 2);
                    const el = this.faviconEl();
                    el.setAttribute('type', 'image/png');
                    el.setAttribute('href', canvas.toDataURL('image/png'));
                };
                const img = new Image();
                img.onload = () => render(img);
                img.onerror = () => render(null);
                img.src = this.originalFavicon || '/favicon.ico';
            } catch (e) {}
        },
        restoreFavicon() {
            this.pendingCount = 0;
            const el = this.faviconEl();
            el.setAttribute('type', this.originalFaviconType || 'image/x-icon');
            el.setAttribute('href', this.originalFavicon || '/favicon.ico');
        },
        notifyFavicon(detail) {
            // Only badge the tab while it's in the background (operator is elsewhere).
            if (!document.hidden) { return; }
            this.pendingCount += (detail && detail.count ? detail.count : 1);
            this.setFaviconAlert(this.pendingCount);
        }
    }"
    x-init="
        originalFavicon = faviconEl().getAttribute('href');
        originalFaviconType = faviconEl().getAttribute('type');
        document.addEventListener('visibilitychange', () => { if (!document.hidden) restoreFavicon(); });
        window.addEventListener('focus', () => restoreFavicon());
        ['click','keydown'].forEach(ev => document.addEventListener(ev, () => { if (window.tgSupportSound) window.tgSupportSound.unlock(); }, { once: true }));
    "
    x-on:open-lightbox.window="lightboxSrc = $event.detail.src; lightboxOpen = true"
    x-on:new-incoming-messages.window="showNotification($event.detail); playSound(); notifyFavicon($event.detail)"
    x-on:messages-updated.window="$nextTick(() => {
        const thread = document.getElementById('chat-thread');
        if (thread) thread.scrollTop = thread.scrollHeight;
    })"
>

    {{-- ── Lightbox overlay ──────────────────────────────────────────────────── --}}
    <template x-teleport="body">
        <div
            x-on:click="lightboxOpen = false"
            x-on:keydown.escape.window="lightboxOpen = false"
            class="fixed inset-0 z-[100000] flex items-center justify-center cursor-zoom-out"
            :class="lightboxOpen ? 'pointer-events-auto' : 'pointer-events-none'"
            :style="{ opacity: lightboxOpen ? 1 : 0, transition: 'opacity 300ms ease' }"
            style="opacity:0"
        >
            <div class="absolute inset-0 bg-black" style="opacity:0.85"></div>
            <button
                x-on:click.stop="lightboxOpen = false"
                class="fixed top-16 right-4 text-white text-4xl leading-none opacity-80 hover:opacity-100 bg-transparent border-none cursor-pointer z-[100001]"
                aria-label="Закрыть"
            >&times;</button>
            <img
                :src="lightboxSrc"
                x-on:click.stop
                class="relative z-[100001] object-contain rounded-lg shadow-2xl cursor-default block"
                style="max-width:min(85vw,960px);max-height:80vh"
                alt="Просмотр изображения"
            >
        </div>
    </template>

    {{-- ══ LEFT SIDEBAR — Dialog list ════════════════════════════════════════ --}}
    {{--
        Design: node M1Hgk — bg-sidebar #1B1F2E, width 360, padding [20,16,0,16], gap 16
        Mobile: visible when no dialog is selected
        Desktop: always visible
    --}}
    <aside
        class="flex shrink-0 flex-col bg-sidebar
               w-full md:w-[360px]
               {{ $activeBotUserId ? 'hidden md:flex' : 'flex' }}"
        style="gap:16px; padding: 20px 16px 0 16px;"
    >
        {{-- Header: "TG Support" + settings gear --}}
        {{-- Design: node AQ4LA — space-between, white 20/700 + settings icon #8B92A5 --}}
        <div class="flex items-center justify-between w-full shrink-0">
            <span class="text-text-sidebar font-bold" style="font-size:20px; line-height:1.2;">TG Support</span>
            <div class="flex items-center shrink-0" style="gap:4px;">
            <a
                href="{{ route('admin.settings.general') }}"
                wire:navigate
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-text-sidebar-secondary transition hover:bg-sidebar-active hover:text-text-sidebar"
                aria-label="Настройки"
                title="Настройки"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="20" height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M12.22 2h-.44a2 2 0 00-2 2v.18a2 2 0 01-1 1.73l-.43.25a2 2 0 01-2 0l-.15-.08a2 2 0 00-2.73.73l-.22.38a2 2 0 00.73 2.73l.15.1a2 2 0 011 1.72v.51a2 2 0 01-1 1.74l-.15.09a2 2 0 00-.73 2.73l.22.38a2 2 0 002.73.73l.15-.08a2 2 0 012 0l.43.25a2 2 0 011 1.73V20a2 2 0 002 2h.44a2 2 0 002-2v-.18a2 2 0 011-1.73l.43-.25a2 2 0 012 0l.15.08a2 2 0 002.73-.73l.22-.39a2 2 0 00-.73-2.73l-.15-.08a2 2 0 01-1-1.74v-.5a2 2 0 011-1.74l.15-.09a2 2 0 00.73-2.73l-.22-.38a2 2 0 00-2.73-.73l-.15.08a2 2 0 01-2 0l-.43-.25a2 2 0 01-1-1.73V4a2 2 0 00-2-2z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-text-sidebar-secondary transition hover:bg-sidebar-active hover:text-text-sidebar"
                    aria-label="Выйти"
                    title="Выйти"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" x2="9" y1="12" y2="12" />
                    </svg>
                </button>
            </form>
            </div>
        </div>

        {{-- Filter tabs: pill-style. Active = bg-accent text-white rounded-md 13/600 --}}
        {{-- Design: node YYO8P — gap 4; BitoF active pill bg-accent; others transparent text-sidebar-secondary --}}
        <div class="flex shrink-0" style="gap:4px;">
            @foreach(['all' => 'Все', 'open' => 'Открытые', 'closed' => 'Закрытые'] as $value => $label)
                <button
                    wire:click="$set('statusFilter', '{{ $value }}')"
                    class="rounded-md transition-colors cursor-pointer"
                    style="padding: 6px 12px; font-size:13px; font-weight: {{ $statusFilter === $value ? '600' : '400' }}; {{ $statusFilter === $value ? 'background:#4F6EF7; color:#FFFFFF;' : 'background:transparent; color:#8B92A5;' }}"
                    type="button"
                >{{ $label }}</button>
            @endforeach
        </div>

        {{-- Divider --}}
        {{-- Design: node FvpDg — bg border-sidebar #2D3348, height 1 --}}
        <div class="w-full shrink-0 h-px bg-border-sidebar"></div>

        {{-- Dialog list --}}
        <div
            class="chat-list-scroll flex-1 overflow-y-auto -mx-4 pb-4"
            x-data="{
                loadingMore: false,
                onScroll() {
                    const el = this.$el;
                    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 120 && this.$wire.hasMoreDialogs && !this.loadingMore) {
                        this.loadingMore = true;
                        this.$wire.loadMoreDialogs().then(() => { this.loadingMore = false; });
                    }
                }
            }"
            x-on:scroll.passive="onScroll()"
        >
            @forelse($dialogList as $user)
                <div
                    wire:click="selectChat({{ $user->id }})"
                    wire:key="dialog-{{ $user->id }}"
                    class="cursor-pointer"
                >
                    <x-chat-item
                        :bot-user="$user"
                        :is-active="$activeBotUserId === $user->id"
                        :has-unread="$this->hasUnread($user)"
                        :unread-count="$this->unreadCount($user)"
                    />
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-text-sidebar-secondary">Нет диалогов</p>
            @endforelse

            {{-- Load-more spinner (infinite scroll) --}}
            @if($hasMoreDialogs)
                <div class="flex justify-center py-3" wire:loading.flex wire:target="loadMoreDialogs">
                    <svg class="h-5 w-5 animate-spin text-text-sidebar-secondary" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                </div>
            @endif
        </div>
    </aside>

    {{-- ══ CENTER — Chat area ═════════════════════════════════════════════════ --}}
    {{-- Design: node YMMFj — bg-primary, layout vertical, fill_container --}}
    <main
        class="relative flex flex-1 flex-col bg-bg-primary overflow-hidden
               {{ $activeBotUserId ? 'flex' : 'hidden md:flex' }}"
        @if($activeBotUser && $this->shouldShowReplyForm() && $this->supportsAttachments())
            x-data="{ dragging: false, depth: 0 }"
            x-on:dragenter.prevent="if ($event.dataTransfer?.types?.includes('Files')) { depth++; dragging = true; }"
            x-on:dragover.prevent
            x-on:dragleave.prevent="if (--depth <= 0) { depth = 0; dragging = false; }"
            x-on:drop.prevent="
                depth = 0; dragging = false;
                const file = $event.dataTransfer?.files?.[0];
                if (file) { $wire.upload('attachment', file); }
            "
        @endif
    >
        {{-- Preloader — shown while a chat is being opened (selectChat round-trip) --}}
        <div
            wire:loading.flex
            wire:target="selectChat"
            wire:key="chat-preloader"
            class="absolute inset-0 z-20 flex items-center justify-center bg-bg-secondary"
            aria-hidden="true"
        >
            <svg class="h-9 w-9 animate-spin text-accent" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        </div>

        {{-- Drag-and-drop overlay — shown while a file is dragged over the conversation pane.
             pointer-events-none lets the drag/drop events pass through to <main> handlers. --}}
        @if($activeBotUser && $this->shouldShowReplyForm() && $this->supportsAttachments())
            <div
                x-show="dragging"
                x-cloak
                class="absolute inset-0 z-30 flex flex-col items-center justify-center pointer-events-none"
                style="margin:12px; border:2px dashed #4F6EF7; border-radius:16px; background:rgba(79,110,247,0.08); gap:10px; color:#4F6EF7;"
                aria-hidden="true"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                </svg>
                <p class="text-sm font-semibold">Отпустите файл, чтобы прикрепить</p>
            </div>
        @endif

        @if($activeBotUser)

            {{-- Chat header --}}
            {{-- Design: node xQRXL — height 72, padding [0,24], space-between, bg-primary --}}
            <div class="flex items-center justify-between shrink-0 bg-bg-primary border-b border-border-light" style="height:72px; padding: 0 10px;">

                {{-- Header Left: mobile back + clickable (avatar + name → opens info panel) --}}
                {{-- Design: node nQRoU — gap 14, alignItems center --}}
                <div class="flex items-center min-w-0" style="gap:14px;">

                    {{-- Mobile back button --}}
                    <button
                        wire:click="selectChat(0)"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-text-secondary transition hover:bg-bg-secondary md:hidden shrink-0"
                        aria-label="Назад к списку"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    @php
                        $hdrColors = ['#6366F1','#E85D75','#34C759','#F5A623','#06B6D4','#10B981','#8B5CF6','#EF4444'];
                        $hdrIdx = abs(crc32((string) $activeBotUser->chat_id)) % 8;
                        $hdrColor = $hdrColors[$hdrIdx];
                        $hdrDisplayName = $activeBotUser->display_name ?? (string) $activeBotUser->chat_id;
                        $hdrInitials = strtoupper(substr($hdrDisplayName, 0, 2));
                        $hdrHandle = $activeBotUser->username;
                        $platformLabel = match ($activeBotUser->platform) {
                            'telegram' => 'Telegram',
                            'vk'       => 'VK',
                            'max'      => 'Max',
                            default    => ucfirst($activeBotUser->platform),
                        };
                    @endphp

                    {{-- Avatar + name — click opens the info panel (Telegram-style overlay) --}}
                    <div
                        x-on:click="infoPanelOpen = true"
                        x-on:keydown.enter="infoPanelOpen = true"
                        role="button"
                        tabindex="0"
                        class="flex items-center min-w-0 cursor-pointer rounded-lg px-2 -mx-2 py-1 transition hover:bg-bg-secondary"
                        style="gap:14px;"
                        aria-label="Открыть сведения о клиенте"
                        title="Сведения о клиенте"
                    >
                        {{-- User avatar (42×42 ellipse) — photo or initials --}}
                        @if($activeBotUser->avatar_path)
                            <img
                                src="{{ route('admin.bot-user-avatar', $activeBotUser->id) }}"
                                alt="{{ $hdrDisplayName }}"
                                class="relative shrink-0 rounded-full object-cover select-none"
                                style="width:42px; height:42px;"
                                aria-hidden="true"
                            >
                        @else
                            <div
                                class="relative shrink-0 flex items-center justify-center rounded-full text-white font-semibold select-none"
                                style="width:42px; height:42px; background:{{ $hdrColor }}; font-size:16px;"
                                aria-hidden="true"
                            >{{ $hdrInitials }}</div>
                        @endif

                        {{-- Name + platform sub-line --}}
                        {{-- Design: node O6aEj — vertical layout gap 2 --}}
                        <div class="flex flex-col min-w-0" style="gap:2px;">
                            <span class="text-sm font-semibold text-text-primary leading-tight truncate max-w-[280px]">
                                {{ $hdrDisplayName }}
                            </span>
                            <span class="text-xs text-text-secondary leading-tight truncate">
                                @if($hdrHandle){{ '@' . $hdrHandle }} · @endif{{ $platformLabel }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Header Actions: more-actions dropdown --}}
                {{-- Design: node r6DYj — gap 4, align center --}}
                <div class="flex items-center" style="gap:4px;">

                    {{-- More actions (⋮) dropdown --}}
                    <div class="relative" x-data="{ menuOpen: false }">
                        <button
                            type="button"
                            x-on:click="menuOpen = !menuOpen"
                            :class="menuOpen ? 'bg-bg-secondary text-accent' : 'text-text-secondary hover:bg-bg-secondary'"
                            class="flex items-center justify-center rounded-lg transition"
                            style="width:36px; height:36px; border:none; cursor:pointer; background:transparent;"
                            aria-label="Действия с чатом"
                            aria-haspopup="true"
                            :aria-expanded="menuOpen"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>
                            </svg>
                        </button>

                        <div
                            x-show="menuOpen"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            x-on:click.outside="menuOpen = false"
                            x-on:keydown.escape.window="menuOpen = false"
                            class="absolute right-0 top-full z-50 mt-2 w-max whitespace-nowrap origin-top-right rounded-xl border border-border-light bg-bg-primary py-1.5 shadow-xl"
                            role="menu"
                        >
                            {{-- Показать профиль --}}
                            <button
                                type="button"
                                role="menuitem"
                                x-on:click="menuOpen = false; infoPanelOpen = true"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-text-primary transition hover:bg-bg-secondary"
                                style="cursor:pointer;"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                </svg>
                                Показать профиль
                            </button>

                            {{-- Очистить историю --}}
                            <button
                                type="button"
                                role="menuitem"
                                wire:click="clearHistory"
                                wire:confirm="Очистить историю переписки? Все сообщения этого чата будут удалены, сам чат останется."
                                x-on:click="menuOpen = false"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-text-primary transition hover:bg-bg-secondary"
                                style="cursor:pointer;"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/>
                                </svg>
                                Очистить историю
                            </button>

                            @if($this->isAdmin())
                                {{-- Divider --}}
                                <div class="my-1 h-px bg-border-light"></div>

                                {{-- Удалить чат (last, red) — admin only --}}
                                <button
                                    type="button"
                                    role="menuitem"
                                    wire:click="deleteChat"
                                    wire:confirm="Удалить чат и все его сообщения? Действие необратимо."
                                    x-on:click="menuOpen = false"
                                    class="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-red-600 transition hover:bg-red-50"
                                    style="cursor:pointer;"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
                                    </svg>
                                    Удалить чат
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Message thread --}}
            {{-- Design: node bCcH9 — bg-secondary, padding [24,32], gap 16, bottom-stacked via inner mt-auto, fill_container --}}
            <div
                id="chat-thread"
                class="flex flex-col flex-1 overflow-y-auto bg-bg-secondary"
                style="padding:24px 32px;"
                x-data="{
                    loadingOlder: false,
                    onScroll() {
                        const el = this.$el;
                        if (el.scrollTop <= 80 && this.$wire.hasMoreMessages && !this.loadingOlder) {
                            this.loadingOlder = true;
                            const prev = el.scrollHeight;
                            this.$wire.loadOlderMessages().then(() => this.$nextTick(() => {
                                el.scrollTop = el.scrollHeight - prev;
                                this.loadingOlder = false;
                            }));
                        }
                    }
                }"
                x-init="$el.scrollTop = $el.scrollHeight"
                x-on:scroll.passive="onScroll()"
            >
                {{-- Older-messages loader (reverse infinite scroll) --}}
                @if($hasMoreMessages)
                    <div class="flex shrink-0 items-center justify-center py-3" wire:loading.flex wire:target="loadOlderMessages">
                        <svg class="h-5 w-5 animate-spin text-text-secondary" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </div>
                @endif

                @if($chatMessages->isEmpty())
                    <div class="flex flex-1 items-center justify-center">
                        <p class="text-sm text-text-secondary">Нет сообщений</p>
                    </div>
                @else
                {{-- mt-auto pins messages to the bottom when they don't fill the
                     viewport, while keeping the container a plain top-anchored
                     scroll area so overflow stays reachable (justify-end clips it). --}}
                <div class="flex flex-col mt-auto w-full" style="gap:16px;">
                @foreach($chatMessages as $message)
                    @php
                        $msgDate   = $message->created_at?->toDateString();
                        $prevDate  = $loop->first ? null : $chatMessages[$loop->index - 1]->created_at?->toDateString();
                        $today     = \Carbon\Carbon::today()->toDateString();
                        $yesterday = \Carbon\Carbon::yesterday()->toDateString();

                        $showDateSep = $loop->first || $msgDate !== $prevDate;
                        if ($showDateSep && $msgDate) {
                            $dateLabel = match(true) {
                                $msgDate === $today     => 'Сегодня',
                                $msgDate === $yesterday => 'Вчера',
                                default => \Carbon\Carbon::parse($msgDate)->locale('ru')->isoFormat('D MMMM YYYY'),
                            };
                        }
                    @endphp

                    {{-- Date separator --}}
                    {{-- Design: node AG5V7 — gap 16, center, lines + date text --}}
                    @if($showDateSep && $msgDate)
                        <div class="flex items-center w-full" style="gap:16px;">
                            <span class="flex-1 border-t border-border-light"></span>
                            <span class="text-xs text-text-secondary whitespace-nowrap" style="font-size:12px;">{{ $dateLabel }}</span>
                            <span class="flex-1 border-t border-border-light"></span>
                        </div>
                    @endif

                    {{-- Message bubble --}}
                    @php
                        $isOutgoing = $message->message_type === 'outgoing';
                        $messageText = $message->text ?? $message->externalMessage?->text;
                    @endphp

                    @if($isOutgoing)
                        {{-- Outgoing: right-aligned with small manager avatar, bg-accent (#4F6EF7), white text, cornerRadius [16,16,4,16] --}}
                        {{-- Design: node uduZU / Bubble Out — mirrors the incoming layout (bubble + 32×32 avatar) --}}
                        <div class="flex items-end w-full" style="gap:10px; justify-content:flex-end;">
                            {{-- Bubble --}}
                            <div class="flex flex-col" style="border-radius:16px 16px 4px 16px; background:#4F6EF7; padding:10px 14px; gap:4px; max-width:70%;">
                                @if($message->attachments->isNotEmpty())
                                    <x-message-attachments
                                        :attachments="$message->attachments"
                                        :platform="$message->platform"
                                        :is-outgoing="true"
                                    />
                                @endif
                                @if($messageText)
                                    <p class="text-sm text-white" style="font-size:14px; line-height:1.4; white-space:pre-wrap; overflow-wrap:anywhere;">{{ $messageText }}</p>
                                @elseif($message->attachments->isEmpty())
                                    <p class="text-xs text-white opacity-70 italic">Вложение</p>
                                @endif
                                <p class="text-right text-white opacity-70" style="font-size:11px;">
                                    {{ $message->created_at?->format('H:i') }}
                                </p>
                            </div>
                            {{-- Operator avatar: photo > initials > generic headset glyph --}}
                            @php
                                $sender = $message->sender;
                                $senderName = $sender?->name ?? $message->sender_name;
                                // Derive 2-letter initials from the name.
                                $senderInitials = null;
                                if ($senderName) {
                                    $parts = explode(' ', trim($senderName));
                                    $senderInitials = count($parts) >= 2
                                        ? mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1))
                                        : mb_strtoupper(mb_substr($parts[0], 0, 2));
                                }
                                // 8-color palette (same hue set used for team-member initials).
                                $avatarPalette = ['#6366F1','#8B5CF6','#EC4899','#F59E0B','#10B981','#3B82F6','#EF4444','#14B8A6'];
                                $senderColor = $senderName
                                    ? $avatarPalette[abs(crc32($senderName)) % 8]
                                    : '#4F6EF7';
                            @endphp
                            @if($sender && $sender->avatar_path)
                                {{-- Known operator with uploaded photo --}}
                                <img
                                    src="{{ route('admin.team-member-avatar', $sender) }}"
                                    alt="{{ $senderName }}"
                                    title="{{ $senderName }}"
                                    class="shrink-0 rounded-full object-cover select-none"
                                    style="width:32px; height:32px;"
                                >
                            @elseif($senderInitials)
                                {{-- Known operator — show initials circle --}}
                                <div
                                    class="flex shrink-0 items-center justify-center rounded-full text-white font-semibold select-none"
                                    style="width:32px; height:32px; background:{{ $senderColor }}; font-size:11px;"
                                    title="{{ $senderName }}"
                                    aria-hidden="true"
                                >{{ $senderInitials }}</div>
                            @else
                                {{-- No author recorded — generic headset glyph (historical / AI / telegram-group) --}}
                                <div
                                    class="flex shrink-0 items-center justify-center rounded-full select-none"
                                    style="width:32px; height:32px; background:#EEF2FF;"
                                    aria-hidden="true"
                                    title="Менеджер"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4F6EF7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 14v-3a9 9 0 0 1 18 0v3" />
                                        <path d="M21 16a2 2 0 0 1-2 2h-1a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h3z" />
                                        <path d="M3 16a2 2 0 0 0 2 2h1a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1H3z" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Incoming: left-aligned with small avatar, bg-primary / bg-input bubble, cornerRadius [16,16,16,4] --}}
                        {{-- Design: node HGFji / Bubble In -- avatar 32×32 ellipse + bubble --}}
                        <div class="flex items-end w-full" style="gap:10px;">
                            {{-- Small avatar — photo or initials (mirrors the header) --}}
                            @if($activeBotUser->avatar_path)
                                <img
                                    src="{{ route('admin.bot-user-avatar', $activeBotUser->id) }}"
                                    alt="{{ $hdrDisplayName }}"
                                    class="flex shrink-0 rounded-full object-cover select-none"
                                    style="width:32px; height:32px;"
                                    aria-hidden="true"
                                >
                            @else
                                <div
                                    class="flex shrink-0 items-center justify-center rounded-full text-white font-semibold select-none"
                                    style="width:32px; height:32px; background:{{ $hdrColor }}; font-size:11px;"
                                    aria-hidden="true"
                                >{{ $hdrInitials }}</div>
                            @endif
                            {{-- Bubble --}}
                            <div class="flex flex-col" style="border-radius:16px 16px 16px 4px; background:#FFFFFF; border:1px solid #E5E7EB; padding:10px 14px; gap:4px; max-width:70%;">
                                @if($message->attachments->isNotEmpty())
                                    <x-message-attachments
                                        :attachments="$message->attachments"
                                        :platform="$message->platform"
                                        :is-outgoing="false"
                                    />
                                @endif
                                @if($messageText)
                                    <p class="text-sm text-text-primary" style="font-size:14px; line-height:1.4; white-space:pre-wrap; overflow-wrap:anywhere;">{{ $messageText }}</p>
                                @elseif($message->attachments->isEmpty())
                                    <p class="text-xs text-text-secondary italic opacity-60">Вложение</p>
                                @endif
                                <p class="text-text-secondary opacity-70" style="font-size:11px;">
                                    {{ $message->created_at?->format('H:i') }}
                                </p>
                            </div>
                        </div>
                    @endif
                @endforeach
                </div>
                @endif

                {{-- ── Pending AI drafts — shown in the admin workspace (always-both) ───── --}}
                @if($pendingAiDrafts->isNotEmpty())
                    <div class="flex flex-col" style="gap:12px; padding:0 0 8px 0;">
                        @foreach($pendingAiDrafts as $draft)
                            <div
                                wire:key="ai-draft-{{ $draft->id }}"
                                wire:loading.class="opacity-60"
                                wire:target="acceptAiDraft, editAiDraft, cancelAiDraft"
                                class="flex flex-col transition-opacity"
                                style="border:2px dashed #4F6EF7; border-radius:14px 14px 0 14px; background:#F5F7FF; padding:14px 16px; gap:10px; margin-top:16px;">
                                {{-- Header --}}
                                <div class="flex items-center" style="gap:8px;">
                                    <div class="flex items-center justify-center rounded-lg text-white font-bold" style="width:28px; height:28px; background:#4F6EF7; font-size:11px; flex-shrink:0;">ИИ</div>
                                    <span class="text-xs font-semibold" style="color:#4F6EF7;">ИИ-черновик</span>
                                    <span class="text-xs" style="color:#9CA3AF; margin-left:auto;">{{ $draft->created_at?->format('H:i') }}</span>
                                </div>
                                {{-- Draft text --}}
                                <p class="text-sm text-text-primary" style="font-size:14px; line-height:1.5; white-space:pre-wrap; overflow-wrap:anywhere;">{{ $draft->text_ai }}</p>
                                {{-- Actions --}}
                                <div class="flex items-center" style="gap:8px; flex-wrap:wrap;">
                                    {{-- All three actions disable each other while any one is
                                         in flight (target by method name, not id) — the 5 s poll
                                         can hold the request slot, so without this a click looks
                                         dead and a second click fires late / out of order. --}}
                                    <button
                                        type="button"
                                        wire:click="acceptAiDraft({{ $draft->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="acceptAiDraft, editAiDraft, cancelAiDraft"
                                        class="flex items-center gap-1.5 rounded-lg text-white text-xs font-semibold transition hover:opacity-90 disabled:opacity-50 disabled:cursor-wait"
                                        style="background:#10B981; padding:7px 14px; border:none; cursor:pointer;"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Принять
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="editAiDraft({{ $draft->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="acceptAiDraft, editAiDraft, cancelAiDraft"
                                        class="flex items-center gap-1.5 rounded-lg text-xs font-semibold transition hover:opacity-80 disabled:opacity-50 disabled:cursor-wait"
                                        style="background:#EEF2FF; color:#4F6EF7; padding:7px 14px; border:none; cursor:pointer;"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Изменить
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="cancelAiDraft({{ $draft->id }})"
                                        wire:confirm="Отменить ИИ-черновик?"
                                        wire:loading.attr="disabled"
                                        wire:target="acceptAiDraft, editAiDraft, cancelAiDraft"
                                        class="flex items-center gap-1.5 rounded-lg text-xs font-semibold transition hover:opacity-80 disabled:opacity-50 disabled:cursor-wait"
                                        style="background:#FEE2E2; color:#EF4444; padding:7px 14px; border:none; cursor:pointer;"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Отмена
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Input area --}}
            {{-- Design: node zONmD — height 72, bg-primary, padding [12,24], gap 12, border-top --}}
            @if($this->shouldShowReplyForm())
                <div class="shrink-0 bg-bg-primary border-t border-border-light" style="padding: 12px 24px;">

                    {{-- Auto-reply chips — active rules from the auto_replies table --}}
                    {{-- Design: mobile lo3D5 node bQdAT — chips row above input --}}
                    @php $autoReplies = $this->getAutoReplies() @endphp
                    @if($autoReplies->isNotEmpty())
                        <div class="flex flex-wrap mb-2" style="gap:8px;">
                            @foreach($autoReplies as $autoReply)
                                <button
                                    type="button"
                                    wire:click="insertQuickReply(@js($autoReply->response))"
                                    title="{{ $autoReply->response }}"
                                    class="text-text-secondary transition hover:text-accent"
                                    style="border-radius:16px; background:#F1F3F5; padding:6px 12px; font-size:12px; border:none; cursor:pointer;"
                                >{{ $autoReply->trigger }}</button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Selected attachment preview / upload progress (telegram + vk only) --}}
                    @if($this->supportsAttachments())
                        {{-- Uploading spinner --}}
                        <div
                            wire:loading.flex
                            wire:target="attachment"
                            class="items-center mb-2"
                            style="gap:8px;"
                        >
                            <svg class="animate-spin text-accent" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                            </svg>
                            <span class="text-text-secondary" style="font-size:12px;">Загрузка файла…</span>
                        </div>

                        {{-- Selected file chip --}}
                        @if($attachment)
                            <div wire:loading.remove wire:target="attachment" class="flex items-center mb-2" style="gap:8px;">
                                <div class="flex items-center max-w-full" style="gap:8px; background:#EEF1FE; border:1px solid #D5DBF9; border-radius:8px; padding:6px 10px;">
                                    <svg class="shrink-0 text-accent" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                    <span class="truncate text-text-primary" style="font-size:12px; max-width:220px;">{{ $attachment->getClientOriginalName() }}</span>
                                    <button
                                        type="button"
                                        wire:click="removeAttachment"
                                        class="flex shrink-0 items-center justify-center text-text-secondary transition hover:text-red-500"
                                        style="width:16px; height:16px; border:none; background:transparent; cursor:pointer; line-height:1; font-size:16px;"
                                        aria-label="Убрать файл"
                                        title="Убрать файл"
                                    >&times;</button>
                                </div>
                            </div>
                        @endif

                        @error('attachment')
                            <p class="mb-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @endif

                    {{-- Input row: attach + textarea + send --}}
                    {{-- Design: FEYxe (attach 40×40) + Euru3 (input, fill, bg-input, rounded-12) + K6yaRa (send 44×44 bg-accent rounded-12) --}}
                    <form wire:submit.prevent="sendReply" class="flex items-end" style="gap:5px; align-items:flex-end;">

                        {{-- Attach button (telegram + vk only) --}}
                        @if($this->supportsAttachments())
                            <label
                                class="flex items-center justify-center shrink-0 text-text-secondary cursor-pointer transition hover:text-accent hover:bg-bg-secondary"
                                style="width:40px; height:40px; border-radius:10px;"
                                title="Прикрепить файл"
                                aria-label="Прикрепить файл"
                            >
                                <input type="file" wire:model="attachment" class="hidden" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                </svg>
                            </label>
                        @endif

                        {{-- Text input --}}
                        {{-- Auto-growing textarea: 1 line by default, grows with content
                             up to max-height, then scrolls.

                             The textarea carries wire:ignore and is bound through
                             @entangle instead of wire:model. This is deliberate: the
                             thread polls every 5 s (wire:poll), and a plain
                             wire:model.live re-rendered the textarea on every keystroke
                             AND every poll, so Livewire's morph reverted the inline
                             height Alpine had set — collapsing the field back to one
                             line and jerking the caret to the top (issue #211). With
                             wire:ignore the morph never touches the field, so height and
                             draft both survive typing and polls; @entangle keeps the
                             value synced with $replyText, so send/clear, quick replies
                             and AI drafts still drive it. autosize() runs on input and
                             whenever the entangled value changes (programmatic writes). --}}
                        <div class="relative flex-1 flex"
                             x-data="{
                                text: @entangle('replyText'),
                                autosize() {
                                    const el = this.$refs.ta;
                                    el.style.height = 'auto';
                                    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
                                },
                             }"
                             x-init="$nextTick(() => autosize()); $watch('text', () => $nextTick(() => autosize()))">
                            <textarea
                                wire:ignore
                                x-ref="ta"
                                x-model="text"
                                rows="1"
                                placeholder="Напишите сообщение..."
                                class="w-full resize-none text-sm text-text-primary placeholder-text-secondary outline-none border-none bg-transparent"
                                style="background:#F1F3F5; border-radius:12px; padding:12px 16px; line-height:1.25; min-height:44px; max-height:160px; overflow-y:auto;"
                                x-on:input="autosize()"
                                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); if (! $refs.ta.value.trim()) return; $wire.sendReply(); }"
                                aria-label="Текст сообщения"
                            ></textarea>
                            @error('replyText')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Send button --}}
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendReply,attachment,acceptAiDraft,editAiDraft,cancelAiDraft"
                            class="flex shrink-0 items-center justify-center text-white transition hover:opacity-90 disabled:opacity-50"
                            style="width:44px; height:44px; border-radius:12px; background:#4F6EF7; border:none; cursor:pointer;"
                            aria-label="Отправить"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m3 3 3 9-3 9 19-9Z"/><path d="M6 12h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            @endif

        @else
            {{-- Empty state --}}
            <div class="flex flex-1 flex-col items-center justify-center text-text-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 opacity-30" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p class="text-sm">Выберите диалог</p>
            </div>
        @endif
    </main>

    {{-- ══ RIGHT PANEL — User info & media gallery ════════════════════════════ --}}
    {{-- Design: node VdLTH — width 300, padding [24,20], gap 20, bg-primary, border-left --}}
    @if($activeBotUser)
        {{-- Centered modal: darkened backdrop centers the panel; click outside closes (Telegram-style) --}}
        <div
            x-show="infoPanelOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="infoPanelOpen = false"
            x-on:keydown.escape.window="infoPanelOpen = false"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
        >
            {{-- Info panel — narrow centered modal; clicks inside do not close --}}
            <aside
                x-show="infoPanelOpen"
                x-cloak
                x-on:click.stop
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="flex flex-col bg-bg-primary overflow-y-auto rounded-2xl border border-border-light shadow-2xl"
                style="gap:20px; padding:24px 20px; width:440px; max-width:92vw; max-height:85vh;"
            >

            {{-- Header with close button (panel is always an overlay now) --}}
            <div class="flex items-center justify-between">
                <span class="text-text-secondary font-semibold tracking-wider" style="font-size:12px; letter-spacing:0.05em;">СВЕДЕНИЯ</span>
                <button
                    type="button"
                    x-on:click="infoPanelOpen = false"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-text-secondary transition hover:bg-bg-secondary"
                    style="cursor:pointer;"
                    aria-label="Закрыть сведения"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Profile section --}}
            {{-- Design: node KAbQg — vertical, gap 14, align center --}}
            <div class="flex flex-col items-center" style="gap:14px;">

                {{-- Large avatar 64×64 --}}
                @php
                    $rpColors = ['#6366F1','#E85D75','#34C759','#F5A623','#06B6D4','#10B981','#8B5CF6','#EF4444'];
                    $rpIdx = abs(crc32((string) $activeBotUser->chat_id)) % 8;
                    $rpColor = $rpColors[$rpIdx];
                    $rpDisplayName = $activeBotUser->display_name ?? (string) $activeBotUser->chat_id;
                    $rpInitials = strtoupper(substr($rpDisplayName, 0, 2));
                    $rpHandle = $activeBotUser->username;
                @endphp
                @if($activeBotUser->avatar_path)
                    <img
                        src="{{ route('admin.bot-user-avatar', $activeBotUser->id) }}"
                        alt="{{ $rpDisplayName }}"
                        class="flex items-center justify-center rounded-full object-cover select-none shrink-0"
                        style="width:64px; height:64px; border-radius:32px;"
                        aria-hidden="true"
                    >
                @else
                    <div
                        class="flex items-center justify-center rounded-full text-white font-semibold select-none shrink-0"
                        style="width:64px; height:64px; background:{{ $rpColor }}; font-size:22px; border-radius:32px;"
                        aria-hidden="true"
                    >{{ $rpInitials }}</div>
                @endif

                {{-- Name + handle --}}
                {{-- Design: node wAn8z — vertical, gap 4, center --}}
                <div class="flex flex-col items-center w-full" style="gap:4px;">
                    <span class="text-text-primary font-semibold text-center" style="font-size:16px;">
                        {{ $rpDisplayName }}
                    </span>
                    @php
                        $rpPlatformLabel = match ($activeBotUser->platform) {
                            'telegram' => 'Telegram',
                            'vk'       => 'VK',
                            'max'      => 'Max',
                            default    => ucfirst($activeBotUser->platform),
                        };
                    @endphp
                    <span class="text-text-secondary text-center" style="font-size:13px;">
                        @if($rpHandle){{ '@' . $rpHandle }} · @endif{{ $rpPlatformLabel }}
                    </span>
                </div>

                {{-- Action buttons: Блок + Закрыть --}}
                {{-- Design: node uYnHt — gap 8 --}}
                {{-- Block = bg #FEE2E2 text #DC2626; Close = bg bg-input text-primary --}}
                <div class="flex" style="gap:8px;">
                    {{-- Блок / Разблокировать — toggles via banUser()/unbanUser() --}}
                    @php $isBanned = (bool) $activeBotUser->is_banned; @endphp
                    @if($isBanned)
                        <button
                            type="button"
                            wire:click="unbanUser"
                            wire:confirm="Разблокировать пользователя? Он снова сможет писать в поддержку."
                            wire:loading.attr="disabled"
                            wire:target="unbanUser"
                            class="flex items-center transition hover:opacity-90 disabled:opacity-50"
                            style="background:#DCFCE7; color:#16A34A; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:500; gap:6px; border:none; cursor:pointer;"
                            aria-label="Разблокировать пользователя"
                            title="Разблокировать пользователя"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                            </svg>
                            Разблокировать
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="banUser"
                            wire:confirm="Заблокировать пользователя? Его сообщения перестанут приниматься, а диалог будет закрыт."
                            wire:loading.attr="disabled"
                            wire:target="banUser"
                            class="flex items-center transition hover:opacity-90 disabled:opacity-50"
                            style="background:#FEE2E2; color:#DC2626; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:500; gap:6px; border:none; cursor:pointer;"
                            aria-label="Заблокировать пользователя"
                            title="Блокировка пользователя"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>
                            </svg>
                            Блок
                        </button>
                    @endif

                    {{-- Закрыть button — runs the canonical CloseTopic flow via closeDialog() --}}
                    @php $isClosed = (bool) $activeBotUser->is_closed; @endphp
                    <button
                        type="button"
                        wire:click="closeDialog"
                        wire:confirm="Закрыть диалог? Пользователю придёт уведомление о закрытии и форма оценки."
                        wire:loading.attr="disabled"
                        wire:target="closeDialog"
                        @disabled($isClosed)
                        class="flex items-center transition hover:opacity-90 disabled:opacity-50 disabled:cursor-default"
                        style="background:#F1F3F5; color:#1A1D26; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:500; gap:6px; border:none; cursor:pointer;"
                        aria-label="Закрыть диалог"
                        title="{{ $isClosed ? 'Диалог уже закрыт' : 'Закрыть тикет' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>
                        </svg>
                        {{ $isClosed ? 'Закрыто' : 'Закрыть' }}
                    </button>
                </div>
            </div>

            {{-- Divider --}}
            <div class="w-full h-px bg-border-light shrink-0"></div>

            {{-- ИНФОРМАЦИЯ section --}}
            {{-- Design: node RWpDk — vertical, gap 14 --}}
            <div class="flex flex-col w-full" style="gap:14px;">

                {{-- Section heading --}}
                {{-- Design: node a2tax7 — 12/600 text-secondary --}}
                <span class="text-text-secondary font-semibold tracking-wider" style="font-size:12px; letter-spacing:0.05em;">ИНФОРМАЦИЯ</span>

                {{-- Info rows — each: icon (16×16, text-secondary) + vertical texts (label 11 text-secondary / value 13 text-primary) --}}

                {{-- ID пользователя — hash icon --}}
                {{-- Design: node x2teWd — gap 10, align center --}}
                <div class="flex items-center w-full" style="gap:10px;">
                    <svg class="shrink-0 text-text-secondary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/>
                    </svg>
                    <div class="flex flex-col" style="gap:2px;">
                        <span class="text-text-secondary" style="font-size:11px;">ID пользователя</span>
                        <span class="text-text-primary font-mono" style="font-size:13px;">{{ $activeBotUser->chat_id }}</span>
                    </div>
                </div>

                {{-- Платформа — send icon --}}
                {{-- Design: node Kc4Ad --}}
                <div class="flex items-center w-full" style="gap:10px;">
                    <svg class="shrink-0 text-text-secondary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
                    </svg>
                    <div class="flex flex-col" style="gap:2px;">
                        <span class="text-text-secondary" style="font-size:11px;">Платформа</span>
                        <span class="text-text-primary" style="font-size:13px;">{{ $rpPlatformLabel }}</span>
                    </div>
                </div>

                {{-- Ссылка на профиль — link icon (only when a profile URL can be built) --}}
                @php $profileUrl = $this->profileUrl(); @endphp
                @if($profileUrl)
                    <div class="flex items-center w-full" style="gap:10px;">
                        <svg class="shrink-0 text-text-secondary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                        </svg>
                        <div class="flex flex-col min-w-0 flex-1" style="gap:2px;">
                            <span class="text-text-secondary" style="font-size:11px;">Ссылка на профиль</span>
                            <a
                                href="{{ $profileUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block truncate text-accent hover:underline"
                                style="font-size:13px; cursor:pointer;"
                                title="{{ $profileUrl }}"
                            >{{ $profileUrl }}</a>
                        </div>
                    </div>
                @endif

                {{-- Первое обращение — calendar icon --}}
                {{-- Design: node u4lnP --}}
                <div class="flex items-center w-full" style="gap:10px;">
                    <svg class="shrink-0 text-text-secondary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>
                    </svg>
                    <div class="flex flex-col" style="gap:2px;">
                        <span class="text-text-secondary" style="font-size:11px;">Первое обращение</span>
                        <span class="text-text-primary" style="font-size:13px;">
                            {{ $activeBotUser->created_at
                                ? \Carbon\Carbon::parse($activeBotUser->created_at)->locale('ru')->isoFormat('D MMMM YYYY')
                                : '—' }}
                        </span>
                    </div>
                </div>

            </div>

            {{-- Divider --}}
            {{-- Design: node GxLQl — #E5E7EB height 1 --}}
            <div class="w-full h-px shrink-0" style="background:#E5E7EB;"></div>

            {{-- МЕДИАФАЙЛЫ section --}}
            {{-- Design: node m1qYAD — vertical gap 12; Media Row: gap 8, thumbs 72×72 rounded-8 --}}
            @php $mediaAttachments = $this->getMediaAttachments() @endphp
            <div class="flex flex-col w-full" style="gap:12px;">

                {{-- Section heading --}}
                {{-- Design: node IUhIo — 12/600 #6B7280 letter-spacing 1 --}}
                <span class="font-semibold" style="font-size:12px; color:#6B7280; letter-spacing:0.07em;">МЕДИАФАЙЛЫ</span>

                {{-- Thumbnail grid: images render as 72×72 thumbs, other files as a file card --}}
                {{-- Design: node D0i1e — gap 8, row; thumbs 72×72 rounded-8 --}}
                @if($mediaAttachments->isNotEmpty())
                    <div class="flex flex-wrap" style="gap:8px;">
                        @foreach($mediaAttachments as $attachment)
                            @php
                                $fileUrl = $activeBotUser->platform === 'telegram'
                                    ? url('/api/files/' . $attachment->file_id)
                                    : $attachment->file_id;
                                $isImage = in_array($attachment->file_type, ['photo', 'sticker']);
                            @endphp
                            @if($isImage)
                                <img
                                    src="{{ $fileUrl }}"
                                    alt="{{ $attachment->file_type }}"
                                    class="object-cover cursor-zoom-in hover:opacity-90 transition"
                                    style="width:72px; height:72px; border-radius:8px; flex-shrink:0;"
                                    loading="lazy"
                                    x-on:click="$dispatch('open-lightbox', { src: '{{ $fileUrl }}' })"
                                >
                            @else
                                <a
                                    href="{{ $fileUrl }}"
                                    target="_blank"
                                    title="{{ $attachment->file_name ?? $attachment->file_type }}"
                                    class="flex flex-col items-center justify-center hover:opacity-90 transition"
                                    style="width:72px; height:72px; border-radius:8px; flex-shrink:0; background:#F3F4F6; padding:8px; text-decoration:none;"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0" style="width:24px; height:24px; color:#6B7280;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <span class="truncate" style="font-size:10px; color:#6B7280; max-width:100%; margin-top:4px;">{{ $attachment->file_name ?? $attachment->file_type }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="text-text-secondary" style="font-size:13px;">Нет файлов</p>
                @endif
            </div>

            </aside>
        </div>
    @endif

</div>
