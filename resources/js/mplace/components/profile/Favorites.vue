<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<div class="d-flex justify-content-between align-items-end gap-2">
				<h3>Избранное</h3>
				<p class="text-muted fs-6 mb-0" v-if="favorites.length > 0">{{ favorites.length }} товар(ов)</p>
			</div>
		</div>
		
		<div v-if="isLoading && favorites.length === 0" class="text-center py-5">
			<div class="loader-gif">
				<div class="radar-ring"></div>
				<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
			</div>
			<p class="mt-3">Загружаем избранное...</p>
		</div>

		<!-- Favorites Items -->
		<div v-else-if="favorites.length > 0" class="inventory-items">
			<div class="row g-4 d-flex flex-lg-row flex-column-reverse">
				<div class="col-lg-7 col-12">
					<div class="mb-4">
						<div class="row g-3">
							<div v-for="favorite in favorites" :key="favorite.id" class="col-lg-4 col-md-6">
								<div @click="selectFavorite(favorite)" 
									:class="['h-100 inventory-item text-center position-relative', 
									{ 'active': selectedFavorite && selectedFavorite.id === favorite.id },
									{ 'item-unavailable': favorite.listing.status !== 'active' }]">
									
									<!-- Бейдж статуса -->
									<div v-if="favorite.listing.status !== 'active'" class="status-badge position-absolute" style="top: 8px; left: 8px; z-index: 10;">
										<span v-if="favorite.listing.status === 'sold'" class="badge bg-secondary">Продан</span>
										<span v-else-if="favorite.listing.status === 'cancelled'" class="badge bg-warning">Снят</span>
										<span v-else-if="favorite.listing.status === 'pending'" class="badge bg-info">Ожидает</span>
									</div>
									
									<div 
										data-favorite-button 
										:data-listing-id="favorite.listing.id"
										:data-is-favorite="favorite.listing.is_favorite"
										class="favorite-button-placeholder position-absolute"
										style="top: 8px; right: 8px; z-index: 10;"
										title="Удалить из избранного"
										@click.stop>
									</div>
									<img class="img-fluid inventory-img h-auto" :src="getIconUrl(favorite.listing)"
										:alt="getItemName(favorite.listing)" @error="handleImageError">
									<h6 class="mt-2">{{ getItemName(favorite.listing) }}</h6>
									<small class="text-muted">{{ getWearCondition(favorite.listing.wear_value) || favorite.listing.wear_name || 'Состояние неизвестно' }}</small>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-lg-5 col-12" id="favorite-details-section">
					<ItemDetails 
						v-if="selectedFavorite"
						:item="selectedFavorite.listing"
						:active-tab="'favorites'">
						
						<template #actions="{ item }">
							<!-- Цена -->
							<div class="item-price mb-3">
								<div class="d-flex justify-content-between align-items-center">
									<strong>Цена:</strong>
									<span class="fw-bold fs-5" v-html="formatPrice(item.price)"></span>
								</div>
							</div>
							
							<!-- Статус товара -->
							<div class="item-status mb-3">
								<div v-if="item.status === 'active'" class="alert alert-success mb-0 py-2">
									<i class="ri-check-line me-2"></i>Товар доступен для покупки
								</div>
								<div v-else-if="item.status === 'sold'" class="alert alert-secondary mb-0 py-2">
									<i class="ri-shopping-cart-line me-2"></i>Товар продан
								</div>
								<div v-else-if="item.status === 'cancelled'" class="alert alert-warning mb-0 py-2">
									<i class="ri-pause-line me-2"></i>Товар снят с продажи
								</div>
								<div v-else-if="item.status === 'pending'" class="alert alert-info mb-0 py-2">
									<i class="ri-time-line me-2"></i>Товар ожидает активации
								</div>
							</div>
							
							<!-- Дополнительная информация -->
							<div class="item-info mb-3">
								<div v-if="isStatTrak(item)" class="mb-2">
									<i class="ri-star-fill text-warning me-1"></i> StatTrak™
								</div>
								<div v-if="isSouvenir(item)" class="mb-2">
									<i class="ri-trophy-fill text-warning me-1"></i> Souvenir
								</div>
								<div class="text-muted">
									<small>Добавлено в избранное: {{ formatDate(selectedFavorite.created_at) }}</small>
								</div>
							</div>
							
							<!-- Кнопки действий -->
							<div v-if="item.status === 'active'" class="btn-group w-100">
								<a :href="`/marketplace/${item.id}`" class="btn theme-outline" title="Посмотреть товар">
									<i class="ri-eye-line me-1"></i>Посмотреть
								</a>
								<CartButton 
									:key="item.id"
									:listing-id="item.id" 
									size="normal" 
									variant="primary" />
							</div>
							<div v-else class="d-grid">
								<a :href="`/marketplace/${item.id}`" class="btn theme-outline" title="Посмотреть товар">
									<i class="ri-eye-line me-1"></i>Посмотреть
								</a>
							</div>
						</template>
					</ItemDetails>
				</div>
			</div>
		</div>

		<!-- Empty State -->
		<div v-else class="text-center py-5">
			<i class="ri-heart-line display-4 text-muted mb-3"></i>
			<h4 class="text-muted">Избранное пусто</h4>
			<p class="text-muted">Добавьте товары в избранное, чтобы легко находить их потом</p>
			<a href="/marketplace" class="btn theme-btn mt-3">
				<i class="ri-shopping-bag-line me-1"></i>Перейти в маркетплейс
			</a>
		</div>
	</div>
</template>

<script>
import axios from 'axios';
import { formatPrice, handleApiError } from '../../../shared/utils/helpers';
import { createApp } from 'vue';
import FavoriteButton from '../FavoriteButton.vue';
import CartButton from '../CartButton.vue';
import ItemDetails from './ItemDetails.vue';

export default {
	name: 'ProfileFavorites',
	components: {
		CartButton,
		ItemDetails
	},
	setup() {
		return { formatPrice };
	},
	props: {
		client: {
			type: Object,
			required: true
		}
	},
	data() {
		return {
			favorites: [],
			isLoading: false,
			selectedFavorite: null
		}
	},
	methods: {
		async loadFavorites() {
			this.isLoading = true;
			try {
				const response = await axios.get('/api/favorites');
				const data = response.data;

				if (data.success) {
					this.favorites = data.favorites;
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Load favorites error:', error);
				// Глобальный обработчик покажет toast автоматически
			} finally {
				this.isLoading = false;
			}
		},
		
		formatDate(dateString) {
			return new Date(dateString).toLocaleDateString('ru-RU', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			});
		},
		
		selectFavorite(favorite) {
			this.selectedFavorite = favorite;
			
			// Скролл к деталям на мобильных устройствах
			this.$nextTick(() => {
				// Проверяем, что это мобильное устройство (экран меньше lg)
				if (window.innerWidth < 992) {
					const targetElement = document.getElementById('favorite-details-section');
					
					if (targetElement) {
						targetElement.scrollIntoView({
							behavior: 'smooth',
							block: 'start'
						});
					}
				}
			});
			
			// Инициализируем кнопки для выбранного товара
			this.$nextTick(() => {
				this.initializeFavoriteButtons();
			});
		},
		
		initializeFavoriteButtons() {
			// Найдем кнопки избранного (не инициализированные)
			const favoriteButtons = document.querySelectorAll('[data-favorite-button]:not(.favorite-initialized)');
			
			favoriteButtons.forEach(button => {
				const listingId = parseInt(button.dataset.listingId);
				const initialIsFavorite = button.dataset.isFavorite === 'true';
				
				if (listingId) {
					// Создаем Vue приложение для кнопки
					const app = createApp(FavoriteButton, {
						listingId: listingId,
						initialIsFavorite: initialIsFavorite
					});
					
					// Добавляем слушатель события удаления из избранного
					app.config.globalProperties.$onFavoriteRemoved = (removedListingId) => {
						this.onFavoriteRemoved(removedListingId);
					};
					
					app.mount(button);
					
					// Помечаем как инициализированную
					button.classList.add('favorite-initialized');
				}
			});
		},
		
		handleImageError(event) {
			event.target.src = '/images/no-image.png';
		},
		
		getItemName(listing) {
			// Используем ту же логику что и в маркетплейсе
			return listing.item?.name_ru || listing.inventory_item_name || listing.market_hash_name || listing.name || 'Неизвестный предмет';
		},
		
		getIconUrl(listing) {
			// Для листингов используем inventory_icon_url
			const iconUrl = listing.inventory_icon_url;
			
			if (iconUrl) {
				// Проверяем, уже ли это полный URL
				if (iconUrl.startsWith('http')) {
					return iconUrl;
				}
				// Если нет, добавляем префикс Steam
				return 'https://community.steamstatic.com/economy/image/' + iconUrl;
			}
			if (listing.image_url) {
				return listing.image_url;
			}
			return '/images/no-image.png';
		},
		
		
		getParsedDescriptions(listing) {
			if (!listing.inventory_descriptions) return [];
			if (typeof listing.inventory_descriptions === 'string') {
				try {
					return JSON.parse(listing.inventory_descriptions);
				} catch (e) {
					return [];
				}
			}
			return listing.inventory_descriptions;
		},
		
		getItemDescription(listing) {
			const descriptions = this.getParsedDescriptions(listing);
			const descriptionItem = descriptions.find(desc => desc.name === 'description');
			return descriptionItem ? descriptionItem.value : null;
		},
		
		getWearCondition(floatValue) {
			if (floatValue <= 0.07) return 'Прямо с завода';
			if (floatValue <= 0.15) return 'Немного поношенное';
			if (floatValue <= 0.38) return 'После полевых испытаний';
			if (floatValue <= 0.45) return 'Поношенное';
			return 'Закалённое в боях';
		},
		
		getParsedStickers(listing) {
			if (!listing || !listing.stickers) return [];
			if (!Array.isArray(listing.stickers)) return [];
			return listing.stickers;
		},
		
		isStatTrak(listing) {
			if (!listing || !listing.market_hash_name) return false;
			return listing.market_hash_name.includes('StatTrak™');
		},
		
		isSouvenir(listing) {
			if (!listing || !listing.market_hash_name) return false;
			return listing.market_hash_name.includes('Souvenir');
		},
		
		
		
		// Метод для обновления списка при удалении из избранного
		onFavoriteRemoved(listingId) {
			this.favorites = this.favorites.filter(favorite => favorite.listing.id !== listingId);
			
			// Если удаленный товар был выбран, сбрасываем выбор
			if (this.selectedFavorite && this.selectedFavorite.listing.id === listingId) {
				this.selectedFavorite = null;
			}
			
			// Автоматически выбираем первый товар если есть
			if (this.favorites.length > 0 && !this.selectedFavorite) {
				this.selectedFavorite = this.favorites[0];
			}
		}
	},

	async mounted() {
		await this.loadFavorites();
		
		// Автоматически выбираем первый товар если есть
		if (this.favorites.length > 0) {
			this.selectedFavorite = this.favorites[0];
		}
		
		// Инициализируем кнопки избранного после загрузки данных
		this.$nextTick(() => {
			this.initializeFavoriteButtons();
		});
		
		// Добавляем глобальный слушатель события удаления из избранного
		window.addEventListener('favoriteRemoved', (event) => {
			this.onFavoriteRemoved(event.detail.listingId);
		});
		
	},
	
	beforeUnmount() {
		// Убираем слушатель при уничтожении компонента
		window.removeEventListener('favoriteRemoved', (event) => {
			this.onFavoriteRemoved(event.detail.listingId);
		});
	},
	
	watch: {
		favorites: {
			handler(newFavorites) {
				// Автоматически выбираем первый товар при загрузке если нет выбранного
				if (newFavorites.length > 0 && !this.selectedFavorite) {
					this.selectedFavorite = newFavorites[0];
				}
			},
			immediate: true
		}
	}
}
</script>