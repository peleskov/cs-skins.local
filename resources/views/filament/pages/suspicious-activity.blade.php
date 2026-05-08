<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Параметры</x-slot>
        {{ $this->filterForm }}
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
