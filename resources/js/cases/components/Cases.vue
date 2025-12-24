<template>
	<section>
	</section>
</template>

<script>
import { formatPrice } from '../../shared/utils/helpers';

export default {
	name: 'Cases',
	setup() {
		return { formatPrice };
	},
	props: {
		initialCases: {
			type: Array,
			default: () => []
		},
		user: {
			type: Object,
			default: null
		}
	},
	data() {
		return {
			cases: this.initialCases || [],
			filters: {
				search: '',
				minPrice: null,
				maxPrice: null,
				onlyAffordable: false,
				categoryIds: []
			},
			searchTimeout: null
		};
	},
	computed: {
		categorizedCases() {
			// Группируем кейсы по категориям
			const categoriesMap = new Map();

			this.cases.forEach(case_item => {
				const categoryId = case_item.category_id;
				const categoryName = case_item.category?.name || 'Кейсы';
				const categoryIcon = case_item.category?.icon || null;
				const sortOrder = case_item.category?.sort_order || 999;

				if (!categoriesMap.has(categoryId)) {
					categoriesMap.set(categoryId, {
						id: categoryId,
						name: categoryName,
						icon: categoryIcon,
						sort_order: sortOrder,
						cases: []
					});
				}

				categoriesMap.get(categoryId).cases.push(case_item);
			});

			// Преобразуем Map в массив и сортируем по sort_order
			return Array.from(categoriesMap.values())
				.sort((a, b) => a.sort_order - b.sort_order);
		},
		hasActiveFilters() {
			return !!(
				this.filters.search ||
				this.filters.minPrice ||
				this.filters.maxPrice ||
				this.filters.onlyAffordable ||
				this.filters.categoryIds.length > 0
			);
		},
		filteredCategorizedCases() {
			// Применяем фильтры к категоризированным кейсам
			return this.categorizedCases
				.filter(category => {
					// Фильтр по категориям (если выбраны)
					if (this.filters.categoryIds.length > 0 && !this.filters.categoryIds.includes(category.id)) {
						return false;
					}
					return true;
				})
				.map(category => {
					const filteredCases = category.cases.filter(case_item => {
						// Фильтр по поиску
						if (this.filters.search && this.filters.search.trim() !== '') {
							const searchTerm = this.filters.search.toLowerCase().trim();
							const nameMatch = case_item.name.toLowerCase().includes(searchTerm);
							const descMatch = case_item.description && case_item.description.toLowerCase().includes(searchTerm);
							if (!nameMatch && !descMatch) {
								return false;
							}
						}

						// Фильтр по минимальной цене
						if (this.filters.minPrice && case_item.price < this.filters.minPrice) {
							return false;
						}

						// Фильтр по максимальной цене
						if (this.filters.maxPrice && case_item.price > this.filters.maxPrice) {
							return false;
						}

						// Фильтр "Что могу открыть" (только для авторизованных пользователей)
						if (this.filters.onlyAffordable && this.user && this.user.balance !== undefined) {
							const casePrice = parseFloat(case_item.price);
							const userBalance = parseFloat(this.user.balance);
							if (casePrice > userBalance) {
								return false;
							}
						}

						return true;
					});

					return {
						...category,
						cases: filteredCases
					};
				}).filter(category => category.cases.length > 0); // Убираем пустые категории
		}
	},
	methods: {
		handleImageError(event) {
			event.target.src = '/images/case-placeholder.png';
		},

		handleCurrencyChange() {
			// Принудительно обновляем данные для пересчета цен
			if (this.cases.length > 0) {
				this.cases = [...this.cases];
			}
		},

		applyFilters() {
			// Метод вызывается при изменении фильтров цены
			// Фильтрация происходит автоматически через computed property
		},

		debouncedSearch() {
			// Отменяем предыдущий таймер
			if (this.searchTimeout) {
				clearTimeout(this.searchTimeout);
			}

			// Устанавливаем новый таймер для задержки поиска
			this.searchTimeout = setTimeout(() => {
				// Поиск происходит автоматически через computed property
				// Этот метод нужен только для debounce функциональности
			}, 300);
		},

		toggleAffordableFilter() {
			this.filters.onlyAffordable = !this.filters.onlyAffordable;
		},

		toggleCategory(categoryId) {
			const index = this.filters.categoryIds.indexOf(categoryId);
			if (index === -1) {
				this.filters.categoryIds.push(categoryId);
			} else {
				this.filters.categoryIds.splice(index, 1);
			}
		},

		clearFilters() {
			this.filters.search = '';
			this.filters.minPrice = null;
			this.filters.maxPrice = null;
			this.filters.onlyAffordable = false;
			this.filters.categoryIds = [];
		}
	},
	mounted() {
		console.log('Cases component mounted with', this.cases.length, 'cases');

		// Слушаем события смены валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);
	},

	beforeUnmount() {
		// Убираем слушатели при размонтировании
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
	}
}
</script>