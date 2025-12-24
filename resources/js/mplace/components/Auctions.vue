<template>
	<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden">
		<div class="container">
			<div class="title text-center">
				<h2>Аукционы</h2>
				<div class="loader-line" style="left: calc(50% - 40px);"></div>
				<div class="sub-title">
					<p>Участвуйте в торгах за популярные скины.</p>
				</div>
			</div>

			<div class="row g-4">
				<!-- Основной контент -->
				<div class="col-12 ratio3_2">
					<!-- Сортировка и количество -->
					<div class="row mb-4">
						<div class="col-md-6">
							<p class="small text-muted mb-0">
								Всего аукционов {{ pagination.total }}, показано {{ shownCount }}
							</p>
						</div>
						<div class="col-md-6">
							<div class="d-flex align-items-center justify-content-end">
								<label class="me-2">Сортировка:</label>
								<select class="form-select form-select-sm" style="width: auto;" v-model="sortValue"
									@change="handleSortChange">
									<option value="ends_at-asc">Заканчивающиеся</option>
									<option value="current_price-asc">Цена: дешевые</option>
									<option value="current_price-desc">Цена: дорогие</option>
									<option value="bid_count-desc">Больше ставок</option>
								</select>
							</div>
						</div>
					</div>

					<!-- Контейнер для аукционов -->
					<div class="row g-4">
						<div v-for="auction in auctions" :key="auction.id" class="col-lg-3 col-md-4">
							<div class="vertical-product-box h-100 d-flex flex-column" :class="getRarityClass(auction.listing)">
								<div v-if="auction.listing?.is_stattrak" class="seller-badge new-badge">
									<img class="img-fluid badge"
										src="/images/svg/star-white.svg" alt="medal">
									<h6>ST</h6>
								</div>
								<div class="vertical-product-box-img">
									<a :href="`/marketplace/${auction.listing_id}`">
										<img class="product-img-top w-100 bg-img skin-image"
											:src="getAuctionImageUrl(auction)"
											:alt="auction.listing?.item?.name_ru || auction.listing?.inventory_item_name || 'Неизвестный предмет'"
											@error="handleImageError">
									</a>
									<div class="offers">
										<div class="d-flex align-items-center justify-content-between">
											<h4 v-html="formatPrice(auction.current_price)"></h4>
										</div>
									</div>
								</div>
								<div class="vertical-product-body d-flex flex-column flex-grow-1">
									<div class="d-flex flex-column mt-sm-3 mt-2 mb-2">
										<a :href="`/marketplace/${auction.listing_id}`">
											<h4 class="vertical-product-title">{{ auction.listing?.item?.name_ru ||
												auction.listing?.inventory_item_name || 'Неизвестный предмет' }}</h4>
										</a>
										<h5 class="product-items mb-2">{{ auction.listing?.wear_name }} {{
											auction.listing?.item?.rarity_translated || '' }}</h5>
										<p class="text-muted small">от {{ auction.seller?.name || 'Неизвестный продавец'
										}}</p>
									</div>

									<!-- Информация об аукционе -->
									<div class="auction-info mt-2 pt-2 border-top">
										<p class="small text-muted mb-2"><i
												class="ri-auction-line me-2 text-primary"></i> <strong>Активный аукцион </strong></p>
										<div class="d-flex justify-content-between align-items-center">
											<small class="text-muted">Текущая цена:</small>
											<small class="text-end" v-html="formatPrice(auction.current_price)">
											</small>
										</div>
										<div class="d-flex justify-content-between align-items-center">
											<small class="text-muted">До окончания:</small>
											<small class="text-end">{{ getTimeLeft(auction) }}</small>
										</div>
										<div class="d-flex justify-content-between align-items-center">
											<small class="text-muted">Всего ставок:</small>
											<small class="text-end">{{ auction.bid_count || 0 }}</small>
										</div>
										<div class="d-flex justify-content-between align-items-center">
											<small class="text-muted">Лидирует:</small>
											<small class="text-end">{{ auction.last_bidder?.name || auction.lastBidder?.name || 'Нет ставок' }}</small>
										</div>
									</div>
									<!-- Кнопка "Сделать ставку" -->
									<div v-if="currentUser && !auction.is_own_auction" class="text-center my-3">
										<button @click="placeBid(auction)"
											:disabled="placingBids.has(auction.id)"
											class="btn btn-sm theme-btn w-100">
											<i class="ri-hammer-line me-1"></i>
											<span v-if="placingBids.has(auction.id)">Размещение...</span>
											<span v-else>Ставка <span v-html="formatPrice(getMinimumBid(auction))"></span></span>
										</button>
									</div>

									<div
										class="location-distance d-flex align-items-center justify-content-between gap-2 pt-sm-3 pt-2">
										<div v-if="!auction.is_own_auction" data-cart-button
											:data-listing-id="auction.listing_id"
											:data-is-in-cart="auction.listing.is_in_cart" data-size="small"
											data-variant="outline" class="cart-button-placeholder flex-fill">
										</div>
										<div data-favorite-button :data-listing-id="auction.listing_id"
											:data-is-favorite="auction.listing?.is_favorite"
											class="favorite-button-placeholder">
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
						<p class="mt-2 text-muted">Загрузка аукционов...</p>
					</div>

					<!-- Кнопка "Загрузить еще" -->
					<div v-if="!isLoading && pagination.hasMorePages" class="text-center mt-4">
						<button class="btn theme-outline cart-btn" @click="loadMore">
							Загрузить еще
						</button>
					</div>

					<!-- Сообщение об отсутствии аукционов -->
					<div v-if="!isLoading && auctions.length === 0" class="text-center py-5">
						<i class="ri-auction-line display-4 mb-3 content-color"></i>
						<h4>Активные аукционы не найдены</h4>
						<p>В данный момент нет активных аукционов</p>
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
import { createEcho } from '../../shared/echo'

export default {
	name: 'Auctions',
	props: {
		initialAuctions: {
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
		currentUser: {
			type: Object,
			default: null
		}
	},
	setup(props) {
		// Состояние данных
		const auctions = ref([...props.initialAuctions])
		const isLoading = ref(false)
		const currentPage = ref(2)
		const echo = ref(null)
		const channels = ref(new Map())
		const placingBids = ref(new Set())
		const currentUser = ref(props.currentUser)
		const countdownInterval = ref(null)

		const pagination = reactive({
			total: props.initialTotal,
			hasMorePages: props.initialHasMore
		})

		const filters = reactive({
			sortBy: 'ends_at',
			sortOrder: 'asc'
		})

		const sortValue = ref('ends_at-asc')

		// Вычисляемые свойства
		const shownCount = computed(() => auctions.value.length)

		// API функции
		const loadAuctions = async (append = false) => {
			if (isLoading.value) return

			isLoading.value = true

			try {
				const params = new URLSearchParams()
				params.append('page', append ? currentPage.value : 1)
				params.append('per_page', 24)
				params.append('sort_by', filters.sortBy)
				params.append('sort_order', filters.sortOrder)

				const response = await axios.get(`/api/auctions?${params}`)
				const data = response.data

				if (append) {
					auctions.value.push(...data.data)
					currentPage.value++
				} else {
					auctions.value = data.data
					currentPage.value = 2
				}

				pagination.total = data.pagination.total
				pagination.hasMorePages = data.pagination.has_more_pages

				nextTick(() => {
					initializeButtons()
					initializeWebSocket()
					// Перезапускаем счетчик после загрузки аукционов
					startCountdown()
				})

			} catch (error) {
				console.error('Ошибка загрузки аукционов:', error)
			} finally {
				isLoading.value = false
			}
		}

		// Функции сортировки
		const handleSortChange = () => {
			const [sortBy, sortOrder] = sortValue.value.split('-')
			filters.sortBy = sortBy
			filters.sortOrder = sortOrder
			localStorage.setItem('auctions_sort', sortValue.value)
			loadAuctions(false)
		}

		const loadMore = () => {
			loadAuctions(true)
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

		const getAuctionImageUrl = (auction) => {
			if (!auction || !auction.listing) {
				return '/images/skin_no_image.svg'
			}

			const listing = auction.listing

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

		const getTimeLeft = (auction) => {
			if (!auction || !auction.ends_at) return 'Завершен'

			const now = new Date().getTime()
			const endTime = new Date(auction.ends_at).getTime()
			const timeLeft = Math.max(0, endTime - now)

			if (timeLeft === 0) return 'Завершен'

			const totalSeconds = Math.floor(timeLeft / 1000)
			const days = Math.floor(totalSeconds / 86400)
			const hours = Math.floor((totalSeconds % 86400) / 3600)
			const minutes = Math.floor((totalSeconds % 3600) / 60)
			const seconds = totalSeconds % 60

			if (days > 0) {
				return `${days}д ${hours}ч ${minutes}м ${seconds}с`
			} else if (hours > 0) {
				return `${hours}ч ${minutes}м ${seconds}с`
			} else if (minutes > 0) {
				return `${minutes}м ${seconds}с`
			} else {
				return `${seconds}с`
			}
		}

		const getMinimumBid = (auction) => {
			if (!auction) return 0
			const currentPrice = parseFloat(auction.current_price) || 0
			const minIncrement = parseFloat(auction.min_bid_increment) || 1
			return currentPrice + minIncrement
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

		const initializeWebSocket = () => {
			if (!echo.value) {
				echo.value = createEcho()
			}

			// Подписываемся на единый канал для всех аукционов
			if (!channels.value.has('auctions.all')) {
				const channel = echo.value.channel('auctions.all')
					.listen('.bid.placed', (e) => {
						// Находим аукцион в массиве и обновляем его данные
						const auctionIndex = auctions.value.findIndex(a => a.id === e.auction.id)
						if (auctionIndex !== -1) {
							// Обновляем данные аукциона
							if (e.auction) {
								auctions.value[auctionIndex].current_price = e.auction.current_price
								auctions.value[auctionIndex].bid_count = e.auction.bid_count
								auctions.value[auctionIndex].last_bidder_id = e.auction.last_bidder_id
								if (e.auction.ends_at) {
									auctions.value[auctionIndex].ends_at = e.auction.ends_at
								}
							}
							// Обновляем информацию о последнем сделавшем ставку из bid
							if (e.bid && e.bid.bidder) {
								if (!auctions.value[auctionIndex].last_bidder) {
									auctions.value[auctionIndex].last_bidder = {}
								}
								auctions.value[auctionIndex].last_bidder = e.bid.bidder
							}
						}
					})

				channels.value.set('auctions.all', channel)
			}
		}

		const cleanupWebSocket = () => {
			if (echo.value && channels.value.has('auctions.all')) {
				// Отписываемся от единого канала
				echo.value.leaveChannel('auctions.all')
				channels.value.clear()
			}
		}

		const placeBid = async (auction) => {
			if (placingBids.value.has(auction.id) || auction.is_own_auction) return

			const bidAmount = getMinimumBid(auction)
			placingBids.value.add(auction.id)

			try {
				const response = await axios.post(`/api/auctions/${auction.id}/bid`, {
					amount: bidAmount
				})

				if (response.data.success) {
					// Обновляем данные аукциона
					const auctionIndex = auctions.value.findIndex(a => a.id === auction.id)
					if (auctionIndex !== -1) {
						auctions.value[auctionIndex] = { ...auctions.value[auctionIndex], ...response.data.auction }
					}
					window.toast.success('Ставка успешно размещена!')
				}
			} catch (error) {
				// Обрабатываем ошибки авторизации
				if (error.response?.status === 401) {
					setTimeout(() => {
						window.location.href = '/auth/steam';
					}, 2000);
					return;
				}

				// Ошибки обрабатываются глобально
			} finally {
				placingBids.value.delete(auction.id)
			}
		}


		// Функция запуска счетчика времени
		const startCountdown = () => {
			// Останавливаем предыдущий интервал если есть
			if (countdownInterval.value) {
				clearInterval(countdownInterval.value)
			}
			
			// Запускаем новый интервал для обновления времени каждую секунду
			countdownInterval.value = setInterval(() => {
				// Принудительно обновляем компонент для перерисовки времени
				auctions.value = [...auctions.value]
			}, 1000)
		}

		// Инициализация
		onMounted(async () => {

			// Восстанавливаем сортировку
			const savedSort = localStorage.getItem('auctions_sort')
			if (savedSort) {
				sortValue.value = savedSort
				const [sortBy, sortOrder] = savedSort.split('-')
				filters.sortBy = sortBy
				filters.sortOrder = sortOrder
				loadAuctions(false)
			}

			// Функция обработки смены валюты
			const handleCurrencyChange = () => {
				// Принудительно обновляем все цены, создавая новый массив
				auctions.value = auctions.value.map(auction => ({ ...auction }))
			}

			// Слушаем события смены валюты
			window.addEventListener('currency-changed', handleCurrencyChange)

			// Запускаем счетчик времени
			startCountdown()

			nextTick(() => {
				initializeButtons()
				initializeWebSocket()
				// Перезапускаем счетчик после загрузки аукционов
				startCountdown()
			})
		})

		// Очистка слушателя при размонтировании
		onUnmounted(() => {
			window.removeEventListener('currency-changed', handleCurrencyChange)
			cleanupWebSocket()
			// Останавливаем счетчик времени
			if (countdownInterval.value) {
				clearInterval(countdownInterval.value)
			}
		})

		return {
			auctions,
			isLoading,
			pagination,
			filters,
			sortValue,
			shownCount,
			handleSortChange,
			loadMore,
			formatPrice,
			handleImageError,
			getAuctionImageUrl,
			getTimeLeft,
			getMinimumBid,
			placeBid,
			placingBids,
			currentUser,
			getRarityClass
		}
	}
}
</script>