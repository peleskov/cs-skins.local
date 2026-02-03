<template>
	<div class="container-fluid flex-fill d-flex flex-column g-0">
		<div class="row g-0 flex-fill">
			<aside class="sidebar align-self-stretch" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
				<div class="d-flex gap-4">
					<div class="search-box mb-4">
						<input type="text" class="form-control" placeholder="Поиск кейсов..." v-model="filters.search"
							@input="debouncedSearch">
					</div>
					<button class="sidebar-toggle d-xl-none" @click="toggleSidebar"
						:title="sidebarCollapsed ? 'Показать фильтры' : 'Скрыть фильтры'"></button>
				</div>
				<div class="price-filter mb-4">
					<h2 class="text-white">Цена</h2>
					<div class="price-inputs d-flex gap-2 mt-2">
						<input type="number" class="form-control" placeholder="От" :min="priceMin" :max="priceMax"
							v-model.number="priceRange[0]" @change="onInputChange">
						<input type="number" class="form-control" placeholder="До" :min="priceMin" :max="priceMax"
							v-model.number="priceRange[1]" @change="onInputChange">
					</div>
					<Slider v-model="priceRange" :min="priceMin" :max="priceMax" :tooltips="true" :lazy="false"
						@update="onPriceChange" />
				</div>
				<div class="accordion mb-5">
					<div>
						<button class="accordion-button" type="button" data-bs-toggle="collapse"
							data-bs-target="#collapseCategories">Категории</button>
						<div id="collapseCategories" class="accordion-collapse collapse show">
							<div class="accordion-body">
								<ul class="category-list">
									<li v-for="category in categorizedCases" :key="category.id">
										<div class="form-check">
											<input type="checkbox" class="form-check-input"
												:id="`category-${category.id}`"
												:checked="filters.categoryIds.includes(category.id)"
												@change="toggleCategory(category.id)">
											<label class="form-check-label" :for="`category-${category.id}`">{{
												category.name }}</label>
										</div>
									</li>
								</ul>
							</div>
						</div>
					</div>
				</div>
				<div v-if="user && user.balance !== undefined" class="">
					<button class="w-100 btn btn-secondary mb-3" @click="toggleAffordableFilter()">
						Доступно по балансу
					</button>
				</div>
				<div>
					<button class="w-100 btn btn-primary" @click="clearFilters" :disabled="!hasActiveFilters">
						Очистить фильтр
					</button>
				</div>
			</aside>
			<div class="category-list col mb-5" :class="{ 'mt-n1': sidebarCollapsed }">
				<button v-if="sidebarCollapsed" class="sidebar-toggle d-xl-none" @click="toggleSidebar"
					title="Показать фильтры">Фильтр</button>
				<div class=" d-flex flex-column gap-5">
					<div v-for="category in filteredCategorizedCases" :key="category.id || 'no-category'" class="px-4">
						<h2
							class="category-title text-center mb-5 d-flex align-items-center justify-content-center gap-2">
							<img v-if="category.icon" :src="`/storage/${category.icon}`" :alt="category.name"
								class="category-icon" style="width: 32px; height: 32px; object-fit: contain;">
							<span>{{ category.name }}</span>
						</h2>
						<div class="row g-5 justify-content-center align-items-stretch">
							<div v-for="case_item in category.cases" :key="case_item.id"
								:class="sidebarCollapsed ? 'col-lg-4 col-xl-3' : 'col-6 col-lg-4 col-xl-2'">
								<div class="category-case-box d-flex flex-column align-items-center h-100">
									<!-- Бейджи -->
									<div class="case-badges" v-if="case_item.label_hot || case_item.label_new || case_item.label_limited">
										<span v-if="case_item.label_hot" class="case-badge case-badge-hot">
											<i class="case-badge-icon case-badge-icon-hot"></i>
											<span>HOT</span>
										</span>
										<span v-if="case_item.label_new" class="case-badge case-badge-new">
											<i class="case-badge-icon case-badge-icon-new"></i>
											<span>NEW</span>
										</span>
										<span v-if="case_item.label_limited" class="case-badge case-badge-limited">
											<i class="case-badge-icon case-badge-icon-limited"></i>
											<span>LIMITED</span>
										</span>
									</div>
									<a :href="`/cases/${case_item.slug}`"
										class="d-flex justify-content-center align-items-center image-box">
										<img :src="case_item.image_url ? `/storage/${case_item.image_url}` : '/images/case-placeholder.png'"
											:alt="case_item.name" @error="handleImageError">
									</a>
									<a :href="`/cases/${case_item.slug}`" class="mb-4 flex-fill">
										<h3 class="text-white text-center">{{ case_item.name }}</h3>
									</a>
									<a :href="`/cases/${case_item.slug}`" class="btn btn-quaternary"
										v-html="formatPrice(case_item.price)"></a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { formatPrice } from '../../shared/utils/helpers';
import Slider from '@vueform/slider';

export default {
	name: 'Cases',
	components: { Slider },
	setup() {
		return { formatPrice };
	},
	props: {
		cases: {
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
			casesList: this.cases || [],
			priceRange: [0, 10000],
			filters: {
				search: '',
				minPrice: null,
				maxPrice: null,
				onlyAffordable: false,
				categoryIds: []
			},
			searchTimeout: null,
			sidebarCollapsed: false
		};
	},
	computed: {
		priceMin() {
			if (!this.casesList.length) return 0;
			return Math.floor(Math.min(...this.casesList.map(c => parseFloat(c.price))));
		},
		priceMax() {
			if (!this.casesList.length) return 10000;
			return Math.ceil(Math.max(...this.casesList.map(c => parseFloat(c.price))));
		},
		categorizedCases() {
			// Группируем кейсы по категориям
			const categoriesMap = new Map();

			this.casesList.forEach(case_item => {
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
			if (this.casesList.length > 0) {
				this.casesList = [...this.casesList];
			}
		},

		applyFilters() {
			// Метод вызывается при изменении фильтров цены
			// Фильтрация происходит автоматически через computed property
		},

		onPriceChange(value) {
			this.filters.minPrice = value[0];
			this.filters.maxPrice = value[1];
		},

		onInputChange() {
			// Обновляем priceRange для синхронизации со слайдером
			this.priceRange = [...this.priceRange];
			this.filters.minPrice = this.priceRange[0];
			this.filters.maxPrice = this.priceRange[1];
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
		},

		toggleSidebar() {
			this.sidebarCollapsed = !this.sidebarCollapsed;
		}
	},
	mounted() {

		// Инициализируем priceRange
		this.priceRange = [this.priceMin, this.priceMax];

		// Слушаем события смены валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);
	},

	beforeUnmount() {
		// Убираем слушатели при размонтировании
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
	}
}
</script>