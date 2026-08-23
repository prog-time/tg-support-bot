<div class="p-6 lg:p-8">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-text-primary">Основные</h1>
        <p class="mt-1 text-sm text-text-secondary">Общие настройки бота и параметры работы</p>
    </div>

    {{-- Card: Conversation settings — admin only (managers see notifications only) --}}
    @if (auth()->user()?->isAdmin())
    <x-admin.card title="Обращения">
        <form wire:submit="save" novalidate>
            @csrf

            <div class="space-y-5">

                {{-- Group ID --}}
                <x-admin.form-field
                    label="ID группы для приёма сообщений"
                    for="group_id"
                    hint="ID супергруппы Telegram, куда дублируются обращения. Необязательно — оставьте пустым, чтобы работать только в админке."
                    :error="$formErrors['group_id'] ?? null"
                >
                    <input
                        id="group_id"
                        type="text"
                        required
                        wire:model="group_id"
                        maxlength="50"
                        placeholder="-100XXXXXXXXXX"
                        class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 @if (!empty($formErrors['group_id'])) border-red-400 @endif"
                    />
                </x-admin.form-field>

                {{-- Topic name template --}}
                <x-admin.form-field
                    label="Шаблон названия топика"
                    for="template_topic_name"
                    hint="Шаблон имени форум-темы в супергруппе Telegram для нового обращения"
                    :error="$formErrors['template_topic_name'] ?? null"
                >
                    <input
                        id="template_topic_name"
                        type="text"
                        wire:model="template_topic_name"
                        maxlength="255"
                        placeholder="Обращение"
                        class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20 @if (!empty($formErrors['template_topic_name'])) border-red-400 @endif"
                    />
                </x-admin.form-field>

            </div>

            {{-- Action row --}}
            <div class="mt-6 flex justify-end">
                <x-admin.button-primary type="submit">
                    Сохранить
                </x-admin.button-primary>
            </div>

            {{-- Success banner --}}
            @if ($saved)
                <div class="mt-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Настройки сохранены.
                </div>
            @endif

        </form>
    </x-admin.card>
    @endif

    {{-- Card: Notifications & sound — browser-level preferences (no DB) --}}
    <x-admin.card title="Оповещения о новых сообщениях" class="mt-6">
        <div
            x-data="{
            vapidPublicKey: @js($vapidPublicKey),
            notifyPermission: (typeof Notification !== 'undefined' ? Notification.permission : 'unsupported'),
            soundEnabled: (localStorage.getItem('tg-support-sound') !== '0'),
            enableNotifications() {
                if (window.tgSupportSound) { window.tgSupportSound.unlock(); }
                if (typeof Notification === 'undefined') { return; }
                Notification.requestPermission().then(p => {
                    this.notifyPermission = p;
                    if (p === 'granted') { this.subscribePush(); }
                });
            },
            subscribePush() {
                // Push delivery (works even while the device is locked) is a
                // best-effort add-on to the local `Notification` permission
                // above — skip quietly if the SW or VAPID key aren't ready.
                if (!('serviceWorker' in navigator) || !this.vapidPublicKey) { return; }
                navigator.serviceWorker.ready.then((reg) => {
                    if (!reg.pushManager) { return; }
                    reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(this.vapidPublicKey),
                    }).then((sub) => {
                        $wire.savePushSubscription(sub.toJSON());
                    }).catch(() => {});
                });
            },
            setSound(val) {
                this.soundEnabled = val;
                localStorage.setItem('tg-support-sound', val ? '1' : '0');
                if (val) { this.playSound(); }
            },
            playSound() {
                if (window.tgSupportSound) { window.tgSupportSound.unlock(); window.tgSupportSound.playSelected(); }
            }
        }">
            <p class="mb-5 text-sm text-text-secondary">
                Приходят даже при заблокированном экране или закрытом приложении (после установки PWA на главный экран). Настройки сохраняются в этом браузере.
            </p>

            <div class="space-y-5">

                {{-- Browser notifications --}}
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-text-primary">Уведомления в браузере</p>
                        <p class="mt-0.5 text-xs text-text-secondary">Всплывающее уведомление, когда вкладка не в фокусе</p>
                    </div>
                    <div class="shrink-0">
                        <template x-if="notifyPermission === 'granted'">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Включены
                            </span>
                        </template>
                        <template x-if="notifyPermission === 'denied'">
                            <span class="inline-flex items-center rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-600">Заблокированы в браузере</span>
                        </template>
                        <template x-if="notifyPermission === 'default'">
                            <button type="button" x-on:click="enableNotifications()"
                                class="inline-flex items-center justify-center rounded-lg border border-border-light bg-bg-primary px-5 py-2.5 text-sm font-semibold text-text-primary transition hover:bg-bg-secondary">
                                Включить
                            </button>
                        </template>
                        <template x-if="notifyPermission === 'unsupported'">
                            <span class="text-sm text-text-secondary">Не поддерживается браузером</span>
                        </template>
                    </div>
                </div>

                <div class="h-px bg-border-light"></div>

                {{-- Sound --}}
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-text-primary">Звуковой сигнал</p>
                        <p class="mt-0.5 text-xs text-text-secondary">Короткий сигнал при новом сообщении в другом чате</p>
                    </div>
                    <div class="flex items-center gap-4 shrink-0">
                        <button type="button" x-show="soundEnabled" x-on:click="playSound()"
                            class="text-xs font-medium text-accent transition hover:underline">Проверить</button>
                        <label class="inline-flex cursor-pointer items-center">
                            <span class="relative">
                                <input type="checkbox" class="peer sr-only" :checked="soundEnabled" x-on:change="setSound($event.target.checked)">
                                <span class="block h-6 w-11 rounded-full bg-border-light transition peer-checked:bg-accent"></span>
                                <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                            </span>
                        </label>
                    </div>
                </div>

            </div>
        </div>
    </x-admin.card>

</div>
