<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Фильтр</x-slot>
        {{ $this->filterForm }}
    </x-filament::section>

    @forelse ($partners as $row)
        <x-filament::section
            :collapsible="true"
            :collapsed="false"
        >
            <x-slot name="heading">
                {{ $row['partner']->email }}
            </x-slot>

            <x-slot name="description">
                ID: {{ $row['partner']->id }}
            </x-slot>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Переходов</div>
                    <div class="text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($row['referrals']) }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Активаций</div>
                    <div class="text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($row['activations']) }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Сумма пополнений</div>
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                        {{ number_format($row['total_deposits'], 0, '.', ' ') }} ₽
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Выдано бонусов</div>
                    <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                        {{ number_format($row['total_bonus'], 0, '.', ' ') }} ₽
                    </div>
                </div>
            </div>

            @if ($row['promocodes']->isNotEmpty())
                <div class="mt-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Промокоды партнёра</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($row['promocodes'] as $promo)
                            <x-filament::badge :color="$promo->is_active ? 'success' : 'gray'">
                                <span class="font-mono">{{ $promo->code }}</span>
                                <span class="opacity-60 ml-1">×{{ $promo->used_count }}</span>
                            </x-filament::badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <div class="text-center text-gray-500 dark:text-gray-400 py-6">
                У вас нет привязанных партнёров. Обратитесь к администратору.
            </div>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
