<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Торговля</h3>
		</div>

		<!-- Loading state -->
		<div v-if="isLoading" class="text-center py-5">
			<div class="loader-gif">
				<div class="radar-ring"></div>
				<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
			</div>
			<p class="mt-3">Загружаем ваши листинги...</p>
		</div>

		<!-- Listings blocks -->
		<div v-else-if="listings.length > 0" class="product-box-section section-b-space">
			<div class="product-details-box-list">
				<div v-for="listing in listings" :key="listing.id" class="product-details-box gap-2">
					<div class="product-img" :style="{ backgroundImage: 'url(' + getItemImage(listing) + ')' }">
					</div>
					<div class="description d-flex align-items-center justify-content-between flex-grow-1 gap-3">
						<div>
							<div class="d-flex align-items-center gap-2">
								<span :class="getStatusClass(listing.status)">
									{{ getStatusText(listing.status) }}
								</span>
								<h6 class="product-name">{{ getItemName(listing) }}</h6>
							</div>
							<div class="rating-section">
								<div class="d-flex align-items-center gap-2">
									<span v-if="listing.wear_value" class="badge bg-secondary">
										{{ getWearName(listing.wear_value) }}
									</span>
									<small class="text-muted">{{ formatDate(listing.created_at) }}</small>
								</div>
							</div>
							<p class="text-muted mb-0">{{ listing.market_hash_name }}</p>
						</div>
						<div class="h-100 d-flex flex-column justify-content-between">
							<div class="product-box-price d-grid gap-2 mb-4 text-center">
								<span class="text-muted small">Цена:</span>
								<span class="text-muted small">ТОП-1</span>
								<span class="text-muted small">Выкуп</span>

								<span class="small">{{ formatPrice(listing.price) }} ₽</span>
								<span class="small">{{ listing.min_market_price ? formatPrice(listing.min_market_price)
									+ ' ₽' : '-' }}</span>
								<span class="small">{{ listing.buyout_price ? formatPrice(listing.buyout_price) + ' ₽' : 'Не востребован' }}</span>
							</div>
							<div class="btn-group">
								<!-- Кнопки для pending статуса -->
								<template v-if="listing.status === 'pending'">
									<button class="btn theme-outline" @click="activateListing(listing)"
										:disabled="listing.price == 0"
										:title="listing.price == 0 ? 'Сначала установите цену' : 'Активировать'">
										<i class="ri-play-line me-1"></i>Активировать
									</button>
									<button class="btn theme-outline" @click="editPrice(listing)" title="Изменить цену">
										<i class="ri-edit-line me-1"></i>Редактировать
									</button>
									<button class="btn theme-outline" @click="removeListing(listing)"
										title="Снять с продажи">
										<i class="ri-delete-bin-line me-1"></i>Удалить
									</button>
								</template>

								<!-- Кнопки для active статуса -->
								<template v-if="listing.status === 'active'">
									<a :href="`/marketplace/${listing.id}`" target="_blank" class="btn theme-outline" title="Просмотреть на маркетплейсе">
										<i class="ri-external-link-line me-1"></i>Смотреть
									</a>
									<button class="btn theme-outline" @click="deactivateListing(listing)"
										title="Деактивировать">
										<i class="ri-pause-line me-1"></i>Пауза
									</button>
								</template>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Empty state -->
		<div v-else class="text-center py-5">
			<i class="ri-shopping-bag-3-line display-4 text-muted mb-3"></i>
			<h4>Нет активных листингов</h4>
			<p class="text-muted mb-4">Перейдите в раздел "Инвентарь" чтобы выставить предметы на продажу</p>
			<a href="#inventory" class="btn theme-btn">
				<i class="ri-treasure-map-line me-2"></i>Перейти в инвентарь
			</a>
		</div>
	</div>

	<!-- Модальное окно подтверждения удаления -->
	<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmDeleteModalLabel">
						<i class="ri-delete-bin-line me-2 text-danger"></i>Подтверждение удаления
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="itemToDelete" class="mb-3">
						<div class="d-flex align-items-center">
							<div class="product-img me-3"
								:style="{ backgroundImage: 'url(' + getItemImage(itemToDelete) + ')', width: '64px', height: '64px', backgroundSize: 'contain', backgroundRepeat: 'no-repeat', backgroundPosition: 'center' }">
							</div>
							<div>
								<h6 class="mb-1">{{ getItemName(itemToDelete) }}</h6>
								<small class="text-muted">{{ itemToDelete.market_hash_name }}</small>
							</div>
						</div>
					</div>
					<p>Вы уверены, что хотите удалить этот предмет из торговли?</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline theme-outline-danger"
						data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn theme-btn-danger" @click="confirmRemoveListing">
						<i class="ri-delete-bin-line me-1"></i>Удалить
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Модальное окно редактирования цены -->
	<div class="modal fade" id="editPriceModal" tabindex="-1" aria-labelledby="editPriceModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="editPriceModalLabel">
						<i class="ri-edit-line me-2 text-primary"></i>Установить цену
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="itemToEdit" class="mb-3">
						<div class="d-flex align-items-center">
							<div class="product-img me-3" :style="{
								backgroundImage: 'url(' + getItemImage(itemToEdit) + ')',
								width: '64px',
								height: '64px',
								backgroundSize: 'contain',
								backgroundRepeat: 'no-repeat',
								backgroundPosition: 'center'
							}"></div>
							<div>
								<h6 class="mb-1">{{ getItemName(itemToEdit) }}</h6>
								<small class="text-muted">{{ itemToEdit.market_hash_name }}</small>
							</div>
						</div>
					</div>

					<div class="mb-3">
						<label for="priceInput" class="form-label">Цена в рублях</label>
						<div class="input-group">
							<input type="number" class="form-control" id="priceInput" v-model="editPriceValue"
								:placeholder="itemToEdit ? itemToEdit.price : '0'" min="0.01" max="100000" step="0.01"
								@keyup.enter="confirmEditPrice">
							<span class="input-group-text">₽</span>
						</div>
						<div class="form-text">
							Минимальная цена: 0.01 ₽, максимальная: 100,000 ₽
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline theme-outline-danger"
						data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn theme-btn-info" @click="confirmEditPrice"
						:disabled="!isValidPrice">
						<i class="ri-save-line me-1"></i>Сохранить
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Модальное окно подтверждения активации -->
	<div class="modal fade" id="confirmActivateModal" tabindex="-1" aria-labelledby="confirmActivateModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmActivateModalLabel">
						<i class="ri-play-line me-2 text-success"></i>Подтверждение активации
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="itemToActivate" class="mb-3">
						<div class="d-flex align-items-center">
							<div class="product-img me-3" :style="{
								backgroundImage: 'url(' + getItemImage(itemToActivate) + ')',
								width: '64px',
								height: '64px',
								backgroundSize: 'contain',
								backgroundRepeat: 'no-repeat',
								backgroundPosition: 'center'
							}"></div>
							<div>
								<h6 class="mb-1">{{ getItemName(itemToActivate) }}</h6>
								<small class="text-muted">{{ itemToActivate.market_hash_name }}</small>
								<div class="mt-1">
									<span class="badge bg-success">{{ formatPrice(itemToActivate.price) }} ₽</span>
								</div>
							</div>
						</div>
					</div>
					<p>Вы уверены, что хотите активировать этот листинг? Предмет станет доступен для покупки в
						маркетплейсе.
					</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline theme-outline-danger"
						data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn theme-btn-success" @click="confirmActivateListing">
						<i class="ri-play-line me-1"></i>Активировать
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Модальное окно подтверждения деактивации -->
	<div class="modal fade" id="confirmDeactivateModal" tabindex="-1" aria-labelledby="confirmDeactivateModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmDeactivateModalLabel">
						<i class="ri-pause-line me-2 text-warning"></i>Подтверждение деактивации
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="itemToDeactivate" class="mb-3">
						<div class="d-flex align-items-center">
							<div class="product-img me-3" :style="{
								backgroundImage: 'url(' + getItemImage(itemToDeactivate) + ')',
								width: '64px',
								height: '64px',
								backgroundSize: 'contain',
								backgroundRepeat: 'no-repeat',
								backgroundPosition: 'center'
							}"></div>
							<div>
								<h6 class="mb-1">{{ getItemName(itemToDeactivate) }}</h6>
								<small class="text-muted">{{ itemToDeactivate.market_hash_name }}</small>
							</div>
						</div>
					</div>
					<p>Вы уверены, что хотите деактивировать этот листинг? Предмет станет недоступен для покупки в
						маркетплейсе.</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline theme-outline-danger"
						data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn theme-btn-info" @click="confirmDeactivateListing">
						<i class="ri-pause-line me-1"></i>Деактивировать
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Модальное окно для ввода Trade URL -->
	<div class="modal fade" id="tradeUrlRequiredModal" tabindex="-1" aria-labelledby="tradeUrlRequiredModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="tradeUrlRequiredModalLabel">
						<i class="ri-link me-2 text-warning"></i>Добавить Trade URL
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="alert alert-warning">
						<i class="ri-alert-line me-2"></i>
						<strong>Для активации листинга необходимо настроить Steam Trade URL</strong>
					</div>
					
					<form @submit.prevent="saveTradeUrl">
						<div class="mb-3">
							<label for="tradeUrlInput" class="form-label">Steam Trade URL</label>
							<input type="url" class="form-control" id="tradeUrlInput" v-model="tradeUrlValue"
								placeholder="https://steamcommunity.com/tradeoffer/new/?partner=..."
								required>
							<div class="form-text">
								Получите Trade URL в Steam: Инвентарь → Настройки торговли
							</div>
						</div>
						
						<div class="alert alert-info">
							<h6><i class="ri-information-line me-2"></i>Как получить Trade URL:</h6>
							<ol class="mb-0">
								<li>Откройте Steam в браузере</li>
								<li>Перейдите в <strong>Инвентарь → Настройки торговли</strong></li>
								<li>Скопируйте ссылку из поля "URL торговых предложений"</li>
							</ol>
						</div>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn" @click="saveTradeUrl" :disabled="!tradeUrlValue">
						<i class="ri-save-line me-1"></i>Сохранить
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { useToast } from "vue-toastification";
import { formatPrice } from '../../utils/helpers';

export default {
	name: 'ProfileTrading',
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
			listings: [],
			isLoading: false,
			itemToDelete: null,
			itemToEdit: null,
			editPriceValue: null,
			itemToActivate: null,
			itemToDeactivate: null,
			tradeUrlValue: ''
		}
	},
	computed: {
		isValidPrice() {
			const price = parseFloat(this.editPriceValue);
			return !isNaN(price) && price >= 0.01 && price <= 100000;
		}
	},
	methods: {
		async loadListings() {
			this.isLoading = true;
			try {
				const response = await fetch('/api/listings/my', {
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					}
				});

				const data = await response.json();

				if (data.success) {
					this.listings = data.data;
				} else {
					this.toast.error(data.message || 'Не удалось загрузить листинги');
				}
			} catch (error) {
				console.error('Error loading listings:', error);
				this.toast.error('Произошла ошибка при загрузке листингов');
			} finally {
				this.isLoading = false;
			}
		},

		getItemImage(listing) {
			// Пытаемся получить изображение из связанного item
			if (listing.item && listing.item.image_url) {
				return listing.item.image_url;
			}
			// Если есть market_hash_name, пытаемся построить URL
			if (listing.market_hash_name) {
				return `https://steamcommunity-a.akamaihd.net/economy/image/${listing.market_hash_name}`;
			}
			return '/images/skin_no_image.svg';
		},

		getItemName(listing) {
			// Пытаемся получить русское название из связанного item
			if (listing.item && listing.item.name_ru) {
				return listing.item.name_ru;
			}
			// Иначе используем market_hash_name
			return listing.market_hash_name || 'Неизвестный предмет';
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

		getWearName(wearValue) {
			if (wearValue <= 0.07) return 'Прямо с завода';
			if (wearValue <= 0.15) return 'Немного поношенное';
			if (wearValue <= 0.38) return 'После полевых испытаний';
			if (wearValue <= 0.45) return 'Поношенное';
			return 'Закалённое в боях';
		},

		getStatusText(status) {
			const statusMap = {
				'pending': 'Черновик',
				'active': 'Активен',
				'sold': 'Продан',
				'cancelled': 'Отменён',
				'expired': 'Истёк'
			};
			return statusMap[status] || status;
		},

		getStatusClass(status) {
			const classMap = {
				'pending': 'badge bg-warning',
				'active': 'badge bg-success',
				'sold': 'badge bg-primary',
				'cancelled': 'badge bg-secondary',
				'expired': 'badge bg-warning'
			};
			return classMap[status] || 'badge bg-secondary';
		},

		handleImageError(event) {
			event.target.src = '/images/skin_no_image.svg';
		},

		activateListing(listing) {
			if (listing.price <= 0) {
				this.toast.error('Сначала установите цену для листинга');
				return;
			}

			this.itemToActivate = listing;
			const modal = new bootstrap.Modal(document.getElementById('confirmActivateModal'));
			modal.show();
		},

		async confirmActivateListing() {
			if (!this.itemToActivate) return;

			try {
				const response = await fetch('/api/listings/activate', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						listing_id: this.itemToActivate.id
					})
				});

				const data = await response.json();

				if (data.success) {
					// Обновляем статус в локальных данных
					this.itemToActivate.status = 'active';
					this.itemToActivate.listed_at = new Date().toISOString();
					this.toast.success('Листинг активирован');

					// Обновляем минимальную цену для этого предмета
					await this.updateMinMarketPrice(this.itemToActivate.market_hash_name);

					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('confirmActivateModal'));
					if (modal) {
						modal.hide();
					}
				} else {
					// Проверяем, нужен ли Trade URL
					if (data.require_trade_url) {
						this.toast.error(data.message || 'Необходимо настроить Trade URL');
						
						// Закрываем модальное окно активации
						const modal = bootstrap.Modal.getInstance(document.getElementById('confirmActivateModal'));
						if (modal) {
							modal.hide();
						}
						
						// Показываем модальное окно для ввода Trade URL
						setTimeout(() => {
							const tradeUrlModal = new bootstrap.Modal(document.getElementById('tradeUrlRequiredModal'));
							tradeUrlModal.show();
						}, 300);
					} else {
						this.toast.error(data.message || 'Не удалось активировать листинг');
					}
				}
			} catch (error) {
				console.error('Activate listing error:', error);
				this.toast.error('Произошла ошибка при активации листинга');
			} finally {
				this.itemToActivate = null;
			}
		},

		removeListing(listing) {
			this.itemToDelete = listing;
			const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
			modal.show();
		},

		async confirmRemoveListing() {
			if (!this.itemToDelete) return;

			try {
				const response = await fetch('/api/listings/delete', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						listing_id: this.itemToDelete.id
					})
				});

				const data = await response.json();

				if (data.success) {
					const deletedMarketHashName = this.itemToDelete.market_hash_name;

					// Удаляем листинг из локального массива
					this.listings = this.listings.filter(listing => listing.id !== this.itemToDelete.id);
					this.toast.success(data.message || 'Предмет удален из торговли');

					// Обновляем минимальную цену для этого предмета
					await this.updateMinMarketPrice(deletedMarketHashName);
				} else {
					this.toast.error(data.message || 'Не удалось удалить предмет');
				}
			} catch (error) {
				console.error('Delete listing error:', error);
				this.toast.error('Произошла ошибка при удалении предмета');
			} finally {
				// Закрываем модальное окно
				const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
				if (modal) {
					modal.hide();
				}

				// Очищаем выбранный элемент
				this.itemToDelete = null;
			}
		},

		editPrice(listing) {
			this.itemToEdit = listing;
			this.editPriceValue = listing.price;
			const modal = new bootstrap.Modal(document.getElementById('editPriceModal'));
			modal.show();
		},

		async confirmEditPrice() {
			if (!this.isValidPrice) {
				this.toast.error('Введите корректную цену');
				return;
			}

			const numPrice = parseFloat(this.editPriceValue);

			try {
				const response = await fetch('/api/listings/update-price', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						listing_id: this.itemToEdit.id,
						price: numPrice
					})
				});

				const data = await response.json();

				if (data.success) {
					// Обновляем цену в локальных данных
					this.itemToEdit.price = numPrice;
					this.toast.success('Цена обновлена');

					// Обновляем минимальную цену для этого предмета
					await this.updateMinMarketPrice(this.itemToEdit.market_hash_name);

					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('editPriceModal'));
					if (modal) {
						modal.hide();
					}
				} else {
					this.toast.error(data.message || 'Не удалось обновить цену');
				}
			} catch (error) {
				console.error('Update price error:', error);
				this.toast.error('Произошла ошибка при обновлении цены');
			} finally {
				this.itemToEdit = null;
				this.editPriceValue = null;
			}
		},

		deactivateListing(listing) {
			this.itemToDeactivate = listing;
			const modal = new bootstrap.Modal(document.getElementById('confirmDeactivateModal'));
			modal.show();
		},

		async confirmDeactivateListing() {
			if (!this.itemToDeactivate) return;

			try {
				const response = await fetch('/api/listings/deactivate', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						listing_id: this.itemToDeactivate.id
					})
				});

				const data = await response.json();

				if (data.success) {
					// Обновляем статус в локальных данных
					this.itemToDeactivate.status = 'pending';
					this.itemToDeactivate.listed_at = null;
					this.toast.success('Листинг деактивирован');

					// Обновляем минимальную цену для этого предмета
					await this.updateMinMarketPrice(this.itemToDeactivate.market_hash_name);

					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeactivateModal'));
					if (modal) {
						modal.hide();
					}
				} else {
					this.toast.error(data.message || 'Не удалось деактивировать листинг');
				}
			} catch (error) {
				console.error('Deactivate listing error:', error);
				this.toast.error('Произошла ошибка при деактивации листинга');
			} finally {
				this.itemToDeactivate = null;
			}
		},

		async updateMinMarketPrice(marketHashName) {
			try {
				const response = await fetch('/api/listings/min-price', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						market_hash_name: marketHashName
					})
				});

				const data = await response.json();

				if (data.success) {
					// Обновляем минимальную цену для всех листингов с таким же market_hash_name
					this.listings.forEach(listing => {
						if (listing.market_hash_name === marketHashName) {
							listing.min_market_price = data.data.min_market_price;
						}
					});
				}
			} catch (error) {
				console.error('Update min market price error:', error);
			}
		},

		async saveTradeUrl() {
			const tradeUrl = this.tradeUrlValue.trim();

			if (!tradeUrl) {
				this.toast.error('Введите Trade URL');
				return;
			}

			try {
				const response = await fetch('/profile/update-trade-url', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						trade_url: tradeUrl
					})
				});

				const data = await response.json();

				if (data.success) {
					// Обновляем Trade URL в клиенте
					this.$emit('update-client', { steam_trade_url: tradeUrl });
					this.toast.success('Trade URL сохранен');
					
					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('tradeUrlRequiredModal'));
					if (modal) {
						modal.hide();
					}

					// Очищаем поле
					this.tradeUrlValue = '';

					// Повторно пытаемся активировать листинг
					if (this.itemToActivate) {
						setTimeout(() => {
							this.confirmActivateListing();
						}, 500);
					}
				} else {
					this.toast.error(data.message || 'Ошибка при сохранении Trade URL');
				}
			} catch (error) {
				console.error('Trade URL save error:', error);
				this.toast.error('Произошла ошибка при сохранении Trade URL');
			}
		}
	},

	mounted() {
		this.loadListings();
	}
}
</script>