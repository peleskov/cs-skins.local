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
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeInventoryTab === 'listed' }" 
					        id="listed-tab" data-bs-toggle="tab" data-bs-target="#listed"
					        type="button" role="tab" @click="setActiveInventoryTab('listed')">
						В продаже 
					</button>
				</li>
			</ul>
			<div class="tab-content product-details-content" id="inventoryTabContent">
				<div class="tab-pane fade" :class="{ 'show active': activeInventoryTab === 'available' }" 
				     id="available" role="tabpanel" aria-labelledby="available-tab" tabindex="0">
					<div class="row g-4 d-flex flex-lg-row flex-column-reverse">
						<div class="col-lg-7 col-12">
							<div v-if="availableItems.length > 0" class="mb-4">
								<div class="row g-3">
									<div v-for="item in availableItems" :key="item.steam_asset_id" class="col-lg-4 col-md-6">
										<div @click="selectItem(item)" 
											 :class="['h-100 inventory-item text-center position-relative', { 'active': selectedItem && selectedItem.steam_asset_id === item.steam_asset_id }]">
											<img class="img-fluid inventory-img h-auto" :src="getIconUrl(item)"
												:alt="item.market_hash_name" @error="handleImageError">
											<h6 class="mt-2">{{ getItemName(item) }}</h6>
											<small class="text-muted">{{ item.type || 'Unknown' }}</small>
										</div>
									</div>
								</div>
							</div>
							
						</div>
						<div class="col-lg-5 col-12" id="item-details-section">
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
								
								<!-- Износ и паттерн -->
								<div v-if="selectedItem.float_value" class="item-wear mb-3">
									<div class="wear-info">
										<strong>Износ:</strong> {{ selectedItem.wear_condition || getWearCondition(selectedItem.float_value) }}
										<div class="float-value">Float: {{ selectedItem.float_value.toFixed(6) }}</div>
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
								<div v-if="getParsedTags(selectedItem).length > 0" class="item-tags mb-3">
									<strong>Информация о предмете:</strong>
									<div class="tags-list mt-2">
										<div v-for="tag in getParsedTags(selectedItem)" :key="tag.internal_name" class="tag-item d-flex justify-content-between mb-1">
											<span class="tag-category text-muted">{{ tag.localized_category_name }}:</span>
											<span class="tag-name fw-medium">
												{{ tag.localized_tag_name }}
											</span>
										</div>
									</div>
								</div>
								
								<!-- Кнопки действий -->
								<div class="item-actions mt-4">
									<!-- Доступные для торговли предметы -->
									<div v-if="activeInventoryTab === 'available'">
										<button v-if="selectedItem.tradable && selectedItem.marketable && hasTradeUrl && !selectedItem.is_listed" 
											class="btn theme-btn w-100 mb-2"
											:disabled="isCreatingListing"
											@click="openSellModal(selectedItem)">
											<i v-if="isCreatingListing" class="ri-loader-4-line me-2 ri-spin"></i>
											<i v-else class="ri-price-tag-3-line me-2"></i>
											{{ isCreatingListing ? 'Создаем листинг...' : 'Продать' }}
										</button>
										<div v-else-if="!hasTradeUrl" 
											 class="alert alert-light mb-0 small">
											<i class="ri-information-line me-2"></i>Для того чтобы выставить на продажу нужно добавить Trade URL в настройках <a href="/profile#profile">профиля</a>
										</div>
										<div v-else-if="!selectedItem.tradable || !selectedItem.marketable" 
											 class="alert alert-secondary mb-0">
											<i class="ri-lock-line me-2"></i>Данный предмет нельзя продать
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="tab-pane fade" :class="{ 'show active': activeInventoryTab === 'listed' }" 
				     id="listed" role="tabpanel" aria-labelledby="listed-tab" tabindex="0">
					<div class="row g-4 d-flex flex-lg-row flex-column-reverse">
						<div class="col-lg-7 col-12">
							<div v-if="listedItems.length > 0" class="mb-4">
								<div class="row g-3">
									<div v-for="item in listedItems" :key="item.steam_asset_id" class="col-lg-4 col-md-6">
										<div @click="selectItem(item)" 
											 :class="['h-100 inventory-item text-center position-relative item-listed', { 'active': selectedItem && selectedItem.steam_asset_id === item.steam_asset_id }]">
											<img class="img-fluid inventory-img h-auto" :src="getIconUrl(item)"
												:alt="item.market_hash_name" @error="handleImageError">
											<h6 class="mt-2">{{ getItemName(item) }}</h6>
											<small class="text-muted">{{ item.type || 'Unknown' }}</small>
										</div>
									</div>
								</div>
							</div>
							
						</div>
						<div class="col-lg-5 col-12" id="listed-item-details-section">
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
								
								<!-- Износ и паттерн -->
								<div v-if="selectedItem.float_value" class="item-wear mb-3">
									<div class="wear-info">
										<strong>Износ:</strong> {{ selectedItem.wear_condition || getWearCondition(selectedItem.float_value) }}
										<div class="float-value">Float: {{ selectedItem.float_value.toFixed(6) }}</div>
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
								<div v-if="getParsedTags(selectedItem).length > 0" class="item-tags mb-3">
									<strong>Информация о предмете:</strong>
									<div class="tags-list mt-2">
										<div v-for="tag in getParsedTags(selectedItem)" :key="tag.internal_name" class="tag-item d-flex justify-content-between mb-1">
											<span class="tag-category text-muted">{{ tag.localized_category_name }}:</span>
											<span class="tag-name fw-medium">
												{{ tag.localized_tag_name }}
											</span>
										</div>
									</div>
								</div>
								
								<!-- Кнопки действий -->
								<div class="item-actions mt-4">
									<!-- Предметы в продаже -->
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
				
				<!-- Global Empty State -->
				<div v-if="items.length === 0" class="text-center py-5">
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
import { useToast } from "vue-toastification";
import { formatPrice } from '../../utils/helpers';

export default {
	name: 'ProfileInventory',
	setup() {
		const toast = useToast();
		return { toast, formatPrice };
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
		filteredItems() {
			// Поскольку фильтров нет, просто возвращаем все предметы
			return this.items;
		},
		tradableItems() {
			// Предметы которые можно обменять и продать
			return this.items.filter(item => item.tradable && item.marketable);
		},
		availableItems() {
			// Предметы доступные для торговли (не выставлены на продажу)
			return this.items.filter(item => item.tradable && item.marketable && !item.is_listed);
		},
		listedItems() {
			// Предметы выставленные на продажу
			return this.items.filter(item => item.is_listed);
		},
		nonTradableItems() {
			// Предметы которые нельзя обменять или продать
			return this.items.filter(item => !item.tradable || !item.marketable);
		}
	},
	methods: {
		async loadInventoryData() {
			this.isLoading = true;
			try {
				const response = await fetch('/inventory', {
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					}
				});

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
						this.toast.info('Ваш Steam инвентарь пуст или приватный. Убедитесь, что инвентарь публичный в настройках Steam. После изменения настроек инвентаря в Steam попробуйте еще раз через 10-15 минут.', {
							timeout: 10000
						});
					}
				} else {
					// Если API вернул ошибку, устанавливаем пустой инвентарь
					this.inventoryData = { items: [], stats: {} };
					this.items = [];
					this.stats = {};
					this.toast.error(data.message || 'Не удалось загрузить инвентарь');
				}
			} catch (error) {
				console.error('Error loading inventory:', error);
				// В любом случае устанавливаем пустые данные чтобы убрать лоадер
				this.inventoryData = { items: [], stats: {} };
				this.items = [];
				this.stats = {};
				this.toast.error('Не удалось загрузить инвентарь. Убедитесь, что ваш Steam инвентарь публичный и попробуйте ещё раз. После изменения настроек инвентаря в Steam попробуйте еще раз через 10-15 минут.', {
					timeout: 10000
				});
			} finally {
				this.isLoading = false;
			}
		},

		async syncInventory() {
			this.isSyncing = true;

			try {
				const response = await fetch('/inventory/sync', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					}
				});

				const data = await response.json();

				if (data.success) {
					this.toast.success(`Инвентарь обновлен! Загружено предметов: ${data.data.items_count}`);

					// Обновляем данные без перезагрузки страницы
					await this.loadInventoryData();

					// Запускаем кулдаун на 2 минуты
					this.startSyncCooldown(120); // 2 минуты = 120 секунд
				} else {
					this.toast.error(data.message);

					// Если есть информация о кулдауне, запускаем его
					if (data.data && data.data.cooldown_remaining) {
						this.startSyncCooldown(data.data.cooldown_remaining);
					}
				}
			} catch (error) {
				console.error('Sync error:', error);
				this.toast.error('Произошла ошибка при обновлении инвентаря');
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
			// Сбрасываем выбранный предмет при смене таба
			this.selectedItem = null;
		},
		getItemName(item) {
			return item.item?.name_ru || item.market_hash_name;
		},
		getItemPrice(item) {
			return item.item?.min_steam_price;
		},
		formatDate(dateString) {
			return new Date(dateString).toLocaleString('ru-RU');
		},
		getIconUrl(item) {
			if (item.icon_url) {
				// Проверяем, уже ли это полный URL
				if (item.icon_url.startsWith('http')) {
					return item.icon_url;
				}
				// Если нет, добавляем префикс Steam
				return 'https://community.steamstatic.com/economy/image/' + item.icon_url;
			}
			return '/images/no-image.png';
		},
		handleImageError(event) {
			event.target.src = '/images/no-image.png';
		},
		selectItem(item) {
			this.selectedItem = item;
			
			// Скролл к деталям на мобильных устройствах
			this.$nextTick(() => {
				// Проверяем, что это мобильное устройство (экран меньше lg)
				if (window.innerWidth < 992) {
					// Выбираем правильный элемент в зависимости от активной вкладки
					const targetId = this.activeInventoryTab === 'available' 
						? 'item-details-section' 
						: 'listed-item-details-section';
					const targetElement = document.getElementById(targetId);
					
					if (targetElement) {
						targetElement.scrollIntoView({
							behavior: 'smooth',
							block: 'start'
						});
					}
				}
			});
		},
		getParsedTags(item) {
			if (!item.tags) return [];
			if (typeof item.tags === 'string') {
				try {
					return JSON.parse(item.tags);
				} catch (e) {
					return [];
				}
			}
			return item.tags;
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
		getWearCondition(floatValue) {
			if (floatValue <= 0.07) return 'Прямо с завода';
			if (floatValue <= 0.15) return 'Немного поношенное';
			if (floatValue <= 0.38) return 'После полевых испытаний';
			if (floatValue <= 0.45) return 'Поношенное';
			return 'Закалённое в боях';
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
			this.toast.info('Функция "Продать боту" находится в разработке и будет доступна в ближайшее время.');
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
				this.toast.info('Создаем листинг и получаем скриншот предмета...', {
					timeout: 3000
				});
				
				// Отправляем запрос на создание листинга
				const response = await fetch('/inventory/create-listing', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						steam_asset_id: this.itemToSell.steam_asset_id
					})
				});
				
				const data = await response.json();
				
				if (data.success) {
					// Помечаем предмет как выставленный на продажу
					this.itemToSell.is_listed = true;
					
					// Если это выбранный предмет, обновляем его состояние
					if (this.selectedItem && this.selectedItem.steam_asset_id === this.itemToSell.steam_asset_id) {
						this.selectedItem.is_listed = true;
					}
					
					// Обновляем предмет в массиве items
					const itemIndex = this.items.findIndex(item => item.steam_asset_id === this.itemToSell.steam_asset_id);
					if (itemIndex !== -1) {
						this.items[itemIndex].is_listed = true;
					}
					
					this.toast.success('Предмет добавлен в торговлю!');
				} else {
					this.toast.error(data.message || 'Не удалось создать листинг');
				}
			} catch (error) {
				console.error('Create listing error:', error);
				this.toast.error('Произошла ошибка при создании листинга');
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
		availableItems: {
			handler(newItems) {
				// Автоматически выбираем первый доступный предмет при загрузке если активен таб "available"
				if (this.activeInventoryTab === 'available' && newItems.length > 0 && !this.selectedItem) {
					this.selectedItem = newItems[0];
				}
			},
			immediate: true
		},
		listedItems: {
			handler(newItems) {
				// Автоматически выбираем первый предмет в продаже если активен таб "listed"
				if (this.activeInventoryTab === 'listed' && newItems.length > 0 && !this.selectedItem) {
					this.selectedItem = newItems[0];
				}
			},
			immediate: true
		}
	}
}
</script>