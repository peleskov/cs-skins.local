<template>
	<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden">
		<div class="container-fluid">

			<div class="row g-4">
				<!-- Sidebar -->
				<div class="col-md-4 col-lg-3 col-xl-2">
					<div class="left-box wow fadeInUp">
						<div class="shop-left-sidebar">
							<!-- Поиск -->
							<div class="search-box">
								<div class="form-input position-relative">
									<input type="text" class="form-control search" placeholder="Поиск кейсов..."
										v-model="filters.search" @input="debouncedSearch">
									<i class="ri-search-line search-icon"></i>
								</div>
							</div>

							<div class="accordion sidebar-accordion" id="accordionPanelsStayOpenExample">
								<!-- Фильтр цены -->
								<div class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapsePrice">
											<span class="dark-text">Цена</span>
										</button>
									</h2>
									<div id="collapsePrice" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<div class="price-range">
												<div class="row g-2">
													<div class="col-6">
														<div class="form-input">
															<input type="number" class="form-control" placeholder="От"
																min="0" v-model="filters.minPrice"
																@change="applyFilters">
														</div>
													</div>
													<div class="col-6">
														<div class="form-input">
															<input type="number" class="form-control" placeholder="До"
																min="0" v-model="filters.maxPrice"
																@change="applyFilters">
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>

								<!-- Фильтр по категориям -->
								<div class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapseCategories">
											<span class="dark-text">Категории</span>
										</button>
									</h2>
									<div id="collapseCategories" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<ul class="category-list custom-padding">
												<li v-for="category in categorizedCases" :key="category.id">
													<a href="#" @click.prevent="toggleCategory(category.id)"
														:class="{ active: filters.categoryIds.includes(category.id) }">
														<div class="form-check ps-0 m-0 category-list-box">
															<div
																class="form-check-label d-flex align-items-center gap-2">
																<img v-if="category.icon"
																	:src="`/storage/${category.icon}`"
																	:alt="category.name"
																	style="width: 20px; height: 20px; object-fit: contain;">
																<span class="name">{{ category.name }}</span>
																<span class="number">({{ category.cases.length
																}})</span>
															</div>
														</div>
													</a>
												</li>
											</ul>
										</div>
									</div>
								</div>

							</div>

							<!-- Кнопка "Что могу открыть" -->
							<div v-if="user && user.balance !== undefined" class="mt-4 pt-3 border-top">
								<button class="btn cart-btn w-100" @click="toggleAffordableFilter()"
									:class="filters.onlyAffordable ? 'theme-btn' : 'theme-outline'">
									<i class="ri-wallet-3-line me-2"></i>
									Что могу открыть
								</button>
							</div>

							<!-- Кнопка очистки фильтров -->
							<div class="mt-3">
								<button class="btn theme-outline cart-btn w-100" @click="clearFilters"
									:disabled="!hasActiveFilters">
									<i class="ri-refresh-line me-2"></i>
									Сбросить фильтры
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Основной контент -->
				<div class="col-md-8 col-lg-9 col-xl-10">
					<!-- Кейсы сгруппированные по категориям -->
					<div v-for="category in filteredCategorizedCases" :key="category.id || 'no-category'" class="mb-5">
						<!-- Заголовок категории -->
						<h2
							class="category-title text-center mb-5 d-flex align-items-center justify-content-center gap-2">
							<img v-if="category.icon" :src="`/storage/${category.icon}`" :alt="category.name"
								class="category-icon" style="width: 32px; height: 32px; object-fit: contain;">
							<span>{{ category.name }}</span>
						</h2>
						<!-- Контейнер для кейсов этой категории -->
						<div class="row g-4 justify-content-center">
							<div v-for="case_item in category.cases" :key="case_item.id"
								class="col-lg-3 col-md-4 col-sm-6">
								<div class="vertical-product-box vertical-case-box">
									<div class="vertical-product-box-img">
										<a :href="`/cases/${case_item.slug}`">
											<img class="product-img-top w-100 bg-img"
												:src="case_item.image_url ? `/storage/${case_item.image_url}` : '/images/case-placeholder.png'"
												:alt="case_item.name" @error="handleImageError">
										</a>
									</div>
									<div class="vertical-product-body">
										<div class="d-flex flex-column mt-sm-3 mt-2 mb-2 text-center">
											<a :href="`/cases/${case_item.slug}`">
												<h4 class="vertical-product-title">{{ case_item.name }}</h4>
											</a>
											<p v-if="case_item.description" class="text-muted small mb-2">
												{{ case_item.description.length > 100 ?
													case_item.description.substring(0, 100) +
													'...' : case_item.description }}
											</p>
										</div>
										<div class="pt-sm-3 pt-2">
											<a :href="`/cases/${case_item.slug}`" class="btn theme-outline w-100" v-html="formatPrice(case_item.price)"></a>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Сообщение если кейсов нет -->
					<div v-if="!cases || cases.length === 0" class="text-center py-5">
						<div class="empty-state">
							<i class="ri-archive-line fs-1 text-muted mb-3"></i>
							<h4>Кейсы не найдены</h4>
							<p class="text-muted">В данный момент нет доступных кейсов для открытия.</p>
						</div>
					</div>

					<!-- Сообщение если ничего не найдено по фильтрам -->
					<div v-else-if="filteredCategorizedCases.length === 0" class="text-center py-5">
						<div class="empty-state">
							<i class="ri-search-line fs-1 text-muted mb-3"></i>
							<h4>Ничего не найдено</h4>
							<p class="text-muted">Попробуйте изменить параметры поиска или фильтры.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
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