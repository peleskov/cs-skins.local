<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<div class="d-flex justify-content-between align-items-center">
				<h3>Steam Инвентарь</h3>
				<button v-if="inventoryData" class="btn theme-outline btn-sm" @click="syncInventory"
					:disabled="isSyncing || syncCooldownRemaining > 0">
					<i :class="['ri-refresh-line', 'me-1', { 'ri-spin': isSyncing }]"></i>
					<span v-if="isSyncing">Обновление...</span>
					<span v-else-if="syncCooldownRemaining > 0">Обновить через {{
						formatCooldownTime(syncCooldownRemaining) }}</span>
					<span v-else>Обновить инвентарь</span>
				</button>
			</div>
		</div>
		<div v-if="isLoading && items.length === 0" class="text-center py-5">
			<div class="loader-gif">
				<div class="radar-ring"></div>
				<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
			</div>
			<p class="mt-3">Загружаем инвентарь...</p>
		</div>

		<!-- Inventory Items -->
		<div v-else-if="inventoryData" class="inventory-items">
			<!-- Tabs Navigation -->
			<ul class="nav nav-tabs tab-style1 mb-4" id="inventoryTab" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeInventoryTab === 'available' }" 
					        id="available-tab" data-bs-toggle="tab" data-bs-target="#available"
					        type="button" role="tab" @click="setActiveInventoryTab('available')">
						Доступные для торговли 
						<span class="badge bg-body-secondary ms-2">{{ availableItems.length }}</span>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeInventoryTab === 'listed' }" 
					        id="listed-tab" data-bs-toggle="tab" data-bs-target="#listed"
					        type="button" role="tab" @click="setActiveInventoryTab('listed')">
						В продаже 
						<span class="badge bg-body-secondary ms-2">{{ listedItems.length }}</span>
					</button>
				</li>
			</ul>
			<div class="tab-content product-details-content" id="inventoryTabContent">
				<!-- Объединенный компонент для обеих вкладок -->
				<div class="tab-pane fade" :class="{ 'show active': activeInventoryTab === 'available' }" 
				     id="available" role="tabpanel" aria-labelledby="available-tab" tabindex="0">
					<div class="row g-4 d-flex flex-lg-row flex-column-reverse">
						<div class="col-lg-7 col-12">
							<div v-if="currentItems.length > 0" class="mb-4">
								<div class="row g-3">
									<div v-for="item in currentItems" :key="item.steam_asset_id" class="col-lg-4 col-md-6">
										<div @click="selectItem(item)" :class="getItemClasses(item)">
											<img class="img-fluid inventory-img h-auto" :src="getIconUrl(item)"
												:alt="item.market_hash_name" @error="handleImageError">
											<h6 class="mt-2">{{ getItemName(item) }}</h6>
											<small class="text-muted">{{ item.type || 'Unknown' }}</small>
										</div>
									</div>
								</div>
							</div>
							<div v-else class="text-center py-5">
								<i class="ri-box-3-line display-4 text-muted mb-3"></i>
								<h6>{{ getEmptyStateMessage() }}</h6>
								<p class="text-muted mb-0">{{ getEmptyStateDescription() }}</p>
							</div>
						</div>
						<div class="col-lg-5 col-12" :id="getDetailsSectionId()">
							<div class="item-details sticky-top" v-if="selectedItem">
								<h5 class="item-name">{{ getItemName(selectedItem) }}</h5>
								<div class="item-type text-muted mb-3">{{ selectedItem.type || 'Unknown' }}</div>
								
								<!-- Изображение предмета -->
								<div class="item-preview mb-3">
									<img :src="getIconUrl(selectedItem)" :alt="selectedItem.market_hash_name" 
										 class="img-fluid" @error="handleImageError">
								</div>
								
								<!-- Описание предмета -->
								<div v-if="getItemDescription(selectedItem)" class="item-description mb-3">
									<div class="description-text text-muted" v-html="getItemDescription(selectedItem)"></div>
								</div>
								
								<!-- Float значение и паттерн -->
								<div v-if="selectedItem.float_value" class="item-wear mb-3">
									<div class="wear-info">
										<strong>Float:</strong> {{ selectedItem.float_value.toFixed(6) }}
									</div>
									<div v-if="selectedItem.pattern_index" class="pattern-info mt-2">
										<strong>Паттерн:</strong> #{{ selectedItem.pattern_index }}
									</div>
								</div>
								
								<!-- Стикеры -->
								<div v-if="selectedItem.parsed_stickers && selectedItem.parsed_stickers.length > 0" class="item-stickers mb-3">
									<strong>Стикеры:</strong>
									<div class="sticker-list mt-2">
										<div v-for="(sticker, index) in selectedItem.parsed_stickers" :key="index" class="sticker-item">
											<img v-if="sticker.img" :src="sticker.img" :alt="sticker.name" class="sticker-img">
											<span>{{ sticker.name }}</span>
										</div>
									</div>
								</div>
								
								<!-- Теги -->
								<div v-if="selectedItem.structured_tags && selectedItem.structured_tags.length > 0" class="item-tags mb-3">
									<strong>Информация о предмете:</strong>
									<div class="tags-list mt-2">
										<div v-for="tag in selectedItem.structured_tags" :key="tag.id" class="tag-item d-flex justify-content-between mb-1">
											<span class="tag-category text-muted">{{ tag.category_name }}:</span>
											<span class="tag-name fw-medium" :style="{ color: tag.color ? '#' + tag.color : '' }">
												{{ tag.display_name }}
											</span>
										</div>
									</div>
								</div>
								
								<!-- Кнопки действий -->
								<div class="item-actions mt-4">
									<div v-if="activeInventoryTab === 'available'">
										<button v-if="selectedItem.tradable && selectedItem.marketable && hasTradeUrl && !selectedItem.is_listed" 
											class="btn theme-btn w-100 mb-2"
											:disabled="isCreatingListing"
											@click="openSellModal(selectedItem)">
											<i v-if="isCreatingListing" class="ri-loader-4-line me-2 ri-spin"></i>
											<i v-else class="ri-price-tag-3-line me-2"></i>
											{{ isCreatingListing ? 'Создаем листинг...' : 'Продать' }}
										</button>
										<div v-else-if="!hasTradeUrl" class="alert alert-light mb-0 small">
											<i class="ri-information-line me-2"></i>Для того чтобы выставить на продажу нужно добавить Trade URL в настройках <a href="/profile#profile">профиля</a>
										</div>
										<div v-else-if="!selectedItem.tradable || !selectedItem.marketable" class="alert alert-secondary mb-0">
											<i class="ri-lock-line me-2"></i>Данный предмет нельзя продать
										</div>
									</div>
									<div v-if="activeInventoryTab === 'listed'">
										<div class="alert alert-info mb-0">
											<i class="ri-information-line me-2"></i>Этот предмет выставлен на продажу
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="tab-pane fade" :class="{ 'show active': activeInventoryTab === 'listed' }" 
				     id="listed" role="tabpanel" aria-labelledby="listed-tab" tabindex="0">
					<!-- Тот же контент, что и в available -->
					<div class="row g-4 d-flex flex-lg-row flex-column-reverse">
						<div class="col-lg-7 col-12">
							<div v-if="currentItems.length > 0" class="mb-4">
								<div class="row g-3">
									<div v-for="item in currentItems" :key="item.steam_asset_id" class="col-lg-4 col-md-6">
										<div @click="selectItem(item)" :class="getItemClasses(item)">
											<img class="img-fluid inventory-img h-auto" :src="getIconUrl(item)"
												:alt="item.market_hash_name" @error="handleImageError">
											<h6 class="mt-2">{{ getItemName(item) }}</h6>
											<small class="text-muted">{{ item.type || 'Unknown' }}</small>
										</div>
									</div>
								</div>
							</div>
							<div v-else class="text-center py-5">
								<i class="ri-box-3-line display-4 text-muted mb-3"></i>
								<h6>{{ getEmptyStateMessage() }}</h6>
								<p class="text-muted mb-0">{{ getEmptyStateDescription() }}</p>
							</div>
						</div>
						<div class="col-lg-5 col-12" :id="getDetailsSectionId()">
							<div class="item-details sticky-top" v-if="selectedItem">
								<h5 class="item-name">{{ getItemName(selectedItem) }}</h5>
								<div class="item-type text-muted mb-3">{{ selectedItem.type || 'Unknown' }}</div>
								
								<!-- Изображение предмета -->
								<div class="item-preview mb-3">
									<img :src="getIconUrl(selectedItem)" :alt="selectedItem.market_hash_name" 
										 class="img-fluid" @error="handleImageError">
								</div>
								
								<!-- Описание предмета -->
								<div v-if="getItemDescription(selectedItem)" class="item-description mb-3">
									<div class="description-text text-muted" v-html="getItemDescription(selectedItem)"></div>
								</div>
								
								<!-- Float значение и паттерн -->
								<div v-if="selectedItem.float_value" class="item-wear mb-3">
									<div class="wear-info">
										<strong>Float:</strong> {{ selectedItem.float_value.toFixed(6) }}
									</div>
									<div v-if="selectedItem.pattern_index" class="pattern-info mt-2">
										<strong>Паттерн:</strong> #{{ selectedItem.pattern_index }}
									</div>
								</div>
								
								<!-- Стикеры -->
								<div v-if="selectedItem.parsed_stickers && selectedItem.parsed_stickers.length > 0" class="item-stickers mb-3">
									<strong>Стикеры:</strong>
									<div class="sticker-list mt-2">
										<div v-for="(sticker, index) in selectedItem.parsed_stickers" :key="index" class="sticker-item">
											<img v-if="sticker.img" :src="sticker.img" :alt="sticker.name" class="sticker-img">
											<span>{{ sticker.name }}</span>
										</div>
									</div>
								</div>
								
								<!-- Теги -->
								<div v-if="selectedItem.structured_tags && selectedItem.structured_tags.length > 0" class="item-tags mb-3">
									<strong>Информация о предмете:</strong>
									<div class="tags-list mt-2">
										<div v-for="tag in selectedItem.structured_tags" :key="tag.id" class="tag-item d-flex justify-content-between mb-1">
											<span class="tag-category text-muted">{{ tag.category_name }}:</span>
											<span class="tag-name fw-medium" :style="{ color: tag.color ? '#' + tag.color : '' }">
												{{ tag.display_name }}
											</span>
										</div>
									</div>
								</div>
								
								<!-- Кнопки действий -->
								<div class="item-actions mt-4">
									<div v-if="activeInventoryTab === 'available'">
										<button v-if="selectedItem.tradable && selectedItem.marketable && hasTradeUrl && !selectedItem.is_listed" 
											class="btn theme-btn w-100 mb-2"
											:disabled="isCreatingListing"
											@click="openSellModal(selectedItem)">
											<i v-if="isCreatingListing" class="ri-loader-4-line me-2 ri-spin"></i>
											<i v-else class="ri-price-tag-3-line me-2"></i>
											{{ isCreatingListing ? 'Создаем листинг...' : 'Продать' }}
										</button>
										<div v-else-if="!hasTradeUrl" class="alert alert-light mb-0 small">
											<i class="ri-information-line me-2"></i>Для того чтобы выставить на продажу нужно добавить Trade URL в настройках <a href="/profile#profile">профиля</a>
										</div>
										<div v-else-if="!selectedItem.tradable || !selectedItem.marketable" class="alert alert-secondary mb-0">
											<i class="ri-lock-line me-2"></i>Данный предмет нельзя продать
										</div>
									</div>
									<div v-if="activeInventoryTab === 'listed'">
										<div class="alert alert-info mb-0">
											<i class="ri-information-line me-2"></i>Этот предмет выставлен на продажу
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Global Empty State - показывается только когда инвентарь вообще не загружен -->
				<div v-if="!inventoryData && !isLoading" class="text-center py-5">
					<i class="ri-box-3-line display-4 text-muted mb-3"></i>
					<h6>Инвентарь пуст</h6>
					<p class="text-muted mb-0">Убедитесь, что ваш Steam инвентарь публичный</p>
				</div>
			</div>
		</div>



		<!-- Sell Type Modal -->
		<div class="modal fade" id="sellTypeModal" tabindex="-1" aria-labelledby="sellTypeModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="sellTypeModalLabel">
							<i class="ri-price-tag-3-line me-2"></i>Выберите способ продажи
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div v-if="itemToSell" class="mb-4">
							<div class="d-flex align-items-center">
								<img :src="getIconUrl(itemToSell)" :alt="itemToSell.market_hash_name" 
									 class="me-3" style="width: 64px; height: 64px;" @error="handleImageError">
								<div>
									<h6 class="mb-1">{{ getItemName(itemToSell) }}</h6>
									<small class="text-muted">{{ itemToSell.type || 'Unknown' }}</small>
								</div>
							</div>
						</div>
						
						<div class="row g-3">
							<!-- Продать боту -->
							<div class="col-12">
								<div class="card h-100 sell-option" @click="sellToBot" style="cursor: pointer;">
									<div class="card-body d-flex align-items-center">
										<div class="sell-icon me-3">
											<i class="ri-robot-line text-primary" style="font-size: 2rem;"></i>
										</div>
										<div class="flex-grow-1">
											<h6 class="card-title mb-1">Продать боту</h6>
											<p class="card-text text-muted mb-0">
												Мгновенный выкуп за 20-50% от рыночной стоимости
											</p>
										</div>
										<div class="sell-arrow">
											<i class="ri-arrow-right-line text-muted"></i>
										</div>
									</div>
								</div>
							</div>
							
							<!-- Добавить в маркетплейс -->
							<div class="col-12">
								<div class="card h-100 sell-option" 
									 @click="!isCreatingListing ? addToMarketplace() : null" 
									 :class="{ 'opacity-50': isCreatingListing }"
									 style="cursor: pointer;">
									<div class="card-body d-flex align-items-center">
										<div class="sell-icon me-3">
											<i v-if="isCreatingListing" class="ri-loader-4-line text-success ri-spin" style="font-size: 2rem;"></i>
											<i v-else class="ri-store-2-line text-success" style="font-size: 2rem;"></i>
										</div>
										<div class="flex-grow-1">
											<h6 class="card-title mb-1">
												{{ isCreatingListing ? 'Создаем листинг...' : 'Добавить в маркетплейс' }}
											</h6>
											<p class="card-text text-muted mb-0">
												{{ isCreatingListing ? 'Получаем скриншот предмета' : 'Установите свою цену, комиссия 5%' }}
											</p>
										</div>
										<div class="sell-arrow">
											<i class="ri-arrow-right-line text-muted"></i>
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
</template>

<script>
import { formatPrice } from '../../utils/helpers';
import { getApiHeaders, handleApiError } from '../../utils/helpers';

export default {
	name: 'ProfileInventory',
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
			items: [],
			stats: {},
			isLoading: false,
			selectedItem: null,
			hasTradeUrl: false,
			itemToSell: null,
			inventoryData: null,
			isSyncing: false,
			syncCooldownRemaining: 0,
			cooldownTimer: null,
			activeInventoryTab: 'available',
			isCreatingListing: false
		}
	},
	computed: {
		availableItems() {
			return this.items.filter(item => item.tradable && item.marketable && !item.is_listed);
		},
		listedItems() {
			return this.items.filter(item => item.is_listed);
		},
		currentItems() {
			return this.activeInventoryTab === 'available' ? this.availableItems : this.listedItems;
		}
	},
	methods: {
		async loadInventoryData() {
			this.isLoading = true;
			try {
				const response = await fetch('/inventory', {
					headers: getApiHeaders()
				});
				
				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }


				const data = await response.json();

				if (data.success) {
					this.inventoryData = data.data;
					this.items = data.data.items;
					this.stats = data.data.stats;
					this.hasTradeUrl = data.data.has_trade_url;

					// Проверяем, нужно ли запустить кулдаун для синхронизации
					if (data.data.stats && data.data.stats.last_sync) {
						const lastSyncTime = new Date(data.data.stats.last_sync);
						const now = new Date();
						const timeDiff = now - lastSyncTime;
						const cooldownTime = 2 * 60 * 1000; // 2 минуты в мс

						if (timeDiff < cooldownTime) {
							const remainingSeconds = Math.ceil((cooldownTime - timeDiff) / 1000);
							this.startSyncCooldown(remainingSeconds);
						}
					}

					// Если инвентарь пустой, показываем сообщение
					if (data.data.items.length === 0) {
						window.toast.info('Ваш Steam инвентарь пуст или приватный. Убедитесь, что инвентарь публичный в настройках Steam. После изменения настроек инвентаря в Steam попробуйте еще раз через 10-15 минут.', {
							timeout: 10000
						});
					}
				} else {
					// Если API вернул ошибку, устанавливаем пустой инвентарь
					this.inventoryData = { items: [], stats: {} };
					this.items = [];
					this.stats = {};
					window.toast.error(data.message || 'Не удалось загрузить инвентарь');
				}
			} catch (error) {
				console.error('Error loading inventory:', error);
				// В любом случае устанавливаем пустые данные чтобы убрать лоадер
				this.inventoryData = { items: [], stats: {} };
				this.items = [];
				this.stats = {};
				window.toast.error(handleApiError(error));
			} finally {
				this.isLoading = false;
			}
		},

		async syncInventory() {
			this.isSyncing = true;

			try {
				const response = await fetch('/inventory/sync', {
					method: 'POST',
					headers: getApiHeaders()
				});
				
				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }


				const data = await response.json();

				if (data.success) {
					window.toast.success(`Инвентарь обновлен! Загружено предметов: ${data.data.items_count}`);

					// Обновляем данные без перезагрузки страницы
					await this.loadInventoryData();

					// Запускаем кулдаун на 2 минуты
					this.startSyncCooldown(120); // 2 минуты = 120 секунд
				} else {
					window.toast.error(data.message);

					// Если есть информация о кулдауне, запускаем его
					if (data.data && data.data.cooldown_remaining) {
						this.startSyncCooldown(data.data.cooldown_remaining);
					}
				}
			} catch (error) {
				console.error('Sync error:', error);
				window.toast.error(handleApiError(error));
			} finally {
				this.isSyncing = false;
			}
		},

		startSyncCooldown(seconds) {
			this.syncCooldownRemaining = seconds;

			// Очищаем предыдущий таймер если он есть
			if (this.cooldownTimer) {
				clearInterval(this.cooldownTimer);
			}

			this.cooldownTimer = setInterval(() => {
				this.syncCooldownRemaining--;

				if (this.syncCooldownRemaining <= 0) {
					clearInterval(this.cooldownTimer);
					this.cooldownTimer = null;
				}
			}, 1000);
		},

		formatCooldownTime(seconds) {
			const minutes = Math.floor(seconds / 60);
			const remainingSeconds = seconds % 60;

			if (minutes > 0) {
				return `${minutes} мин ${remainingSeconds} сек`;
			}
			return `${remainingSeconds} сек`;
		},

		setActiveInventoryTab(tab) {
			this.activeInventoryTab = tab;
			this.selectedItem = null;
		},
		
		getItemClasses(item) {
			const baseClasses = 'h-100 inventory-item text-center position-relative';
			const activeClass = this.selectedItem && this.selectedItem.steam_asset_id === item.steam_asset_id ? 'active' : '';
			const listedClass = this.activeInventoryTab === 'listed' ? 'item-listed' : '';
			
			return [baseClasses, activeClass, listedClass].filter(Boolean).join(' ');
		},
		
		getDetailsSectionId() {
			return this.activeInventoryTab === 'available' ? 'item-details-section' : 'listed-item-details-section';
		},
		
		getEmptyStateMessage() {
			return this.activeInventoryTab === 'available' 
				? 'Нет доступных предметов для торговли' 
				: 'Нет предметов в продаже';
		},
		
		getEmptyStateDescription() {
			return this.activeInventoryTab === 'available' 
				? 'Убедитесь, что ваш Steam инвентарь публичный и содержит торгуемые предметы' 
				: 'Выставьте предметы на продажу в разделе "Доступные для торговли"';
		},
		
		getItemName(item) {
			return item.item?.name_ru || item.market_hash_name;
		},
		
		getIconUrl(item) {
			if (item.icon_url) {
				if (item.icon_url.startsWith('http')) {
					return item.icon_url;
				}
				return 'https://community.steamstatic.com/economy/image/' + item.icon_url;
			}
			return '/images/skin_no_image.svg';
		},
		
		handleImageError(event) {
			event.target.src = '/images/skin_no_image.svg';
		},
		
		selectItem(item) {
			this.selectedItem = item;
			this.scrollToDetailsOnMobile();
		},
		
		scrollToDetailsOnMobile() {
			this.$nextTick(() => {
				if (window.innerWidth < 992) {
					const targetElement = document.getElementById(this.getDetailsSectionId());
					if (targetElement) {
						targetElement.scrollIntoView({
							behavior: 'smooth',
							block: 'start'
						});
					}
				}
			});
		},
		
		getParsedDescriptions(item) {
			if (!item.descriptions) return [];
			if (typeof item.descriptions === 'string') {
				try {
					return JSON.parse(item.descriptions);
				} catch (e) {
					return [];
				}
			}
			return item.descriptions;
		},
		
		getItemDescription(item) {
			const descriptions = this.getParsedDescriptions(item);
			const descriptionItem = descriptions.find(desc => desc.name === 'description');
			return descriptionItem ? descriptionItem.value : null;
		},
		
		openSellModal(item) {
			this.itemToSell = item;
			const modal = new bootstrap.Modal(document.getElementById('sellTypeModal'));
			modal.show();
		},
		
		sellToBot() {
			// Закрываем модальное окно
			const modal = bootstrap.Modal.getInstance(document.getElementById('sellTypeModal'));
			if (modal) {
				modal.hide();
			}
			
			// Показываем уведомление о разработке
			window.toast.info('Функция "Продать боту" находится в разработке и будет доступна в ближайшее время.');
		},
		
		async addToMarketplace() {
			if (!this.itemToSell) return;
			
			// Закрываем модальное окно
			const modal = bootstrap.Modal.getInstance(document.getElementById('sellTypeModal'));
			if (modal) {
				modal.hide();
			}
			
			this.isCreatingListing = true;
			
			try {
				// Показываем уведомление о начале процесса
				window.toast.info('Создаем листинг и получаем скриншот предмета...', {
					timeout: 3000
				});
				
				// Отправляем запрос на создание листинга
				const response = await fetch('/inventory/create-listing', {
					method: 'POST',
					headers: getApiHeaders(),
					body: JSON.stringify({
						steam_asset_id: this.itemToSell.steam_asset_id
					})
				});
				
				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }

				const data = await response.json();
				
				if (data.success) {
					// Помечаем предмет как выставленный на продажу
					this.itemToSell.is_listed = true;
					
					// Обновляем предмет в массиве items
					const itemIndex = this.items.findIndex(item => item.steam_asset_id === this.itemToSell.steam_asset_id);
					if (itemIndex !== -1) {
						this.items[itemIndex].is_listed = true;
					}
					
					// Если это был выбранный предмет, выбираем первый доступный или убираем выбор
					if (this.selectedItem && this.selectedItem.steam_asset_id === this.itemToSell.steam_asset_id) {
						if (this.availableItems.length > 0) {
							this.selectedItem = this.availableItems[0];
						} else {
							this.selectedItem = null;
						}
					}
					
					window.toast.success('Предмет добавлен в торговлю!');
				} else {
					window.toast.error(data.message || 'Не удалось создать листинг');
				}
			} catch (error) {
				console.error('Create listing error:', error);
				window.toast.error(handleApiError(error));
			} finally {
				this.isCreatingListing = false;
			}
		}
	},
	mounted() {
		// Загружаем данные инвентаря при загрузке компонента
		this.loadInventoryData();
	},

	beforeUnmount() {
		// Очищаем таймер кулдауна
		if (this.cooldownTimer) {
			clearInterval(this.cooldownTimer);
		}
	},

	watch: {
		activeInventoryTab: {
			handler(newTab) {
				// Автовыбираем первый предмет при смене таба
				if (newTab === 'available' && this.availableItems.length > 0) {
					this.selectedItem = this.availableItems[0];
				} else if (newTab === 'listed' && this.listedItems.length > 0) {
					this.selectedItem = this.listedItems[0];
				}
			}
		},
		currentItems: {
			handler(newItems) {
				// Автоматически выбираем первый предмет при загрузке
				if (newItems.length > 0 && !this.selectedItem) {
					this.selectedItem = newItems[0];
				}
			},
			immediate: true
		}
	}
}
</script>