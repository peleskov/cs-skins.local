<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow ring-1 ring-gray-950/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">Реальный онлайн (сейчас)</div>
            <div class="text-3xl font-bold text-primary-600">{{ $this->getRealOnline() }}</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow ring-1 ring-gray-950/5">
            <div class="text-sm text-gray-500 dark:text-gray-400">Отображается на сайте</div>
            <div class="text-3xl font-bold text-success-600">{{ $this->getCurrentOnline() }}</div>
        </div>
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex justify-end mt-6">
            <x-filament::button type="submit">Сохранить</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
