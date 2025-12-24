<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>
        @if($this->maintenanceMode)
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-danger-100 dark:bg-danger-900">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-danger-600 dark:text-danger-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                </svg>
            </div>
        @else
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-success-100 dark:bg-success-900">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-success-600 dark:text-success-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        @endif

        <div class="fi-account-widget-main">
            <h2 class="fi-account-widget-heading">
                @if($this->maintenanceMode)
                    Режим тех. работ ВКЛЮЧЕН
                @else
                    Сайт работает
                @endif
            </h2>

            <p class="fi-account-widget-user-name">
                @if($this->maintenanceMode)
                    Сайт недоступен для пользователей
                @else
                    Сайт доступен для пользователей
                @endif
            </p>
        </div>

        <x-filament::button
            wire:click="toggleMaintenance"
            :color="$this->maintenanceMode ? 'success' : 'danger'"
            wire:loading.attr="disabled"
            labeled-from="sm"
        >
            @if($this->maintenanceMode)
                Включить сайт
            @else
                Включить тех. работы
            @endif
        </x-filament::button>
    </x-filament::section>
</x-filament-widgets::widget>
