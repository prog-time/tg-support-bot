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
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 space-y-3 w-1/2">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Контактная информация</p>
                    @if($botUser->platform === 'vk')
                        <a href="https://vk.com/id{{ $botUser->chat_id }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 ring-1 ring-indigo-300 dark:ring-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-950 transition-colors">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            Перейти на профиль
                        </a>
                    @elseif($botUser->platform === 'telegram' && $telegramUsername)
                        <a href="https://t.me/{{ $telegramUsername }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 ring-1 ring-blue-300 dark:ring-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950 transition-colors">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            Перейти на профиль
                        </a>
                    @endif
                </div>

                @php
                    $externalUser = ($botUser->platform !== 'telegram' && $botUser->platform !== 'vk')
                        ? $botUser->externalUser
                        : null;
                @endphp

                <ul class="space-y-1 text-sm">
                    @php
                        $platformColor = $botUser->platform === 'telegram'
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                            : ($botUser->platform === 'vk'
                                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300'
                                : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300');
                    @endphp

                    <li class="flex items-baseline gap-1">
                        <span class="shrink-0 text-gray-500 dark:text-gray-400">Источник</span>
                        <span class="flex-1 border-b border-dotted border-gray-300 dark:border-gray-600 mx-1 mb-0.5"></span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium {{ $platformColor }}">{{ ucfirst($botUser->platform) }}</span>
                    </li>

                    <li class="flex items-baseline gap-1">
                        <span class="shrink-0 text-gray-500 dark:text-gray-400">ID</span>
                        <span class="flex-1 border-b border-dotted border-gray-300 dark:border-gray-600 mx-1 mb-0.5"></span>
                        <span class="font-mono text-gray-900 dark:text-white">{{ $botUser->chat_id }}</span>
                    </li>

                    @if($botUser->platform === 'telegram' && $botUser->topic_id)
                        <li class="flex items-baseline gap-1">
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">Topic ID</span>
                            <span class="flex-1 border-b border-dotted border-gray-300 dark:border-gray-600 mx-1 mb-0.5"></span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ $botUser->topic_id }}</span>
                        </li>
                    @endif

                    @if($externalUser)
                        <li class="flex items-baseline gap-1">
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">External ID</span>
                            <span class="flex-1 border-b border-dotted border-gray-300 dark:border-gray-600 mx-1 mb-0.5"></span>
                            <span class="font-mono text-gray-900 dark:text-white">{{ $externalUser->external_id }}</span>
                        </li>
                        <li class="flex items-baseline gap-1">
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">Название источника</span>
                            <span class="flex-1 border-b border-dotted border-gray-300 dark:border-gray-600 mx-1 mb-0.5"></span>
                            <span class="text-gray-900 dark:text-white">{{ $externalUser->source }}</span>
                        </li>
                    @endif

                    <li class="flex items-baseline gap-1">
                        <span class="shrink-0 text-gray-500 dark:text-gray-400">Статус</span>
                        <span class="flex-1 border-b border-dotted border-gray-300 dark:border-gray-600 mx-1 mb-0.5"></span>
                        @if($botUser->isBanned())
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
                                Заблокирован@if($botUser->banned_at) · {{ \Carbon\Carbon::parse($botUser->banned_at)->format('d.m.Y') }}@endif
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">Активен</span>
                        @endif
                    </li>

                    <li class="flex items-baseline gap-1">
                        <span class="shrink-0 text-gray-500 dark:text-gray-400">Зарегистрирован</span>
                        <span class="flex-1 border-b border-dotted border-gray-300 dark:border-gray-600 mx-1 mb-0.5"></span>
                        <span class="text-gray-900 dark:text-white">{{ $botUser->created_at?->format('d.m.Y H:i') }}</span>
                    </li>
                </ul>
            </div>

            <div
                class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 overflow-y-auto space-y-3"
                style="height: 500px; max-height: 90vh;"
                x-data
                x-init="$el.scrollTop = $el.scrollHeight"
                x-on:messages-updated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
            >
                @forelse($messages as $message)
                    @php
                        $msgDate   = $message->created_at?->toDateString();
                        $prevDate  = $loop->first ? null : $messages[$loop->index - 1]->created_at?->toDateString();
                        $today     = \Carbon\Carbon::today()->toDateString();
                        $yesterday = \Carbon\Carbon::yesterday()->toDateString();

                        $showDateSeparator = $loop->first || $msgDate !== $prevDate;

                        if ($showDateSeparator && $msgDate) {
                            if ($msgDate === $today) {
                                $dateLabel = 'Сегодня';
                            } elseif ($msgDate === $yesterday) {
                                $dateLabel = 'Вчера';
                            } else {
                                $dateLabel = \Carbon\Carbon::parse($msgDate)
                                    ->locale('ru')
                                    ->isoFormat('D MMMM YYYY');
                            }
                        }
                    @endphp

                    @if($showDateSeparator && $msgDate)
                        <div class="flex items-center gap-3 py-1">
                            <span class="flex-1 border-t border-gray-200 dark:border-gray-700"></span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $dateLabel }}</span>
                            <span class="flex-1 border-t border-gray-200 dark:border-gray-700"></span>
                        </div>
                    @endif

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
                                {{ $message->created_at?->format('H:i') }}
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
                        <textarea
                            wire:model="replyText"
                            rows="3"
                            placeholder="Введите сообщение..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        ></textarea>
                        @error('replyText')
                            <p class="text-xs text-red-500">{{ $message }}</p>
                        @enderror

                        <div x-data="{ fileName: null }" class="flex items-center justify-between gap-3">
                            <div class="flex flex-col">
                                <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                                    <x-heroicon-o-paper-clip class="w-4 h-4 shrink-0" />
                                    <span x-text="fileName ?? 'Прикрепить файл'"></span>
                                    <input
                                        type="file"
                                        wire:model="attachment"
                                        x-on:change="fileName = $event.target.files[0]?.name ?? null"
                                        class="sr-only"
                                    >
                                </label>
                                <div wire:loading wire:target="attachment" class="text-xs text-gray-400 mt-1">Загрузка...</div>
                                @error('attachment')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled" wire:target="sendReply,attachment">
                                Отправить
                            </x-filament::button>
                        </div>
                    </form>
                </div>
            @endif
        @else
            <p class="text-center text-sm text-gray-400">Диалог не найден</p>
        @endif
    </div>
</x-filament-panels::page>
