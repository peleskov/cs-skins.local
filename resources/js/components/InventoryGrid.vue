<template>
	<div class="inventory-section">



		<!-- Loading State -->
		<div v-if="isLoading && items.length === 0" class="text-center py-5">
			<div class="loader-gif">
				<div class="radar-ring"></div>
				<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
			</div>
			<p class="mt-3">Загружаем инвентарь...</p>
		</div>

		<!-- Inventory Items -->
		<div v-else class="inventory-items">
			<h5 class="mb-3">Доступные для торговли</h5>
			<div class="row g-4">
				<div class="col-7">
					<!-- Tradable Items -->
					<div v-if="tradableItems.length > 0" class="mb-4">
						<div class="row g-3">
							<div v-for="item in tradableItems" :key="item.steam_asset_id" class="col-lg-4 col-md-6">
								<div @click="selectItem(item)" 
									 :class="['h-100 inventory-item text-center position-relative', { 'active': selectedItem && selectedItem.steam_asset_id === item.steam_asset_id, 'item-listed': item.is_listed }]">
									<img class="img-fluid inventory-img h-auto" :src="getIconUrl(item)"
										:alt="item.market_hash_name" @error="handleImageError">
									<h6 class="mt-2">{{ getItemName(item) }}</h6>
									<small class="text-muted">{{ item.type || 'Unknown' }}</small>
									<div v-if="item.is_listed" class="position-absolute top-0 end-0 m-2">
										<span class="badge bg-success">
											<i class="ri-store-2-line"></i> В продаже
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
					
					<!-- Empty State -->
					<div v-if="items.length === 0" class="text-center py-5">
						<p class="text-muted">Инвентарь пуст</p>
					</div>
				</div>
				<div class="col-5">
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
							<button v-if="selectedItem.tradable && selectedItem.marketable && hasTradeUrl && !selectedItem.is_listed" 
									class="btn theme-btn w-100 mb-2"
									@click="openSellModal(selectedItem)">
								<i class="ri-price-tag-3-line me-2"></i>Продать
							</button>
							<div v-else-if="selectedItem.is_listed" 
								 class="alert alert-info mb-0">
								<i class="ri-store-2-line me-2"></i>Предмет уже выставлен на продажу. Управлять им можно в разделе <a href="/profile#trading">Торговля</a>
							</div>
							<div v-else-if="selectedItem.tradable && selectedItem.marketable && !hasTradeUrl" 
								 class="alert alert-light mb-0 small">
								<i class="ri-information-line me-2"></i>Для того чтобы выставить на продажу нужно добавить Trade URL в настройках <a href="/profile#profile">профиля</a>
							</div>
							<div v-else-if="!selectedItem.tradable || !selectedItem.marketable" 
								 class="alert alert-secondary mb-0">
								<i class="ri-lock-line me-2"></i>Данный предмет нельзя продать
							</div>
						</div>
					</div>
					<div v-else class="item-details-placeholder text-center text-muted">
						<i class="ri-arrow-left-line"></i>
						<p>Выберите предмет для просмотра деталей</p>
					</div>
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
								<div class="card h-100 sell-option" @click="addToMarketplace" style="cursor: pointer;">
									<div class="card-body d-flex align-items-center">
										<div class="sell-icon me-3">
											<i class="ri-store-2-line text-success" style="font-size: 2rem;"></i>
										</div>
										<div class="flex-grow-1">
											<h6 class="card-title mb-1">Добавить в маркетплейс</h6>
											<p class="card-text text-muted mb-0">
												Установите свою цену, комиссия 5%
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

export default {
	name: 'InventoryGrid',
	setup() {
		const toast = useToast();
		return { toast };
	},
	props: {
		initialItems: {
			type: Array,
			default: () => []
		},
		initialStats: {
			type: Object,
			default: () => ({})
		},
		initialHasTradeUrl: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			items: this.initialItems,
			stats: this.initialStats,
			isLoading: false,
			selectedItem: null,
			hasTradeUrl: this.initialHasTradeUrl,
			itemToSell: null
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
		nonTradableItems() {
			// Предметы которые нельзя обменять или продать
			return this.items.filter(item => !item.tradable || !item.marketable);
		}
	},
	methods: {
		async syncInventory() {
			this.isLoading = true;

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
					this.$emit('inventory-updated');
				} else {
					this.toast.error(data.message);
				}
			} catch (error) {
				console.error('Sync error:', error);
				this.toast.error('Произошла ошибка при обновлении инвентаря');
			} finally {
				this.isLoading = false;
			}
		},
		getItemName(item) {
			return item.item?.name_ru || item.market_hash_name;
		},
		getItemPrice(item) {
			return item.item?.min_steam_price;
		},
		formatPrice(price) {
			return Number(price).toFixed(2);
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
			
			try {
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
					
					this.toast.success('Предмет добавлен в маркетплейс. Переходим в раздел "Торговля"...');
					
					// Переходим в раздел торговли через 2 секунды
					setTimeout(() => {
						window.location.href = '/profile#trading';
					}, 2000);
				} else {
					this.toast.error(data.message || 'Не удалось создать листинг');
				}
			} catch (error) {
				console.error('Create listing error:', error);
				this.toast.error('Произошла ошибка при создании листинга');
			}
		}
	},
	mounted() {
		// Автоматически выбираем первый доступный для торговли предмет при загрузке
		if (this.tradableItems.length > 0) {
			this.selectedItem = this.tradableItems[0];
		} else if (this.items.length > 0) {
			this.selectedItem = this.items[0];
		}
	}
}
</script>