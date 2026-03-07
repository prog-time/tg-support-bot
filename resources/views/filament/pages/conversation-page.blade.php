<x-filament-panels::page>
    <div
        class="space-y-4"
        x-data="{ lightboxSrc: '', lightboxOpen: false }"
        x-on:open-lightbox.window="lightboxSrc = $event.detail.src; lightboxOpen = true"
    >
        {{-- Lightbox --}}
        <template x-teleport="body">
            <div
                x-on:click="lightboxOpen = false"
                x-on:keydown.escape.window="lightboxOpen = false"
                class="fixed inset-0 z-[100000] flex items-center justify-center cursor-zoom-out"
                :class="lightboxOpen ? 'pointer-events-auto' : 'pointer-events-none'"
                :style="{ opacity: lightboxOpen ? 1 : 0, transition: 'opacity 300ms ease' }"
                style="opacity: 0;"
            >
                <div class="absolute inset-0 bg-black" style="opacity: 0.85;"></div>
                <button
                    x-on:click.stop="lightboxOpen = false"
                    class="fixed top-16 right-4 text-white text-4xl leading-none opacity-80 hover:opacity-100 bg-transparent border-none cursor-pointer z-[100001]"
                    aria-label="Закрыть"
                >&times;</button>
                <img
                    :src="lightboxSrc"
                    x-on:click.stop
                    class="relative z-[100001] object-contain rounded-lg shadow-2xl cursor-default block"
                    style="max-width: min(85vw, 960px); max-height: 80vh;"
                    alt="Просмотр изображения"
                >
            </div>
        </template>
        @if($botUser)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <div class="flex items-center gap-3">
                    <div class="text-sm text-gray-500">
                        <span class="font-medium text-gray-900 dark:text-white">Chat ID: {{ $botUser->chat_id }}</span>
                        &nbsp;·&nbsp;
                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                            {{ $botUser->platform === 'telegram' ? 'bg-blue-100 text-blue-700' : ($botUser->platform === 'vk' ? 'bg-indigo-100 text-indigo-700' : 'bg-yellow-100 text-yellow-700') }}">
                            {{ ucfirst($botUser->platform) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 min-h-64 max-h-[32rem] overflow-y-auto space-y-3">
                @forelse($chatMessages as $message)
                    <div class="flex {{ $message->message_type === 'outgoing' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[70%] rounded-xl px-4 py-2 text-sm
                            {{ $message->message_type === 'outgoing'
                                ? 'bg-primary-500 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white' }}">
                            @php $messageText = $message->text ?? $message->externalMessage?->text @endphp
                            @if($messageText)
                                <p>{{ $messageText }}</p>
                            @endif
                            @if($message->attachments->isNotEmpty())
                                <x-message-attachments
                                    :attachments="$message->attachments"
                                    :platform="$message->platform"
                                    :is-outgoing="$message->message_type === 'outgoing'"
                                />
                            @elseif(!$messageText)
                                <p class="text-xs opacity-60 italic">{{ $message->platform }} · {{ $message->message_type }}</p>
                            @endif
                            <p class="text-xs opacity-60 mt-1">
                                {{ $message->created_at?->format('d.m.Y H:i') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-sm text-gray-400">Нет сообщений</p>
                @endforelse
            </div>

            @if($this->shouldShowReplyForm())
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                    <form wire:submit="sendReply" class="space-y-3">
                        {{ $this->form }}
                        <div class="flex justify-end">
                            <x-filament::button type="submit" icon="heroicon-m-paper-airplane">
                                Отправить
                            </x-filament::button>
                        </div>
                    </form>
                    <x-filament-actions::modals />
                </div>
            @endif
        @else
            <p class="text-center text-sm text-gray-400">Диалог не найден</p>
        @endif
    </div>
</x-filament-panels::page>
