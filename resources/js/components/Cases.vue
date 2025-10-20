<template>
	<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden bg-white">
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
								<!-- Фильтр "Что могу открыть" -->
								<div v-if="user && user.balance !== undefined" class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapseAffordable">
											<span class="dark-text">Доступность</span>
										</button>
									</h2>
									<div id="collapseAffordable" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<ul class="category-list custom-padding">
												<li>
													<a href="#" @click.prevent="toggleAffordableFilter()"
														:class="{ active: filters.onlyAffordable }">
														<div class="form-check ps-0 m-0 category-list-box">
															<div class="form-check-label">
																<span class="name">Что могу открыть</span>
																<span class="number">(<span v-html="formatPrice(user.balance, 'RUB')"></span>)</span>
															</div>
														</div>
													</a>
												</li>
											</ul>
										</div>
									</div>
								</div>

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
							</div>
						</div>
					</div>
				</div>

				<!-- Основной контент -->
				<div class="col-md-8 col-lg-9 col-xl-10">
					<!-- Кейсы сгруппированные по категориям -->
					<div v-for="category in filteredCategorizedCases" :key="category.id || 'no-category'" class="mb-5">
				<!-- Заголовок категории -->
				<h2 class="text-center mb-4">{{ category.name }}</h2>

				<!-- Контейнер для кейсов этой категории -->
				<div class="row g-4 justify-content-center">
					<div v-for="case_item in category.cases" :key="case_item.id" class="col-lg-3 col-md-4 col-sm-6">
						<div class="vertical-product-box">
							<div class="vertical-product-box-img">
								<a :href="`/cases/${case_item.slug}`">
									<img class="product-img-top w-100 bg-img"
										:src="case_item.image_url ? `/storage/${case_item.image_url}` : '/images/case-placeholder.png'"
										:alt="case_item.name" @error="handleImageError">
								</a>
								<div class="offers">
									<div class="d-flex align-items-center justify-content-between">
										<h4 v-html="formatPrice(case_item.price)"></h4>
									</div>
								</div>
							</div>
							<div class="vertical-product-body">
								<div class="d-flex flex-column mt-sm-3 mt-2 mb-2">
									<a :href="`/cases/${case_item.slug}`">
										<h4 class="vertical-product-title">{{ case_item.name }}</h4>
									</a>
									<p v-if="case_item.description" class="text-muted small mb-2">
										{{ case_item.description.length > 100 ? case_item.description.substring(0, 100) +
											'...' : case_item.description }}
									</p>
								</div>
								<div class="pt-sm-3 pt-2">
									<a :href="`/cases/${case_item.slug}`" class="btn theme-btn w-100">
										Открыть кейс
									</a>
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
import { formatPrice } from '../utils/helpers';

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
				onlyAffordable: false
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
				const sortOrder = case_item.category?.sort_order || 999;

				if (!categoriesMap.has(categoryId)) {
					categoriesMap.set(categoryId, {
						id: categoryId,
						name: categoryName,
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
		filteredCategorizedCases() {
			// Применяем фильтры к категоризированным кейсам
			return this.categorizedCases.map(category => {
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

		clearFilters() {
			this.filters.search = '';
			this.filters.minPrice = null;
			this.filters.maxPrice = null;
			this.filters.onlyAffordable = false;
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