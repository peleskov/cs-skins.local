<template>
	<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden bg-white">
		<div class="container-fluid">
			<div class="title text-center d-none d-lg-block">
				<h2>{{ translate('marketplace.title') }}</h2>
				<div class="loader-line" style="left: calc(50% - 40px);"></div>
				<div class="sub-title">
					<p>{{ translate('marketplace.subtitle') }}</p>
				</div>
			</div>

			<div class="row g-4">
				<!-- Sidebar (desktop only, скрыт на мобиле через CSS) -->
				<div class="col-md-4 col-lg-3 col-xl-2 mp-filter-sidebar">
					<div class="left-box wow fadeInUp">
						<div class="shop-left-sidebar">
							<!-- Карточка продавца -->
							<div v-if="seller && sellerStats" class="mb-3">
								<div class="accordion-body"
									style="background: var(--bs-light); border-radius: 8px; padding: 1rem;">
									<div class="text-center mb-3">
										<img :src="seller.steam_avatar || '/images/default-avatar.png'"
											:alt="seller.name" class="rounded-circle mb-2"
											style="width: 50px; height: 50px; object-fit: cover;">
										<h6 class="mb-0">{{ seller.name }}</h6>
									</div>
									<ul class="category-list custom-padding">
										<li>
											<div class="d-flex justify-content-between">
												<span class="name">{{ translate('marketplace.seller_listings') }}</span>
												<span class="number">({{ sellerStats.total_listings }})</span>
											</div>
										</li>
										<li>
											<div class="d-flex justify-content-between">
												<span class="name">{{ translate('marketplace.seller_sales') }}</span>
												<span class="number">({{ sellerStats.total_sales }})</span>
											</div>
										</li>
										<li>
											<div class="d-flex justify-content-between">
												<span class="name">{{ translate('marketplace.seller_purchases')
												}}</span>
												<span class="number">({{ sellerStats.total_purchases }})</span>
											</div>
										</li>
									</ul>
									<div class="mt-3">
										<a href="/marketplace" class="btn theme-outline w-100">
											<i class="ri-store-line"></i>
											{{ translate('marketplace.marketplace_link') }}
										</a>
									</div>
								</div>
							</div>

							<!-- Поиск -->
							<div v-if="!seller" class="search-box">
								<div class="form-input position-relative">
									<input type="text" class="form-control search"
										:placeholder="translate('marketplace.search_placeholder')"
										v-model="filters.search" @input="debouncedSearch">
									<i class="ri-search-line search-icon"></i>
								</div>
							</div>

							<div v-if="!seller" class="accordion sidebar-accordion" id="accordionPanelsStayOpenExample">
								<!-- Фильтр цены -->
								<div class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapsePrice">
											<span class="dark-text">{{ translate('marketplace.price') }}</span>
										</button>
									</h2>
									<div id="collapsePrice" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<div class="price-range">
												<div class="row g-2">
													<div class="col-6">
														<div class="form-input">
															<input type="number" class="form-control"
																:placeholder="translate('marketplace.price_min')"
																min="0" v-model="filters.minPrice"
																@change="applyFilters">
														</div>
													</div>
													<div class="col-6">
														<div class="form-input">
															<input type="number" class="form-control"
																:placeholder="translate('marketplace.price_max')"
																min="0" v-model="filters.maxPrice"
																@change="applyFilters">
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>

								<!-- Категории -->
								<div class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapseOne">
											<span class="dark-text">{{ translate('marketplace.categories') }}</span>
										</button>
									</h2>
									<div id="collapseOne" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<ul class="category-list custom-padding custom-height scroll-bar">
												<li v-for="category in categories" :key="category.type">
													<a href="#" @click.prevent="toggleCategory(category.type)"
														:class="{ active: filters.types === category.type }">
														<div class="form-check ps-0 m-0 category-list-box">
															<div class="form-check-label">
																<span class="name">{{ category.name }}</span>
																<span class="number">({{ category.count }})</span>
															</div>
														</div>
													</a>
												</li>
											</ul>
										</div>
									</div>
								</div>

								<!-- Качество (Износ) -->
								<div class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapseQuality">
											<span class="dark-text">{{ translate('marketplace.quality') }}</span>
										</button>
									</h2>
									<div id="collapseQuality" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<ul class="category-list custom-padding custom-height scroll-bar">
												<li v-for="quality in qualityOptions" :key="quality.value">
													<a href="#" @click.prevent="toggleQuality(quality.value)"
														:class="{ active: filters.wearConditions[quality.value] }">
														<div class="form-check ps-0 m-0 category-list-box">
															<div class="form-check-label">
																<span class="name">{{ translate('tags.values.' +
																	quality.value) }}</span>
															</div>
														</div>
													</a>
												</li>
											</ul>
										</div>
									</div>
								</div>

								<!-- Раритетность -->
								<div class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapseRarity">
											<span class="dark-text">{{ translate('marketplace.rarity') }}</span>
										</button>
									</h2>
									<div id="collapseRarity" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<ul class="category-list custom-padding custom-height scroll-bar">
												<li v-for="rarity in rarityOptions" :key="rarity.value">
													<a href="#" @click.prevent="toggleRarity(rarity.value)"
														:class="{ active: filters.rarities[rarity.value] }">
														<div class="form-check ps-0 m-0 category-list-box">
															<div class="form-check-label">
																<span class="name">{{ translate('tags.values.' +
																	rarity.value) }}</span>
															</div>
														</div>
													</a>
												</li>
											</ul>
										</div>
									</div>
								</div>

								<!-- Float Range -->
								<div class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapseFloat">
											<span class="dark-text">{{ translate('marketplace.float_range') }}</span>
										</button>
									</h2>
									<div id="collapseFloat" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<div class="float-range">
												<div class="row g-2">
													<div class="col-6">
														<div class="form-input">
															<input type="number" class="form-control"
																:placeholder="translate('marketplace.price_min')"
																min="0" max="1" step="0.001" v-model="filters.minFloat"
																@change="applyFilters">
														</div>
													</div>
													<div class="col-6">
														<div class="form-input">
															<input type="number" class="form-control"
																:placeholder="translate('marketplace.price_max')"
																min="0" max="1" step="0.001" v-model="filters.maxFloat"
																@change="applyFilters">
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>

								<!-- Фазы (для ножей/перчаток) -->
								<div class="accordion-item">
									<h2 class="accordion-header">
										<button class="accordion-button" type="button" data-bs-toggle="collapse"
											data-bs-target="#collapsePhases">
											<span class="dark-text">{{ translate('marketplace.phases') }}</span>
										</button>
									</h2>
									<div id="collapsePhases" class="accordion-collapse collapse show">
										<div class="accordion-body">
											<ul class="category-list custom-padding custom-height scroll-bar">
												<li v-for="phase in phaseOptions" :key="phase.value">
													<a href="#" @click.prevent="togglePhase(phase.value)"
														:class="{ active: filters.phases[phase.value] }">
														<div class="form-check ps-0 m-0 category-list-box">
															<div class="form-check-label">
																<span class="name">{{ phase.label }}</span>
															</div>
														</div>
													</a>
												</li>
											</ul>
										</div>
									</div>
								</div>

							</div>

							<!-- Кнопка очистки фильтров -->
							<div v-if="!seller" class="mt-4 pt-3 border-top">
								<button class="btn theme-outline cart-btn w-100" @click="clearAllFilters"
									:disabled="!hasActiveFilters">
									<i class="ri-refresh-line me-2"></i>
									{{ translate('ui.clear_all') }}
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Основной контент -->
				<div class="col-md-8 col-lg-9 col-xl-10 ratio3_2">
					<!-- Сортировка и количество -->
					<div class="row mb-4 justify-content-between align-items-center d-none d-lg-flex">
						<div class="col-auto">
							<div class="d-flex align-items-center justify-content-end">
								<label class="me-2">{{ translate('ui.sort') }}</label>
								<select class="form-select form-select-sm" style="width: auto;" v-model="sortValue"
									@change="handleSortChange">
									<option value="listed_at-desc">{{ translate('ui.sort_newest') }}</option>
									<option value="price-asc">{{ translate('ui.sort_price_low') }}</option>
									<option value="price-desc">{{ translate('ui.sort_price_high') }}</option>
									<option value="wear_value-asc">{{ translate('ui.sort_wear_best') }}</option>
									<option value="wear_value-desc">{{ translate('ui.sort_wear_worst') }}</option>
								</select>
							</div>
						</div>
						<div class="col-auto">
							<p class="small mb-0">
								{{ translate('ui.total_listings').replace(':total', pagination.total).replace(':shown',
									shownCount) }}
							</p>
						</div>
					</div>

					<!-- Mobile filter button -->
					<button type="button"
						class="mp-mobile-filter-btn d-lg-none d-inline-flex align-items-center justify-content-center border-0"
						@click="mobileFiltersOpen = true" aria-label="Фильтры">
						<i class="ri-equalizer-line"></i>
					</button>

					<!-- Mobile filter drawer -->
					<div class="mp-filter-backdrop d-lg-none" :class="{ 'is-open': mobileFiltersOpen }"
						@click="mobileFiltersOpen = false"></div>
					<aside class="mp-filter-drawer d-lg-none position-fixed" :class="{ 'is-open': mobileFiltersOpen }">
						<div class="mp-filter-head d-flex align-items-center justify-content-between">
							<div class="mp-filter-title d-flex align-items-center gap-2">
								<i class="m-ico m-ico-filter-title"></i>
								<span>Фильтр</span>
							</div>
							<button type="button"
								class="mp-filter-close d-inline-flex align-items-center justify-content-center border-0"
								@click="mobileFiltersOpen = false" aria-label="Закрыть">
								<i class="m-ico m-ico-close"></i>
							</button>
						</div>

						<div class="mp-filter-body flex-grow-1 overflow-y-auto px-3 pb-3">
							<div class="mp-filter-search mb-3">
								<input type="search" class="w-100" v-model="filters.search" @input="debouncedSearch"
									placeholder="Поиск по скинам" autocomplete="off">
							</div>

							<div class="mp-filter-group mb-3">
								<div class="mp-filter-label">ЦЕНА</div>
								<div class="d-flex align-items-center gap-2">
									<input type="number" class="mp-filter-input flex-grow-1" placeholder="₽ Min"
										v-model="filters.minPrice" @change="applyFilters">
									<span class="mp-filter-dash">—</span>
									<input type="number" class="mp-filter-input flex-grow-1" placeholder="₽ Max"
										v-model="filters.maxPrice" @change="applyFilters">
								</div>
							</div>

							<div class="mp-filter-group mb-3">
								<div class="mp-filter-label">КАТЕГОРИИ</div>
								<div class="d-flex flex-wrap gap-2">
									<button type="button" class="mp-pill" :class="{ active: !filters.types }"
										@click="filters.types && toggleCategory(filters.types)">Все</button>
									<button v-for="c in categories" :key="c.type" type="button" class="mp-pill"
										:class="{ active: filters.types === c.type }" @click="toggleCategory(c.type)">{{
											c.name }}</button>
								</div>
							</div>

							<div class="mp-filter-group mb-3">
								<div class="mp-filter-label">КАЧЕСТВО</div>
								<div class="d-flex flex-wrap gap-2">
									<button type="button" class="mp-pill"
										:class="{ active: !Object.values(filters.wearConditions).some(v => v) }"
										@click="clearWearConditions">Все</button>
									<button v-for="quality in qualityOptions" :key="quality.value" type="button"
										class="mp-pill" :class="{ active: filters.wearConditions[quality.value] }"
										@click="toggleQuality(quality.value)">
										{{ translate('tags.values.' + quality.value) }}
									</button>
								</div>
							</div>

							<div class="mp-filter-group mb-3">
								<div class="mp-filter-label">РАРИТЕТНОСТЬ</div>
								<div class="mp-rarity-grid">
									<button v-for="rarity in rarityOptions" :key="rarity.value" type="button"
										class="mp-rarity-pill"
										:class="['mp-rarity-' + rarity.value, { active: filters.rarities[rarity.value] }]"
										@click="toggleRarity(rarity.value)">
										<span>{{ translate('tags.values.' + rarity.value) }}</span>
									</button>
								</div>
							</div>

							<div class="mp-filter-group mb-3">
								<div class="mp-filter-label">ДИАПАЗОН FLOAT</div>
								<div class="d-flex align-items-center gap-2">
									<input type="number" class="mp-filter-input flex-grow-1" placeholder="Min" min="0"
										max="1" step="0.001" v-model="filters.minFloat" @change="applyFilters">
									<span class="mp-filter-dash">—</span>
									<input type="number" class="mp-filter-input flex-grow-1" placeholder="Max" min="0"
										max="1" step="0.001" v-model="filters.maxFloat" @change="applyFilters">
								</div>
							</div>

							<div class="mp-filter-group mb-3">
								<div class="mp-filter-label">ФАЗЫ</div>
								<div class="d-flex flex-wrap gap-2">
									<button v-for="phase in phaseOptions" :key="phase.value" type="button"
										class="mp-pill" :class="{ active: filters.phases[phase.value] }"
										@click="togglePhase(phase.value)">{{ phase.label }}</button>
								</div>
							</div>
						</div>

						<div class="mp-filter-footer p-3">
							<button type="button"
								class="mp-filter-clear w-100 d-flex align-items-center justify-content-center"
								@click="clearAllFilters">
								<span class="text-center me-2">Очистить все</span>
								<span class="mp-filter-count">{{ pagination.total }} ITEMS</span>
							</button>
						</div>
					</aside>

					<!-- Контейнер для товаров -->
					<div class="row g-4">
						<div v-for="listing in listings" :key="listing.id" class="col-xxl-2 col-xl-3 col-lg-4 col-6">
							<!-- Мобильная карточка -->
							<div class="m-listing-card d-lg-none h-100" :class="getRarityClass(listing)">
								<a :href="`/marketplace/${listing.id}`" class="m-lc-img">
									<img class="w-100" :src="getListingImageUrl(listing)"
										:alt="listing.item?.name_ru || listing.inventory_item_name || 'Неизвестный предмет'"
										@error="handleImageError">
								</a>
								<div class="px-3 mt-2">
									<h4 class="m-lc-price m-0" v-html="formatPrice(listing.price, 'RUB')"></h4>
								</div>
								<a :href="`/marketplace/${listing.id}`" class="m-lc-title px-3 mt-1">
									{{ listing.item?.name_ru || listing.inventory_item_name || 'Неизвестный предмет' }}
								</a>
								<div v-if="listing.wear_name" class="m-lc-wear px-3">
									{{ translate('tags.values.' + listing.wear_name) }}
								</div>
								<div class="px-3 mt-1">
									<FloatBar :item="listing" :show-value="false" />
								</div>
								<div
									class="m-lc-actions d-flex align-items-center justify-content-between gap-2 px-3 pb-3 pt-2">
									<div v-if="!listing.is_own_item && !listing.purchase_blocked" data-cart-button
										:data-listing-id="listing.id" :data-is-in-cart="listing.is_in_cart"
										data-size="small" data-variant="outline"
										class="cart-button-placeholder m-lc-cart flex-grow-lg-1">
									</div>
									<div data-favorite-button :data-listing-id="listing.id"
										:data-is-favorite="listing.is_favorite"
										class="favorite-button-placeholder m-lc-fav">
									</div>
								</div>
							</div>

							<div class="vertical-product-box h-100 d-none d-lg-flex flex-column"
								:class="getRarityClass(listing)">
								<div v-if="listing.is_stattrak" class="seller-badge new-badge">
									<img class="img-fluid badge" src="/images/svg/star-white.svg" alt="medal">
									<h6>ST</h6>
								</div>
								<div class="vertical-product-box-img">
									<a :href="`/marketplace/${listing.id}`">
										<img class="product-img-top w-100 bg-img skin-image"
											:src="getListingImageUrl(listing)"
											:alt="listing.item?.name_ru || listing.inventory_item_name || 'Неизвестный предмет'"
											@error="handleImageError">
									</a>
									<div class="offers">
										<div class="d-flex align-items-center justify-content-between">
											<h4 v-html="formatPrice(listing.price, 'RUB')"></h4>
										</div>
									</div>
								</div>
								<div class="vertical-product-body d-flex flex-column flex-grow-1">
									<div class="d-flex flex-column flex-grow-lg-1 mt-sm-3 mt-2 mb-2">
										<a :href="`/marketplace/${listing.id}`">
											<h4 class="vertical-product-title">{{ listing.item?.name_ru ||
												listing.inventory_item_name || 'Неизвестный предмет' }}</h4>
										</a>
										<div v-if="listing.wear_value !== null && listing.wear_value !== undefined"
											class="text-muted small mb-1">
											{{ listing.wear_value.toFixed(4) }}
										</div>
										<h5 class="product-items mb-2">{{ listing.wear_name ? translate('tags.values.' +
										listing.wear_name) : '' }} {{
												listing.item?.rarity_translated || '' }}</h5>
										<FloatBar :item="listing" :show-value="false" />
										<p class="text-muted small">от {{ listing.seller?.name || 'Неизвестный продавец'
										}}</p>
									</div>
									<div
										class="location-distance d-flex align-items-center justify-content-between gap-2 pt-sm-3 pt-2">
										<div v-if="!listing.is_own_item && !listing.purchase_blocked" data-cart-button
											:data-listing-id="listing.id" :data-is-in-cart="listing.is_in_cart"
											data-size="small" data-variant="outline"
											class="cart-button-placeholder flex-fill">
										</div>
										<div data-favorite-button :data-listing-id="listing.id"
											:data-is-favorite="listing.is_favorite" class="favorite-button-placeholder">
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Индикатор загрузки -->
					<div v-if="isLoading" class="text-center py-4">
						<div class="spinner-border text-primary" role="status">
							<span class="visually-hidden">Загрузка...</span>
						</div>
						<p class="mt-2 text-muted">Загрузка предложений...</p>
					</div>

					<!-- Пагинация -->
					<Pagination v-if="!isLoading"
						:current-page="currentPage"
						:last-page="lastPage"
						:per-page="perPage"
						class="mt-4"
						@update:current-page="goToPage"
						@update:per-page="changePerPage" />

					<!-- Сообщение об отсутствии товаров -->
					<div v-if="!isLoading && listings.length === 0" class="text-center py-5">
						<i class="ri-search-line display-4 text-muted mb-3"></i>
						<h4 class="text-muted">Предложения не найдены</h4>
						<p class="text-muted">Попробуйте изменить параметры поиска</p>
					</div>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import { ref, reactive, onMounted, computed, nextTick, onUnmounted } from 'vue'
import { createApp } from 'vue'
import axios from 'axios'
import CartButton from './CartButton.vue'
import FavoriteButton from './FavoriteButton.vue'
import { formatPrice } from '../../shared/utils/helpers'
import FloatBar from './FloatBar.vue'
import Pagination from '../../shared/components/Pagination.vue'

export default {
	name: 'Marketplace',
	components: {
		FloatBar,
		Pagination
	},
	props: {
		initialListings: {
			type: Array,
			default: () => []
		},
		initialTotal: {
			type: Number,
			default: 0
		},
		initialHasMore: {
			type: Boolean,
			default: false
		},
		initialSeller: {
			type: Object,
			default: null
		},
		initialSellerStats: {
			type: Object,
			default: null
		}
	},
	setup(props) {
		// Состояние данных
		const listings = ref([...props.initialListings])
		const categories = ref([])
		const mobileFiltersOpen = ref(false)
		const clearWearConditions = () => {
			Object.keys(filters.wearConditions).forEach(k => {
				filters.wearConditions[k] = false
			})
			applyFilters()
		}
		const seller = ref(props.initialSeller)
		const sellerStats = ref(props.initialSellerStats)
		const tags = ref([])
		const isLoading = ref(false)
		const currentPage = ref(1)
		const perPage = ref(25)
		const searchTimeout = ref(null)

		const pagination = reactive({
			total: props.initialTotal,
			hasMorePages: props.initialHasMore
		})

		const lastPage = computed(() => {
			if (!pagination.total || !perPage.value) return 1
			return Math.max(1, Math.ceil(pagination.total / perPage.value))
		})

		const filters = reactive({
			search: '',
			minPrice: '',
			maxPrice: '',
			types: '',
			stattrak: false,
			souvenir: false,
			wearRange: '',
			wearConditions: {
				fn: false,
				mw: false,
				ft: false,
				ww: false,
				bs: false
			},
			rarities: {
				consumer: false,
				industrial: false,
				milspec: false,
				restricted: false,
				classified: false,
				covert: false,
				contraband: false
			},
			phases: {
				phase1: false,
				phase2: false,
				phase3: false,
				phase4: false,
				ruby: false,
				sapphire: false,
				blackpearl: false,
				emerald: false
			},
			minFloat: '',
			maxFloat: '',
			tags: [],
			sortBy: 'listed_at',
			sortOrder: 'desc'
		})

		const sortValue = ref('listed_at-desc')

		// Вычисляемые свойства
		const shownCount = computed(() => listings.value.length)

		const hasActiveFilters = computed(() => {
			const hasWearConditions = Object.values(filters.wearConditions).some(v => v)
			const hasRarities = Object.values(filters.rarities).some(v => v)
			const hasPhases = Object.values(filters.phases).some(v => v)

			return !!(
				filters.search ||
				filters.minPrice ||
				filters.maxPrice ||
				filters.types ||
				filters.stattrak ||
				filters.souvenir ||
				filters.wearRange ||
				hasWearConditions ||
				hasRarities ||
				hasPhases ||
				filters.minFloat ||
				filters.maxFloat ||
				(filters.tags && filters.tags.length > 0) ||
				sortValue.value !== 'listed_at-desc'
			)
		})


		// Утилиты для создания параметров запроса
		const createBaseParams = (excludeTypes = false, excludeTags = false) => {
			const params = new URLSearchParams()

			if (seller.value && seller.value.id) params.append('seller_id', seller.value.id)
			if (filters.search) params.append('search', filters.search)
			if (filters.minPrice) params.append('min_price', filters.minPrice)
			if (filters.maxPrice) params.append('max_price', filters.maxPrice)
			if (!excludeTypes && filters.types) params.append('types', filters.types)
			if (filters.stattrak) params.append('stattrak', filters.stattrak)
			if (filters.souvenir) params.append('souvenir', filters.souvenir)

			if (filters.wearRange) {
				if (Array.isArray(filters.wearRange)) {
					filters.wearRange.forEach(wear => params.append('wear_range[]', wear))
				} else {
					params.append('wear_range', filters.wearRange)
				}
			}

			// Добавляем фильтры качества (износа)
			const activeWearConditions = Object.entries(filters.wearConditions)
				.filter(([_, active]) => active)
				.map(([condition, _]) => condition)
			if (activeWearConditions.length > 0) {
				activeWearConditions.forEach(wear => params.append('wear_conditions[]', wear))
			}

			// Добавляем фильтры раритетности
			const activeRarities = Object.entries(filters.rarities)
				.filter(([_, active]) => active)
				.map(([rarity, _]) => rarity)
			if (activeRarities.length > 0) {
				params.append('rarities', activeRarities.join(','))
			}

			// Добавляем фильтры фаз
			const activePhases = Object.entries(filters.phases)
				.filter(([_, active]) => active)
				.map(([phase, _]) => phase)
			if (activePhases.length > 0) {
				activePhases.forEach(phase => params.append('phases[]', phase))
			}

			// Добавляем фильтры float
			if (filters.minFloat) params.append('min_float', filters.minFloat)
			if (filters.maxFloat) params.append('max_float', filters.maxFloat)

			if (!excludeTags && filters.tags && filters.tags.length > 0) {
				params.append('tags', filters.tags.join(','))
			}

			return params
		}

		// API функции
		const loadTags = async () => {
			try {
				const params = createBaseParams(false, false) // Включаем все фильтры включая categories для тегов
				const response = await axios.get(`/api/marketplace/tags?${params}`)
				tags.value = response.data
			} catch (error) {
				console.error('Ошибка загрузки тегов:', error)
			}
		}

		const loadCategories = async () => {
			try {
				const params = createBaseParams(false, false) // Включаем все фильтры включая tags для категорий
				const response = await axios.get(`/api/marketplace/categories?${params}`)
				categories.value = response.data
			} catch (error) {
				console.error('Ошибка загрузки категорий:', error)
			}
		}

		const loadListings = async (resetPage = true) => {
			if (isLoading.value) return

			if (resetPage) currentPage.value = 1

			isLoading.value = true

			try {
				const params = createBaseParams()
				params.append('page', currentPage.value)
				params.append('per_page', perPage.value)
				params.append('sort_by', filters.sortBy)
				params.append('sort_order', filters.sortOrder)

				const response = await axios.get(`/api/marketplace/listings?${params}`)
				const data = response.data

				listings.value = data.data
				pagination.total = data.pagination.total
				pagination.hasMorePages = data.pagination.has_more_pages

				nextTick(() => {
					initializeButtons()
				})

			} catch (error) {
				console.error('Ошибка загрузки товаров:', error)
			} finally {
				isLoading.value = false
			}
		}

		const goToPage = (page) => {
			if (typeof page !== 'number' || page < 1 || page > lastPage.value || page === currentPage.value) return
			currentPage.value = page
			loadListings(false)
			nextTick(() => {
				const el = document.querySelector('.popular-restaurant')
				if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
			})
		}

		const changePerPage = (value) => {
			perPage.value = parseInt(value) || 25
			loadListings(true)
		}

		// Функции фильтрации
		const debouncedSearch = () => {
			clearTimeout(searchTimeout.value)
			searchTimeout.value = setTimeout(() => {
				applyFilters()
			}, 300)
		}

		const applyFilters = () => {
			saveFiltersToStorage()
			loadCategories()
			loadTags()
			loadListings(true)
		}

		const toggleCategory = (type) => {
			filters.types = filters.types === type ? '' : type
			saveFiltersToStorage()
			loadCategories()
			loadTags()
			loadListings(true)
		}

		const toggleTag = (tag) => {
			if (tag.type === 'stattrak' || tag.type === 'souvenir') {
				filters[tag.type] = !filters[tag.type]
			} else if (tag.type === 'wear') {
				if (!Array.isArray(filters.wearRange)) {
					filters.wearRange = filters.wearRange ? [filters.wearRange] : []
				}

				const index = filters.wearRange.indexOf(tag.value)
				if (index > -1) {
					filters.wearRange.splice(index, 1)
				} else {
					filters.wearRange.push(tag.value)
				}

				if (filters.wearRange.length === 0) {
					filters.wearRange = ''
				}
			} else {
				const tagKey = `${tag.type}:${tag.value}`
				const index = filters.tags.indexOf(tagKey)

				if (index > -1) {
					filters.tags.splice(index, 1)
				} else {
					filters.tags.push(tagKey)
				}
			}

			applyFilters()
		}

		const isTagActive = (tag) => {
			if (tag.type === 'stattrak' || tag.type === 'souvenir') {
				return filters[tag.type]
			} else if (tag.type === 'wear') {
				if (Array.isArray(filters.wearRange)) {
					return filters.wearRange.includes(tag.value)
				}
				return filters.wearRange === tag.value
			} else {
				const tagKey = `${tag.type}:${tag.value}`
				return filters.tags.includes(tagKey)
			}
		}

		// Методы toggle для новых фильтров
		const toggleQuality = (quality) => {
			filters.wearConditions[quality] = !filters.wearConditions[quality]
			applyFilters()
		}

		const toggleRarity = (rarity) => {
			filters.rarities[rarity] = !filters.rarities[rarity]
			applyFilters()
		}

		const togglePhase = (phase) => {
			filters.phases[phase] = !filters.phases[phase]
			applyFilters()
		}

		// Функции сортировки
		const handleSortChange = () => {
			const [sortBy, sortOrder] = sortValue.value.split('-')
			filters.sortBy = sortBy
			filters.sortOrder = sortOrder
			localStorage.setItem('marketplace_sort', sortValue.value)
			applyFilters()
		}

		// Функции управления состоянием
		const clearAllFilters = () => {
			filters.search = ''
			filters.minPrice = ''
			filters.maxPrice = ''
			filters.types = ''
			filters.stattrak = false
			filters.souvenir = false
			filters.wearRange = ''
			filters.wearConditions = {
				fn: false,
				mw: false,
				ft: false,
				ww: false,
				bs: false
			}
			filters.rarities = {
				consumer: false,
				industrial: false,
				milspec: false,
				restricted: false,
				classified: false,
				covert: false,
				contraband: false
			}
			filters.phases = {
				phase1: false,
				phase2: false,
				phase3: false,
				phase4: false,
				ruby: false,
				sapphire: false,
				blackpearl: false,
				emerald: false
			}
			filters.minFloat = ''
			filters.maxFloat = ''
			filters.tags = []
			filters.sortBy = 'listed_at'
			filters.sortOrder = 'desc'

			sortValue.value = 'listed_at-desc'

			localStorage.removeItem('marketplace_filters')
			localStorage.removeItem('marketplace_sort')

			loadCategories()
			loadTags()
			loadListings(true)
		}


		// Функции хранения
		const saveFiltersToStorage = () => {
			const filtersToSave = {
				search: filters.search,
				minPrice: filters.minPrice,
				maxPrice: filters.maxPrice,
				types: filters.types,
				stattrak: filters.stattrak,
				souvenir: filters.souvenir,
				wearRange: filters.wearRange,
				wearConditions: filters.wearConditions,
				rarities: filters.rarities,
				phases: filters.phases,
				minFloat: filters.minFloat,
				maxFloat: filters.maxFloat,
				tags: filters.tags
			}
			localStorage.setItem('marketplace_filters', JSON.stringify(filtersToSave))
		}

		const restoreFiltersFromStorage = () => {
			try {
				// Восстанавливаем фильтры
				const savedFilters = localStorage.getItem('marketplace_filters')
				if (savedFilters) {
					const parsedFilters = JSON.parse(savedFilters)
					Object.assign(filters, parsedFilters)
				}

				// Восстанавливаем сортировку
				const savedSort = localStorage.getItem('marketplace_sort')
				if (savedSort) {
					sortValue.value = savedSort
					const [sortBy, sortOrder] = savedSort.split('-')
					filters.sortBy = sortBy
					filters.sortOrder = sortOrder
				}

				// Перезагружаем данные если есть активные фильтры
				if (hasActiveFilters.value || filters.types) {
					loadCategories()
					loadTags()
					loadListings(true)
				}
			} catch (error) {
				console.error('Ошибка восстановления фильтров:', error)
			}
		}

		// Утилиты UI
		const handleImageError = (event) => {
			event.target.closest('.vertical-product-box-img').classList.add('image-error')
		}

		const createVueApp = (component, props) => {
			const app = createApp(component, props)
			return app
		}

		const initializeButtons = () => {
			// Инициализация кнопок корзины
			const cartButtons = document.querySelectorAll('[data-cart-button]:not(.cart-initialized)')
			cartButtons.forEach(button => {
				const listingId = parseInt(button.dataset.listingId)
				const size = button.dataset.size || 'normal'
				const variant = button.dataset.variant || 'primary'
				const initialIsInCart = button.dataset.isInCart === 'true'

				if (listingId) {
					const app = createVueApp(CartButton, { listingId, size, variant, initialIsInCart })
					app.mount(button)
					button.classList.add('cart-initialized')
				}
			})

			// Инициализация кнопок избранного
			const favoriteButtons = document.querySelectorAll('[data-favorite-button]:not(.favorite-initialized)')
			favoriteButtons.forEach(button => {
				const listingId = parseInt(button.dataset.listingId)
				const initialIsFavorite = button.dataset.isFavorite === 'true'


				if (listingId) {
					const app = createVueApp(FavoriteButton, { listingId, initialIsFavorite })
					app.mount(button)
					button.classList.add('favorite-initialized')
				}
			})
		}

		const getListingImageUrl = (listing) => {
			if (!listing) {
				return '/images/skin_no_image.svg'
			}

			if (listing.inventory_icon_url) {
				if (!listing.inventory_icon_url.startsWith('http')) {
					return `https://community.steamstatic.com/economy/image/${listing.inventory_icon_url}`
				}
				return listing.inventory_icon_url
			}

			if (listing.item && listing.item.image_url) {
				return listing.item.image_url
			}

			return '/images/skin_no_image.svg'
		}

		const getRarityClass = (listing) => {
			if (!listing || !listing.structured_tags) {
				return ''
			}

			const rarityTag = listing.structured_tags.find(tag => tag.category_code === 'rarity')
			if (rarityTag) {
				return `rarity-${rarityTag.normalized_value}`
			}

			return ''
		}

		// Инициализация
		onMounted(() => {
			loadCategories()
			loadTags()
			restoreFiltersFromStorage()

			// Функция обработки смены валюты
			const handleCurrencyChange = () => {
				// Принудительно обновляем все цены, создавая новый массив
				listings.value = listings.value.map(listing => ({ ...listing }))
			}

			// Слушаем события смены валюты
			window.addEventListener('currency-changed', handleCurrencyChange)

			nextTick(() => {
				initializeButtons()
			})
		})

		// Функция перевода
		const translate = (key) => {
			const keys = key.split('.');
			let translation = window.translations;

			for (const k of keys) {
				if (translation && typeof translation === 'object' && translation[k]) {
					translation = translation[k];
				} else {
					return key;
				}
			}

			return translation || key;
		}

		// Опции для фильтров
		const qualityOptions = [
			{ value: 'fn' },
			{ value: 'mw' },
			{ value: 'ft' },
			{ value: 'ww' },
			{ value: 'bs' }
		]

		const rarityOptions = [
			{ value: 'consumer' },
			{ value: 'industrial' },
			{ value: 'milspec' },
			{ value: 'restricted' },
			{ value: 'classified' },
			{ value: 'covert' },
			{ value: 'contraband' }
		]

		const phaseOptions = [
			{ value: 'phase1', label: 'Phase 1' },
			{ value: 'phase2', label: 'Phase 2' },
			{ value: 'phase3', label: 'Phase 3' },
			{ value: 'phase4', label: 'Phase 4' },
			{ value: 'ruby', label: 'Ruby' },
			{ value: 'sapphire', label: 'Sapphire' },
			{ value: 'blackpearl', label: 'Black Pearl' },
			{ value: 'emerald', label: 'Emerald' }
		]

		// Очистка слушателя при размонтировании
		onUnmounted(() => {
			window.removeEventListener('currency-changed', handleCurrencyChange)
		})

		return {
			listings,
			categories,
			tags,
			seller,
			sellerStats,
			isLoading,
			pagination,
			filters,
			sortValue,
			qualityOptions,
			rarityOptions,
			phaseOptions,
			shownCount,
			hasActiveFilters,
			debouncedSearch,
			applyFilters,
			toggleCategory,
			toggleTag,
			toggleQuality,
			toggleRarity,
			togglePhase,
			isTagActive,
			handleSortChange,
			lastPage,
			perPage,
			currentPage,
			goToPage,
			changePerPage,
			formatPrice,
			clearAllFilters,
			clearWearConditions,
			mobileFiltersOpen,
			handleImageError,
			getListingImageUrl,
			getRarityClass,
			translate
		}
	}
}
</script>

