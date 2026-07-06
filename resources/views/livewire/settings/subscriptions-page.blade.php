<div class="p-6 lg:p-8">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-text-primary">Подписки</h1>
        <p class="mt-1 text-sm text-text-secondary">Лицензионный ключ и доступные по нему модули</p>
    </div>

    {{-- Card: license key --}}
    <x-admin.card title="Лицензионный ключ">
        <form wire:submit="check" novalidate>
            @csrf

            <x-admin.form-field
                label="Лицензионный ключ"
                for="license_key"
                hint="Один ключ распространяется на все платные модули. Хранится в зашифрованном виде."
            >
                <input
                    id="license_key"
                    type="password"
                    autocomplete="off"
                    wire:model="license_key"
                    placeholder="{{ $hasKey ? '•••••••••• (ключ сохранён)' : 'Вставьте лицензионный ключ' }}"
                    class="block w-full rounded-lg border border-border-light bg-bg-input px-3.5 py-2.5 text-sm text-text-primary placeholder-text-secondary outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"
                />
            </x-admin.form-field>

            <div class="mt-6 flex items-center justify-end">
                <x-admin.button-primary type="submit" wire:loading.attr="disabled" wire:target="check">
                    <span wire:loading.remove wire:target="check">Проверить</span>
                    <span wire:loading wire:target="check">Проверка...</span>
                </x-admin.button-primary>
            </div>

            {{-- Error notice --}}
            @if ($errorMessage)
                <div class="mt-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-red-500"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M6.938 19h10.124A2 2 0 0019 16.27L13.938 7A2 2 0 0010.062 7L5 16.27A2 2 0 006.938 19z" />
                    </svg>
                    {{ $errorMessage }}
                </div>
            @endif

        </form>
    </x-admin.card>

    {{-- Card: modules granted by the key --}}
    @if ($checked)
    <x-admin.card title="Доступные модули" class="mt-6">

        @if (count($products) === 0)
            <p class="text-sm text-text-secondary">По этому ключу нет доступных модулей.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border-light text-left text-xs font-semibold uppercase tracking-wider text-text-secondary">
                            <th class="pb-3 pr-4 font-semibold">Модуль</th>
                            <th class="pb-3 pr-4 font-semibold">Дата окончания</th>
                            <th class="pb-3 font-semibold">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php
                                $status = $product['status'];
                                [$statusLabel, $statusClasses] = match ($status) {
                                    'active', 'grace' => ['Активна', 'bg-green-50 text-green-700'],
                                    'trial' => ['Пробная', 'bg-blue-50 text-blue-700'],
                                    'expired' => ['Истекла', 'bg-red-50 text-red-700'],
                                    'inactive', 'invalid' => ['Неактивна', 'bg-red-50 text-red-700'],
                                    default => [$status, 'bg-bg-input text-text-secondary'],
                                };
                            @endphp
                            <tr class="border-b border-border-light last:border-0">
                                <td class="py-3 pr-4 font-medium text-text-primary">{{ $product['name'] }}</td>
                                <td class="py-3 pr-4 text-text-secondary">{{ $product['valid_until'] ?? 'бессрочно' }}</td>
                                <td class="py-3">
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </x-admin.card>
    @endif

</div>
