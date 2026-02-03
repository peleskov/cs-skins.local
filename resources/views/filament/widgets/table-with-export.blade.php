<x-filament-widgets::widget class="fi-wi-table">
    {{ $this->table }}

    <div class="fi-ta-actions" style="padding: 1rem;">
        {{ $this->exportAction }}
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
