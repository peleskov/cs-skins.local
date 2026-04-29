<template>
	<div id="Inventory" class="change-profile-content position-relative">
		<a href="/profile#profile" class="btn-to-profile d-lg-none"><i class="m-ico m-ico-back"></i>Назад</a>
		<div class="title">
			<div class="loader-line d-none d-lg-block"></div>
			<div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center">
				<h3 class="mb-4 mb-lg-0">Инвентарь</h3>
				<div class="btn-group d-flex gap-2">
					<a href="/trading-guide" target="_blank" class="btn theme-btn btn-sm"
						title="Руководство по торговле">
						<i class="ri-question-line me-1"></i>
						<span>Как начать торговлю</span>
					</a>
					<a v-if="inventoryData && !hasTradeUrl" href="/profile#profile" class="btn theme-btn btn-sm">
						<i class="ri-link me-1"></i>
						<span>Установить TradeUrl</span>
					</a>
					<button v-else-if="inventoryData" class="btn theme-outline btn-sm" @click="syncInventory"
						:disabled="isSyncing || syncCooldownRemaining > 0">
						<i :class="['ri-refresh-line', 'me-1', { 'ri-spin': isSyncing }]"></i>
						<span v-if="isSyncing">Обновление...</span>
						<span v-else-if="syncCooldownRemaining > 0">Обновить через {{
							getTimeRemaining(syncCooldownRemaining) }}</span>
						<span v-else>Обновить инвентарь</span>
					</button>
				</div>
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
				<li class="flex-fill flex-lg-grow-0 flex-lg-shrink-0 nav-item" role="presentation">
					<button class="nav-link d-flex align-items-center justify-content-center"
						:class="{ active: activeInventoryTab === 'available' }" id="available-tab" data-bs-toggle="tab"
						data-bs-target="#available" type="button" role="tab"
						@click="setActiveInventoryTab('available')">
						Доступные для торговли
						<span class="d-none d-lg-flex badge bg-body-secondary ms-2">{{ availableItems.length }}</span>
					</button>
				</li>
				<li class="flex-fill flex-lg-grow-0 flex-lg-shrink-0 nav-item" role="presentation">
					<button class="nav-link d-flex align-items-center justify-content-center"
						:class="{ active: activeInventoryTab === 'listed' }" id="listed-tab" data-bs-toggle="tab"
						data-bs-target="#listed" type="button" role="tab" @click="setActiveInventoryTab('listed')">
						В продаже
						<span class="d-none d-lg-flex badge bg-body-secondary ms-2">{{ listedItems.length }}</span>
					</button>
				</li>
			</ul>
			<div class="search-box mb-4">
				<div class="form-input">
					<input type="text" class="form-control search" placeholder="Поиск по названию..."
						v-model="searchQuery">
				</div>
			</div>
			<div class="tab-content product-details-content" id="inventoryTabContent">
				<!-- Объединенный компонент для обеих вкладок -->
				<div class="tab-pane fade" :class="{ 'show active': activeInventoryTab === 'available' }" id="available"
					role="tabpanel" aria-labelledby="available-tab" tabindex="0">
					<EmptyState v-if="currentItems.length === 0" icon="m-ico m-ico-empty-box"
						:title="getEmptyStateMessage()" :description="getEmptyStateDescription()" />
					<div v-else class="row g-4 d-flex flex-lg-row flex-column-reverse">
						<div class="col-lg-7 col-12">
							<div class="mb-4">
								<div class="row g-3">
									<div v-for="item in currentItems" :key="item.steam_asset_id" class="col-lg-4 col-6">
										<!-- Мобильная карточка — стиль маркетплейса -->
										<div class="m-listing-card d-lg-none h-100 d-flex flex-column" :class="getRarityClass(item)">
											<div class="m-lc-img">
												<img class="w-100" :src="getIconUrl(item)" :alt="item.market_hash_name"
													@error="handleImageError">
											</div>
											<div class="px-3 mt-2 m-lc-title">{{ getItemName(item) }}</div>
											<div class="px-3 m-lc-wear">{{ getItemType(item) }}</div>
											<div class="m-lc-actions px-3 pb-3 pt-2 mt-auto">
												<button v-if="!item.is_listed" type="button"
													class="btn m-inv-sell w-100" @click="openSellModal(item)">
													Продать
												</button>
												<div v-else class="alert alert-info mb-0 m-inv-status">
													<i class="ri-information-line me-2"></i>Этот предмет выставлен на
													продажу
												</div>
											</div>
										</div>
										<!-- Десктоп -->
										<div class="d-none d-lg-block" @click="selectItem(item)"
											:class="getItemClasses(item)">
											<img class="img-fluid inventory-img h-auto" :src="getIconUrl(item)"
												:alt="item.market_hash_name" @error="handleImageError">
											<h6 class="mt-2">{{ getItemName(item) }}</h6>
											<small class="text-muted">{{ getItemType(item) }}</small>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-lg-5 col-12 d-none d-lg-block" :id="getDetailsSectionId()">
							<ItemDetails :item="selectedItem" :active-tab="activeInventoryTab"
								:has-trade-url="hasTradeUrl" :is-creating-listing="isCreatingListing"
								:extension-active="extensionActive" :extension-checked="extensionChecked"
								@sell="openSellModal" />
						</div>
					</div>
				</div>
				<div class="tab-pane fade" :class="{ 'show active': activeInventoryTab === 'listed' }" id="listed"
					role="tabpanel" aria-labelledby="listed-tab" tabindex="0">
					<EmptyState v-if="currentItems.length === 0" icon="m-ico m-ico-empty-box"
						:title="getEmptyStateMessage()" :description="getEmptyStateDescription()"
						button-text="Перейти к доступным" @action="setActiveInventoryTab('available')" />
					<div v-else class="row g-4 d-flex flex-lg-row flex-column-reverse">
						<div class="col-lg-7 col-12">
							<div class="mb-4">
								<div class="row g-3">
									<div v-for="item in currentItems" :key="item.steam_asset_id" class="col-lg-4 col-6">
										<!-- Мобильная карточка — стиль маркетплейса -->
										<div class="m-listing-card d-lg-none h-100 d-flex flex-column" :class="getRarityClass(item)">
											<div class="m-lc-img">
												<img class="w-100" :src="getIconUrl(item)" :alt="item.market_hash_name"
													@error="handleImageError">
											</div>
											<div class="px-3 mt-2 m-lc-title">{{ getItemName(item) }}</div>
											<div class="px-3 m-lc-wear">{{ getItemType(item) }}</div>
											<div class="m-lc-actions px-3 pb-3 pt-2 mt-auto">
												<button v-if="!item.is_listed" type="button"
													class="btn m-inv-sell w-100" @click="openSellModal(item)">
													Продать
												</button>
												<div v-else class="alert alert-info mb-0 m-inv-status">
													<i class="ri-information-line me-2"></i>Этот предмет выставлен на
													продажу
												</div>
											</div>
										</div>
										<!-- Десктоп -->
										<div class="d-none d-lg-block" @click="selectItem(item)"
											:class="getItemClasses(item)">
											<img class="img-fluid inventory-img h-auto" :src="getIconUrl(item)"
												:alt="item.market_hash_name" @error="handleImageError">
											<h6 class="mt-2">{{ getItemName(item) }}</h6>
											<small class="text-muted">{{ getItemType(item) }}</small>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-lg-5 col-12 d-none d-lg-block" :id="getDetailsSectionId()">
							<ItemDetails :item="selectedItem" :active-tab="activeInventoryTab"
								:has-trade-url="hasTradeUrl" :is-creating-listing="isCreatingListing"
								:extension-active="extensionActive" :extension-checked="extensionChecked"
								@sell="openSellModal" />
						</div>
					</div>
				</div>

				<!-- Global Empty State - показывается только когда инвентарь вообще не загружен -->
				<EmptyState v-if="!inventoryData && !isLoading" icon="m-ico m-ico-empty-box" title="Инвентарь пуст"
					description="Убедитесь, что ваш Steam инвентарь публичный" />
			</div>
		</div>



		<!-- Sell Type Modal -->
		<div class="modal fade" id="sellTypeModal" tabindex="-1" aria-labelledby="sellTypeModalLabel"
			aria-hidden="true">
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
								<img :src="getIconUrl(itemToSell)" :alt="itemToSell.market_hash_name" class="me-3"
									style="width: 64px; height: 64px;" @error="handleImageError">
								<div>
									<h6 class="mb-1">{{ getItemName(itemToSell) }}</h6>
									<small class="text-muted">{{ getItemType(itemToSell) }}</small>
								</div>
							</div>
						</div>

						<div class="row g-3">
							<!-- Продать боту -->
							<div class="col-12">
								<div class="card h-100 sell-option"
									@click="itemToSell && itemToSell.buyout_price ? sellToBot() : null"
									:class="{ 'opacity-50': !itemToSell || !itemToSell.buyout_price }"
									style="cursor: pointer;">
									<div class="card-body d-flex align-items-center">
										<div class="sell-icon me-3">
											<i class="ri-robot-line text-primary" style="font-size: 2rem;"></i>
										</div>
										<div class="flex-grow-1">
											<h6 class="card-title mb-1">
												Быстрый выкуп
												<span v-if="itemToSell && itemToSell.buyout_price"
													class="text-success ms-2">
													<span v-html="formatPrice(itemToSell.buyout_price, 'USD')"></span>
												</span>
											</h6>
											<p class="card-text text-muted mb-0">
												<span v-if="itemToSell && itemToSell.buyout_price">
													Мгновенная продажа боту
												</span>
												<span v-else class="text-danger">
													Предмет не востребован
												</span>
											</p>
										</div>
										<div class="sell-arrow">
											<i v-if="itemToSell && itemToSell.buyout_price"
												class="ri-arrow-right-line text-muted"></i>
											<i v-else class="ri-close-line text-danger"></i>
										</div>
									</div>
								</div>
							</div>

							<!-- Добавить в маркетплейс -->
							<div class="col-12">
								<div class="card h-100 sell-option" :class="{ 'opacity-50': isCreatingListing }">
									<div class="card-body">
										<div class="d-flex align-items-center mb-3">
											<div class="sell-icon me-3">
												<i v-if="isCreatingListing"
													class="ri-loader-4-line text-success ri-spin"
													style="font-size: 2rem;"></i>
												<i v-else class="ri-store-2-line text-success"
													style="font-size: 2rem;"></i>
											</div>
											<div class="flex-grow-1">
												<h6 class="card-title mb-1">
													{{ sellTitle }}
												</h6>
												<p class="card-text text-muted mb-0">
													{{ sellDescription }}
												</p>
											</div>
										</div>

										<!-- Поле ввода цены -->
										<div v-if="!isCreatingListing" class="mb-3">
											<label class="form-label small">Цена продажи</label>
											<div class="input-group">
												<input type="number" class="form-control" id="marketplacePriceInput"
													v-model="marketplacePrice"
													:placeholder="itemToSell && (itemToSell.recommended_price || itemToSell.buyout_price) ? Math.round((itemToSell.recommended_price || itemToSell.buyout_price) * 1.3) : '100'"
													min="1" step="1">
												<span class="input-group-text">₽</span>
											</div>
											<small class="text-muted">Рекомендуемая цена: <span
													v-html="itemToSell && (itemToSell.recommended_price || itemToSell.buyout_price) ? formatPrice((itemToSell.recommended_price || itemToSell.buyout_price) * 1.3, 'USD') : '100 ₽'"></span></small>
										</div>

										<!-- Кнопка добавления -->
										<button v-if="!isCreatingListing" class="btn btn-success w-100"
											@click="addToMarketplace()"
											:disabled="!marketplacePrice || marketplacePrice <= 0">
											<i class="ri-add-line me-1"></i>Добавить за {{ marketplacePrice || '0' }} ₽
										</button>
									</div>
								</div>
							</div>

						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal успешной продажи -->
		<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content border-0">
					<div class="modal-header border-0 text-center">
						<div class="w-100">
							<div class="text-success mb-3">
								<i class="ri-check-circle-fill" style="font-size: 3rem;"></i>
							</div>
							<h5 class="modal-title text-success" id="successModalLabel">Продажа завершена!</h5>
						</div>
					</div>
					<div class="modal-body text-center">
						<p class="mb-3">{{ successModalData.message }}</p>
						<div v-if="successModalData.order" class="alert alert-light">
							<strong>Номер заказа:</strong> {{ successModalData.order.order_number }}<br>
							<strong>Сумма:</strong> <span
								v-html="formatPrice(successModalData.order.total_amount, 'RUB')"></span>
						</div>
					</div>
					<div class="modal-footer border-0 justify-content-center">
						<button type="button" class="btn theme-outline btn-sm me-2"
							data-bs-dismiss="modal">Закрыть</button>
						<button type="button" class="btn theme-btn btn-sm" @click="goToSales">
							<i class="ri-eye-line me-1"></i>Посмотреть в продажах
						</button>
					</div>
				</div>
			</div>
		</div>

	</div>
</template>

<script>
import axios from 'axios';
import { formatPrice, handleApiError, getTimeRemaining } from '../../../shared/utils/helpers';
import ItemDetails from './ItemDetails.vue';
import EmptyState from '../EmptyState.vue';

export default {
	name: 'ProfileInventory',
	components: {
		ItemDetails,
		EmptyState
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
			items: [],
			stats: {},
			isLoading: false,
			selectedItem: null,
			hasTradeUrl: false,
			itemToSell: null,
			inventoryData: null,
			isSyncing: false,
			syncCooldownRemaining: 0,
			syncCooldownMinutes: 2,
			cooldownTimer: null,
			activeInventoryTab: 'available',
			isCreatingListing: false,
			marketplacePrice: null,
			successModalData: {
				message: '',
				order: null
			},
			extensionActive: false,
			extensionChecked: false,
			searchQuery: ''
		}
	},
	computed: {
		availableItems() {
			return this.items.filter(item => item.tradable && item.marketable && !item.is_listed && this.matchesSearch(item));
		},
		listedItems() {
			return this.items.filter(item => item.is_listed && this.matchesSearch(item));
		},
		currentItems() {
			return this.activeInventoryTab === 'available' ? this.availableItems : this.listedItems;
		},
		sellTitle() {
			return this.isCreatingListing ? 'Создаем листинг...' : 'Добавить в маркетплейс';
		},
		sellDescription() {
			return this.isCreatingListing ? 'Получаем скриншот предмета' : 'Установите свою цену, комиссия 5%';
		}
	},
	methods: {
		async loadInventoryData() {
			this.isLoading = true;
			try {
				const response = await axios.get('/inventory');
				const data = response.data;

				if (data.success) {
					this.inventoryData = data.data;
					this.items = data.data.items;
					this.stats = data.data.stats;
					this.hasTradeUrl = data.data.has_trade_url;

					if (data.data.sync_cooldown_minutes) {
						this.syncCooldownMinutes = data.data.sync_cooldown_minutes;
					}

					const storedSyncTime = localStorage.getItem('inventory_last_sync');
					let lastSyncTime = null;

					if (storedSyncTime) {
						lastSyncTime = new Date(storedSyncTime);
					} else if (data.data.stats && data.data.stats.last_sync) {
						lastSyncTime = new Date(data.data.stats.last_sync);
					}

					if (lastSyncTime) {
						const now = new Date();
						const timeDiff = now - lastSyncTime;
						const cooldownTime = this.syncCooldownMinutes * 60 * 1000;

						if (timeDiff < cooldownTime) {
							const remainingSeconds = Math.ceil((cooldownTime - timeDiff) / 1000);
							this.startSyncCooldown(remainingSeconds);
						}
					}

					if (data.data.items.length === 0) {
						if (!this.hasTradeUrl) {
							window.toast.warning('Укажите Steam Trade URL в настройках профиля для загрузки инвентаря', {
								timeout: 10000
							});
						} else {
							window.toast.info('Ваш Steam инвентарь пуст или приватный. Убедитесь, что инвентарь публичный в настройках Steam. После изменения настроек попробуйте еще раз через 10-15 минут.', {
								timeout: 10000
							});
						}
					}
				} else {
					// Если API вернул ошибку, устанавливаем пустой инвентарь
					this.inventoryData = { items: [], stats: {} };
					this.items = [];
					this.stats = {};
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Error loading inventory:', error);
				// В любом случае устанавливаем пустые данные чтобы убрать лоадер
				this.inventoryData = { items: [], stats: {} };
				this.items = [];
				this.stats = {};
				// Глобальный обработчик покажет toast автоматически
			} finally {
				this.isLoading = false;
			}
		},

		async syncInventory() {
			this.isSyncing = true;

			try {
				const response = await axios.post('/inventory/sync');
				const data = response.data;

				if (data.success) {
					window.toast.success(`Инвентарь обновлен! Загружено предметов: ${data.data.items_count}`);

					const syncTime = new Date().toISOString();
					localStorage.setItem('inventory_last_sync', syncTime);

					await this.loadInventoryData();

					this.startSyncCooldown(this.syncCooldownMinutes * 60);
				} else {
					if (data.data && data.data.cooldown_remaining) {
						this.startSyncCooldown(data.data.cooldown_remaining);
					}
				}
			} catch (error) {
				console.error('Sync error:', error);
				// Глобальный обработчик покажет toast автоматически
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

		getTimeRemaining,

		matchesSearch(item) {
			if (!this.searchQuery) return true;
			const query = this.searchQuery.toLowerCase();
			const nameRu = (item.item?.name_ru || '').toLowerCase();
			const nameEn = (item.market_hash_name || '').toLowerCase();
			return nameRu.includes(query) || nameEn.includes(query);
		},

		setActiveInventoryTab(tab) {
			this.activeInventoryTab = tab;
			this.selectedItem = null;
		},

		getItemClasses(item) {
			const baseClasses = 'h-100 inventory-item text-center position-relative';
			const activeClass = this.selectedItem && this.selectedItem.steam_asset_id === item.steam_asset_id ? 'active' : '';
			const listedClass = this.activeInventoryTab === 'listed' ? 'item-listed' : '';
			const rarityClass = this.getRarityClass(item);

			return [baseClasses, activeClass, listedClass, rarityClass].filter(Boolean).join(' ');
		},

		getRarityClass(item) {
			if (!item || !item.structured_tags) {
				return '';
			}

			const rarityTag = item.structured_tags.find(tag => tag.category_code === 'rarity');
			if (rarityTag) {
				return `rarity-${rarityTag.normalized_value}`;
			}

			return '';
		},

		getDetailsSectionId() {
			return this.activeInventoryTab === 'available' ? 'item-details-section' : 'listed-item-details-section';
		},

		getEmptyStateMessage() {
			if (this.activeInventoryTab === 'listed') {
				return 'Нет предметов в продаже';
			}
			if (!this.hasTradeUrl) {
				return 'Не указана Trade URL';
			}
			return 'Нет доступных предметов для торговли';
		},

		getEmptyStateDescription() {
			if (this.activeInventoryTab === 'listed') {
				return 'Выставьте предметы на продажу в разделе "Доступные для торговли"';
			}
			if (!this.hasTradeUrl) {
				return 'Укажите вашу Steam Trade URL в настройках профиля, чтобы загрузить инвентарь';
			}
			return 'Убедитесь, что ваш Steam инвентарь публичный и содержит торгуемые предметы';
		},

		getItemName(item) {
			return item.item?.name_ru || item.market_hash_name;
		},

		getItemType(item) {
			if (item.structured_tags && item.structured_tags.length > 0) {
				const typeTag = item.structured_tags.find(tag => tag.category_code === 'type');
				return typeTag ? typeTag.display_name : 'Предмет';
			}
			return 'Предмет';
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


		openSellModal(item) {
			// Проверяем статус расширения перед открытием модала
			if (!this.extensionActive) {
				window.toast.warning('Активируйте расширение для создания листингов');
				return;
			}

			this.itemToSell = item;

			// Устанавливаем рекомендуемую цену (на 30% выше рыночной цены)
			const refPrice = item.recommended_price || item.buyout_price;
			if (refPrice) {
				this.marketplacePrice = Math.round(refPrice * 1.3);
			} else {
				this.marketplacePrice = 100; // Базовая цена по умолчанию
			}

			const modal = new bootstrap.Modal(document.getElementById('sellTypeModal'));

			// Добавляем обработчик события полного открытия модального окна
			const modalElement = document.getElementById('sellTypeModal');
			modalElement.addEventListener('shown.bs.modal', function selectPriceText() {
				const priceInput = document.getElementById('marketplacePriceInput');
				if (priceInput) {
					setTimeout(() => {
						priceInput.focus();
						priceInput.select();
					}, 50);
				}
				// Удаляем обработчик после использования
				modalElement.removeEventListener('shown.bs.modal', selectPriceText);
			});

			modal.show();
		},

		async sellToBot() {
			if (!this.itemToSell) return;

			// Закрываем модальное окно
			const modal = bootstrap.Modal.getInstance(document.getElementById('sellTypeModal'));
			if (modal) {
				modal.hide();
			}

			this.isCreatingListing = true;

			try {
				// Показываем уведомление о начале процесса
				window.toast.info('Продаем предмет боту...', {
					timeout: 3000
				});

				const { orderAPI } = await import('../../../shared/utils/api.js');
				const result = await orderAPI.quickSell(this.itemToSell.steam_asset_id);

				if (result.success) {
					// Показываем модальное окно с результатом
					this.showSuccessModal(result.message, result.order);
				} else {
					// Ошибку уже покажет глобальный axios interceptor
					console.error('Quick sell failed:', result.message);
				}

			} catch (error) {
				console.error('Error selling to bot:', error);
				// Ошибку уже покажет глобальный axios interceptor
			} finally {
				this.isCreatingListing = false;
				this.itemToSell = null;
			}
		},

		showSuccessModal(message, order) {
			this.successModalData = {
				message: message,
				order: order
			};

			// Показываем модальное окно
			this.$nextTick(() => {
				const modal = new bootstrap.Modal(document.getElementById('successModal'));
				modal.show();
			});
		},

		goToSales() {
			// Закрываем модальное окно
			const modal = bootstrap.Modal.getInstance(document.getElementById('successModal'));
			if (modal) {
				modal.hide();
			}

			// Переходим к продажам
			window.location.hash = 'sales';
			// Обновляем страницу для отображения продаж
			window.location.reload();
		},

		handleCurrencyChange() {
			// Принудительно обновляем данные для пересчета цен
			if (this.itemToSell) {
				this.itemToSell = { ...this.itemToSell };
			}
			if (this.selectedItem) {
				this.selectedItem = { ...this.selectedItem };
			}
			if (this.items.length > 0) {
				this.items = [...this.items];
			}
		},

		async checkExtensionStatus() {
			try {
				const response = await axios.get('/inventory/extension-status');
				if (response.data.success) {
					this.extensionActive = response.data.data.is_active;
				}
			} catch (error) {
				console.error('Failed to check extension status:', error);
				this.extensionActive = false;
			} finally {
				this.extensionChecked = true;
			}
		},

		async addToMarketplace() {
			if (!this.itemToSell || !this.marketplacePrice || this.marketplacePrice <= 0) return;

			// Закрываем модальное окно
			const modal = bootstrap.Modal.getInstance(document.getElementById('sellTypeModal'));
			if (modal) {
				modal.hide();
			}

			this.isCreatingListing = true;

			try {

				// Отправляем запрос на создание листинга с ценой
				const response = await axios.post('/inventory/create-listing', {
					steam_asset_id: this.itemToSell.steam_asset_id,
					price: this.marketplacePrice
				});
				const data = response.data;

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
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Create listing error:', error);
				// Глобальный обработчик покажет toast автоматически
			} finally {
				this.isCreatingListing = false;
				this.marketplacePrice = null; // Очищаем цену для следующего использования
			}
		}
	},
	mounted() {
		// Загружаем данные инвентаря при загрузке компонента
		this.loadInventoryData();

		// Проверяем статус расширения
		this.checkExtensionStatus();

		// Слушаем события смены валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);
	},

	beforeUnmount() {
		// Очищаем таймер кулдауна
		if (this.cooldownTimer) {
			clearInterval(this.cooldownTimer);
		}

		// Убираем слушатель при размонтировании
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
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