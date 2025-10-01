<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<div class="d-flex justify-content-between align-items-center">
				<h3>Steam Инвентарь</h3>
				<a v-if="inventoryData && !hasTradeUrl" href="/profile#profile" class="btn theme-btn btn-sm">
					<i class="ri-link me-1"></i>
					<span>Установить TradeUrl</span>
				</a>
				<button v-else-if="inventoryData" class="btn theme-outline btn-sm" @click="syncInventory"
					:disabled="isSyncing || syncCooldownRemaining > 0">
					<i :class="['ri-refresh-line', 'me-1', { 'ri-spin': isSyncing }]"></i>
					<span v-if="isSyncing">Обновление...</span>
					<span v-else-if="syncCooldownRemaining > 0">Обновить через {{ getTimeRemaining(syncCooldownRemaining) }}</span>
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
											<small class="text-muted">{{ getItemType(item) }}</small>
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
							<ItemDetails 
								:item="selectedItem"
								:active-tab="activeInventoryTab"
								:has-trade-url="hasTradeUrl"
								:is-creating-listing="isCreatingListing"
								@sell="openSellModal"
							/>
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
											<small class="text-muted">{{ getItemType(item) }}</small>
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
							<ItemDetails 
								:item="selectedItem"
								:active-tab="activeInventoryTab"
								:has-trade-url="hasTradeUrl"
								:is-creating-listing="isCreatingListing"
								@sell="openSellModal"
							/>
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
												<span v-if="itemToSell && itemToSell.buyout_price" class="text-success ms-2">
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
											<i v-if="itemToSell && itemToSell.buyout_price" class="ri-arrow-right-line text-muted"></i>
											<i v-else class="ri-close-line text-danger"></i>
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
							<strong>Сумма:</strong> <span v-html="formatPrice(successModalData.order.total_amount, 'RUB')"></span>
						</div>
					</div>
					<div class="modal-footer border-0 justify-content-center">
						<button type="button" class="btn theme-outline btn-sm me-2" data-bs-dismiss="modal">Закрыть</button>
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
import { formatPrice, handleApiError, getTimeRemaining } from '../../utils/helpers';
import ItemDetails from './ItemDetails.vue';

export default {
	name: 'ProfileInventory',
	components: {
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
			isCreatingListing: false,
			successModalData: {
				message: '',
				order: null
			}
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
				const response = await axios.get('/inventory');
				const data = response.data;

				if (data.success) {
					this.inventoryData = data.data;
					this.items = data.data.items;
					this.stats = data.data.stats;
					this.hasTradeUrl = data.data.has_trade_url;

					// Проверяем, нужно ли запустить кулдаун для синхронизации
					// Сначала проверяем localStorage, затем данные с сервера
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

					// Сохраняем время синхронизации в localStorage
					const syncTime = new Date().toISOString();
					localStorage.setItem('inventory_last_sync', syncTime);

					// Обновляем данные без перезагрузки страницы
					await this.loadInventoryData();

					// Запускаем кулдаун на 2 минуты
					this.startSyncCooldown(120); // 2 минуты = 120 секунд
				} else {
					// Глобальный обработчик покажет toast автоматически

					// Если есть информация о кулдауне, запускаем его
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
			this.itemToSell = item;
			const modal = new bootstrap.Modal(document.getElementById('sellTypeModal'));
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
				
				const { orderAPI } = await import('../../utils/api.js');
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
				const response = await axios.post('/inventory/create-listing', {
					steam_asset_id: this.itemToSell.steam_asset_id
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
			}
		}
	},
	mounted() {
		// Загружаем данные инвентаря при загрузке компонента
		this.loadInventoryData();
		
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