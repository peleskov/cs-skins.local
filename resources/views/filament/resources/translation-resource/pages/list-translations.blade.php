<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Поиск и фильтры --}}
        <div class="flex gap-4">
            <div class="flex-1">
                <input
                    type="text"
                    wire:model.live.debounce.500ms="search"
                    placeholder="Поиск по ключам и значениям..."
                    class="fi-input block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm transition duration-75 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:focus:border-primary-500"
                />
            </div>
            <div class="w-48">
                <select
                    wire:model.live="filterGroup"
                    class="fi-select block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm transition duration-75 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:focus:border-primary-500"
                >
                    <option value="">Все группы</option>
                    @foreach($this->getGroups() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Таблица переводов --}}
        <div class="fi-ta-content relative divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-left">Группа</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-left">Ключ</th>
                        @foreach($this->getLocales() as $locale)
                            <th class="fi-ta-header-cell px-3 py-3.5 text-left">{{ strtoupper($locale) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse($this->getPaginatedTranslations() as $translation)
                        <tr class="fi-ta-row hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="fi-ta-cell px-3 py-4">
                                <span class="text-sm">{{ $translation['group'] }}</span>
                            </td>
                            <td class="fi-ta-cell px-3 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-mono">{{ $translation['key'] }}</span>
                                    <button
                                        type="button"
                                        onclick="navigator.clipboard.writeText('{{ $translation['id'] }}')"
                                        class="text-gray-400 hover:text-gray-600"
                                        title="Копировать полный ключ"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            @foreach($this->getLocales() as $locale)
                                <td class="fi-ta-cell px-3 py-4">
                                    @if(isset($editingCell['id']) && $editingCell['id'] === $translation['id'] && isset($editingCell['locale']) && $editingCell['locale'] === $locale)
                                        <div class="flex gap-1">
                                            <textarea
                                                wire:model.defer="editingValue"
                                                wire:keydown.escape="cancelEdit"
                                                wire:keydown.enter.prevent="saveTranslation"
                                                class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                                                autofocus
                                            ></textarea>
                                            <button
                                                wire:click="saveTranslation"
                                                class="text-green-600 hover:text-green-800"
                                                type="button"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </button>
                                            <button
                                                wire:click="cancelEdit"
                                                class="text-red-600 hover:text-red-800"
                                                type="button"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <div
                                            wire:click="startEdit('{{ $translation['id'] }}', '{{ $locale }}')"
                                            class="cursor-pointer hover:bg-gray-100 dark:hover:bg-white/10 rounded px-2 py-1 -mx-2 -my-1"
                                        >
                                            <span class="text-sm">{{ $translation[$locale] ?? '' }}</span>
                                            @if(empty($translation[$locale]))
                                                <span class="text-gray-400 italic text-xs">Пусто</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 2 + count($this->getLocales()) }}" class="text-center py-8 text-gray-500">
                                Переводы не найдены
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Пагинация --}}
        @if($this->getTotalPages() > 1)
            <div class="mt-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button
                        wire:click="previousPage"
                        @disabled($currentPage === 1)
                        class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Назад
                    </button>

                    @php
                        $totalPages = $this->getTotalPages();
                        $start = max(1, $currentPage - 2);
                        $end = min($totalPages, $currentPage + 2);
                    @endphp

                    @for($i = $start; $i <= $end; $i++)
                        <button
                            wire:click="goToPage({{ $i }})"
                            @class([
                                'px-3 py-2 text-sm font-medium rounded-md',
                                'bg-primary-600 text-white' => $i === $currentPage,
                                'text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' => $i !== $currentPage,
                            ])
                        >
                            {{ $i }}
                        </button>
                    @endfor

                    <button
                        wire:click="nextPage"
                        @disabled($currentPage === $this->getTotalPages())
                        class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Вперед
                    </button>
                </div>

                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Страница {{ $currentPage }} из {{ $this->getTotalPages() }}
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>