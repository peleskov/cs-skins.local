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
		<div v-else-if="filteredItems.length > 0" class="inventory-items">
			<div class="row g-3">
				<div class="col-8">
					<div class="row g-3">
						<div v-for="item in filteredItems" :key="item.steam_asset_id" class="col-lg-3 col-md-4">
							<div class="h-100 inventory-item text-center">
								<img class="img-fluid inventory-img h-auto" :src="getIconUrl(item)"
									:alt="item.market_hash_name" @error="handleImageError">
								<h6 class="mt-2">{{ getItemName(item) }}</h6>
								<small class="text-muted">{{ item.type || 'Unknown' }}</small>
							</div>
						</div>
					</div>
				</div>
				<div class="col-4"></div>
			</div>
		</div>


		<!-- Toast Notifications -->
		<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
			<div v-for="notification in notifications" :key="notification.id" class="toast show mb-2" role="alert">
				<div class="toast-header">
					<i :class="['ri-' + notification.type + '-line', 'me-2']"></i>
					<strong class="me-auto">{{ notification.title }}</strong>
					<button type="button" class="btn-close" @click="removeNotification(notification.id)"></button>
				</div>
				<div class="toast-body">
					{{ notification.message }}
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'InventoryGrid',
	props: {
		initialItems: {
			type: Array,
			default: () => []
		},
		initialStats: {
			type: Object,
			default: () => ({})
		}
	},
	data() {
		return {
			items: this.initialItems,
			stats: this.initialStats,
			isLoading: false,
			searchQuery: '',
			filterTradable: '',
			sortBy: 'name',
			sortOrder: 'asc',
			notifications: []
		}
	},
	computed: {
		filteredItems() {
			let filtered = this.items;

			// Поиск по названию
			if (this.searchQuery) {
				const query = this.searchQuery.toLowerCase();
				filtered = filtered.filter(item =>
					this.getItemName(item).toLowerCase().includes(query)
				);
			}

			// Фильтр по возможности торговли
			if (this.filterTradable === 'tradable') {
				filtered = filtered.filter(item => item.tradable);
			} else if (this.filterTradable === 'non-tradable') {
				filtered = filtered.filter(item => !item.tradable);
			}

			// Сортировка
			filtered.sort((a, b) => {
				let aValue, bValue;

				switch (this.sortBy) {
					case 'name':
						aValue = this.getItemName(a).toLowerCase();
						bValue = this.getItemName(b).toLowerCase();
						break;
					case 'price':
						aValue = this.getItemPrice(a) || 0;
						bValue = this.getItemPrice(b) || 0;
						break;
					case 'float':
						aValue = a.float_value || 0;
						bValue = b.float_value || 0;
						break;
					case 'date':
						aValue = new Date(a.cached_at);
						bValue = new Date(b.cached_at);
						break;
					default:
						return 0;
				}

				if (aValue < bValue) return this.sortOrder === 'asc' ? -1 : 1;
				if (aValue > bValue) return this.sortOrder === 'asc' ? 1 : -1;
				return 0;
			});

			return filtered;
		},
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
					this.showNotification('success', 'Успешно!',
						`Инвентарь обновлен! Загружено предметов: ${data.data.items_count}`);

					// Обновляем данные без перезагрузки страницы
					this.$emit('inventory-updated');
				} else {
					this.showNotification('error', 'Ошибка', data.message);
				}
			} catch (error) {
				console.error('Sync error:', error);
				this.showNotification('error', 'Ошибка', 'Произошла ошибка при обновлении инвентаря');
			} finally {
				this.isLoading = false;
			}
		},
		toggleSortOrder() {
			this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
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
		showNotification(type, title, message) {
			const id = Date.now();
			const notification = { id, type, title, message };
			this.notifications.push(notification);

			// Автоматически удаляем через 10 секунд
			setTimeout(() => {
				this.removeNotification(id);
			}, 10000);
		},

		removeNotification(id) {
			const index = this.notifications.findIndex(n => n.id === id);
			if (index > -1) {
				this.notifications.splice(index, 1);
			}
		}
	}
}
</script>