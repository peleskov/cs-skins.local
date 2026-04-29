<template>
	<div id="Auctions" class="change-profile-content position-relative">
		<a href="/profile#profile" class="btn-to-profile d-lg-none"><i class="m-ico m-ico-back"></i>Назад</a>
		<div class="title">
			<div class="loader-line d-none d-lg-block"></div>
			<h3 class="mb-4 mb-lg-0">Мои аукционы</h3>
		</div>

		<!-- Loading state -->
		<div v-if="isLoading" class="text-center py-5">
			<div class="loader-gif">
				<div class="radar-ring"></div>
				<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
			</div>
			<p class="mt-3">Загружаем ваши аукционы...</p>
		</div>

		<!-- Auctions with tabs -->
		<div v-else-if="auctions.length > 0" class="auction-listings">
			<!-- Tabs Navigation -->
			<ul class="nav nav-tabs tab-style1 mb-4" id="auctionTab" role="tablist">
				<li class="flex-fill nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeAuctionTab === 'pending' }" id="pending-tab"
						data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab"
						@click="setActiveAuctionTab('pending')">
						Черновики
						<span v-if="pendingAuctions.length > 0"
							class="badge bg-body-secondary ms-1 d-none d-leg-block">{{
								pendingAuctions.length }}</span>
					</button>
				</li>
				<li class="flex-fill nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeAuctionTab === 'active' }" id="active-tab"
						data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab"
						@click="setActiveAuctionTab('active')">
						Активные
						<span v-if="activeAuctions.length > 0"
							class="badge bg-body-secondary ms-1 d-none d-leg-block">{{
								activeAuctions.length }}</span>
					</button>
				</li>
				<li class="flex-fill nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeAuctionTab === 'completed' }" id="completed-tab"
						data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab"
						@click="setActiveAuctionTab('completed')">
						Завершенные
						<span v-if="completedAuctions.length > 0"
							class="badge bg-body-secondary ms-1 d-none d-leg-block">{{
								completedAuctions.length }}</span>
					</button>
				</li>
			</ul>

			<div class="tab-content" id="auctionTabContent">
				<!-- Единый компонент для всех табов -->
				<div v-for="tabId in ['pending', 'active', 'completed']" :key="tabId" class="tab-pane fade"
					:class="{ 'show active': activeAuctionTab === tabId }" :id="tabId" role="tabpanel"
					:aria-labelledby="`${tabId}-tab`" tabindex="0">
					<div v-if="currentTabAuctions.length > 0 && activeAuctionTab === tabId"
						class="product-box-section section-b-space">
						<div class="product-details-box-list">
							<div v-for="auction in currentTabAuctions" :key="auction.id"
								class="product-details-box gap-2">
								<div class="product-img"
									:style="{ backgroundImage: 'url(' + getItemImage(auction.listing) + ')' }">
								</div>
								<div
									class="description d-flex flex-column flex-lg-row align-items-center justify-content-between flex-grow-1 gap-3">
									<div>
										<div class="d-flex align-items-center gap-2">
											<h6 class="product-name">{{ getItemName(auction.listing) }}</h6>
										</div>
										<div class="rating-section">
											<div class="d-flex align-items-center gap-2">
												<span v-if="getExteriorTag(auction.listing)" class="badge bg-secondary">
													{{ getExteriorTag(auction.listing) }}
												</span>
												<small class="text-muted">{{ formatDate(auction.created_at) }}</small>
											</div>
										</div>
										<p class="text-muted mb-0">{{ auction.listing.market_hash_name }}</p>
									</div>
									<div class="h-100 d-flex flex-column justify-content-between">
										<div v-if="auction.status !== 'completed'"
											class="product-box-price col4 d-grid gap-2 mb-4 text-center">
											<i class="ri-price-tag-3-line text-muted small" title="Текущая цена"></i>
											<i class="ri-add-circle-line text-muted small" title="Минимальный шаг"></i>
											<i class="ri-time-line text-muted small" title="Длительность"></i>
											<i class="ri-refresh-line text-muted small" title="Автопродление"></i>

											<span class="small" v-html="formatPrice(auction.current_price)"></span>
											<span class="small" v-html="formatPrice(auction.min_bid_increment)"></span>
											<span class="small">{{ formatDurationFromHours(auction.duration_hours)
											}}</span>
											<span class="small">{{ auction.auto_extend ? 'Да' : 'Нет' }}</span>
										</div>
										<div v-else class="product-box-price d-flex flex-column gap-1 mb-4">
											<div class="d-flex justify-content-between">
												<small class="text-muted">Финальная цена</small><br>
												<small v-html="formatPrice(auction.current_price)"></small>
											</div>
											<div v-if="auction.last_bidder" class="d-flex justify-content-between">
												<small class="text-muted">Победитель</small><br>
												<small>{{ auction.last_bidder.name || 'Неизвестный' }}</small>
											</div>
											<div v-if="auction.bid_count > 0" class="d-flex justify-content-between">
												<small class="text-muted">Всего ставок</small><br>
												<small>{{ auction.bid_count }}</small>
											</div>
											<div class="d-flex justify-content-between">
												<small class="text-muted">Завершен</small><br>
												<small>{{ formatDate(auction.updated_at) }}</small>
											</div>
											<div v-if="auction.order && auction.order.order_number"
												class="d-flex justify-content-between">
												<small class="text-muted">Заказ</small>
												<small>#{{ auction.order.order_number }}</small>
											</div>
											<div v-else-if="auction.bid_count > 0"
												class="d-flex justify-content-between">
												<small class="text-muted">Заказ</small>
												<small>Не создан</small>
											</div>
											<div v-else class="d-flex justify-content-between">
												<small class="text-muted">Заказ</small>
												<small>Нет ставок</small>
											</div>
										</div>

										<div class="btn-group">
											<!-- Кнопки для черновиков -->
											<button v-if="auction.status === 'pending'" class="btn btn-sm theme-outline"
												data-bs-toggle="modal" data-bs-target="#confirmActivateAuctionModal"
												@click="setAuctionToActivate(auction)">
												<i class="ri-play-line me-1"></i>Активировать
											</button>
											<button v-if="auction.status === 'pending'" class="btn btn-sm theme-outline"
												data-bs-toggle="modal" data-bs-target="#editAuctionModal"
												@click="setAuctionToEdit(auction)">
												<i class="ri-edit-line me-1"></i>Редактировать
											</button>
											<button v-if="auction.status === 'pending'" class="btn btn-sm theme-outline"
												data-bs-toggle="modal" data-bs-target="#confirmDeleteAuctionModal"
												@click="setAuctionToDelete(auction)">
												<i class="ri-delete-bin-line me-1"></i>Удалить
											</button>

											<!-- Кнопки для активных аукционов -->
											<button v-if="auction.status === 'active'" class="btn btn-sm theme-outline"
												@click="deactivateAuction(auction)">
												<i class="ri-pause-line me-1"></i>Пауза
											</button>
											<a v-if="auction.status === 'active'"
												:href="`/marketplace/${auction.listing_id}`"
												class="btn btn-sm theme-outline">
												<i class="ri-eye-line me-1"></i>Смотреть
											</a>

										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<!-- Empty state для каждого таба -->
					<EmptyState v-else-if="activeAuctionTab === tabId" icon="m-ico m-ico-empty-box"
						:title="getEmptyMessage(tabId)" :description="getEmptyDescription(tabId)" />
				</div>
			</div>
		</div>

		<!-- Empty state when no auctions -->
		<EmptyState v-else icon="m-ico m-ico-empty-box" title="У вас пока нет аукционов"
			description="Создать новый аукцион можно в разделе «Торговля» для любого активного листинга."
			button-text="Перейти к торговле" button-href="/profile#trading" />
	</div>

	<!-- Модальное окно редактирования аукциона -->
	<div class="modal fade" id="editAuctionModal" tabindex="-1" aria-labelledby="editAuctionModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="editAuctionModalLabel">
						<i class="ri-edit-line me-2 text-info"></i>Редактировать аукцион
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="auctionToEdit" class="mb-4">
						<!-- Информация о предмете -->
						<div class="d-flex align-items-center mb-4 p-3 bg-light rounded">
							<div class="product-img me-3" :style="{
								backgroundImage: 'url(' + getItemImage(auctionToEdit.listing) + ')',
								width: '80px',
								height: '80px',
								backgroundSize: 'contain',
								backgroundRepeat: 'no-repeat',
								backgroundPosition: 'center'
							}"></div>
							<div>
								<h6 class="mb-1">{{ getItemName(auctionToEdit.listing) }}</h6>
								<small class="text-muted">{{ auctionToEdit.listing.market_hash_name }}</small>
								<div class="mt-1">
									<span class="badge bg-secondary">{{ getExteriorTag(auctionToEdit.listing) }}</span>
								</div>
							</div>
						</div>

						<!-- Форма редактирования -->
						<div class="row g-3">
							<div class="col-md-6">
								<label for="editStartingPrice" class="form-label">Стартовая цена</label>
								<div class="input-group">
									<span class="input-group-text">₽</span>
									<input type="number" class="form-control" id="editStartingPrice"
										v-model="editForm.starting_price" min="0.01" step="0.01"
										:class="{ 'is-invalid': !isValidStartingPrice }">
								</div>
								<div v-if="!isValidStartingPrice" class="invalid-feedback">
									Цена должна быть от 0.01₽ до 100,000₽
								</div>
							</div>

							<div class="col-md-6">
								<label for="editMinBidIncrement" class="form-label">Минимальный шаг ставки</label>
								<div class="input-group">
									<span class="input-group-text">₽</span>
									<input type="number" class="form-control" id="editMinBidIncrement"
										v-model="editForm.min_bid_increment" min="1" step="1"
										:class="{ 'is-invalid': !isValidMinBidIncrement }">
								</div>
								<div v-if="!isValidMinBidIncrement" class="invalid-feedback">
									Минимальный шаг должен быть от 1₽
								</div>
							</div>

							<div class="col-md-6">
								<label for="editDurationHours" class="form-label">Длительность (часов)</label>
								<select class="form-select" id="editDurationHours" v-model="editForm.duration_hours">
									<option value="1">1 час</option>
									<option value="3">3 часа</option>
									<option value="6">6 часов</option>
									<option value="12">12 часов</option>
									<option value="24" selected>24 часа</option>
									<option value="48">48 часов</option>
									<option value="72">72 часа</option>
									<option value="168">7 дней</option>
								</select>
							</div>

							<div class="col-md-6">
								<label for="editAutoExtend" class="form-label d-block">Настройки</label>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" id="editAutoExtend"
										v-model="editForm.auto_extend">
									<label class="form-check-label" for="editAutoExtend">
										Автопродление на 5 минут при ставке в последние 5 минут
									</label>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal"
						@click="clearEditForm">Отмена</button>
					<button type="button" class="btn theme-btn" @click="saveAuctionChanges"
						:disabled="!isValidEditForm">
						<i class="ri-save-line me-1"></i>Сохранить изменения
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Модальное окно подтверждения удаления аукциона -->
	<div class="modal fade" id="confirmDeleteAuctionModal" tabindex="-1"
		aria-labelledby="confirmDeleteAuctionModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmDeleteAuctionModalLabel">
						<i class="ri-delete-bin-line me-2 text-danger"></i>Удалить аукцион
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="auctionToDelete" class="mb-3">
						<div class="d-flex align-items-center">
							<div class="product-img me-3" :style="{
								backgroundImage: 'url(' + getItemImage(auctionToDelete.listing) + ')',
								width: '64px',
								height: '64px',
								backgroundSize: 'contain',
								backgroundRepeat: 'no-repeat',
								backgroundPosition: 'center'
							}"></div>
							<div>
								<h6 class="mb-1">{{ getItemName(auctionToDelete.listing) }}</h6>
								<small class="text-muted">Стартовая цена: <span
										v-html="formatPrice(auctionToDelete.starting_price)"></span></small>
								<div class="mt-1">
									<span class="badge bg-warning">{{ getStatusText(auctionToDelete.status) }}</span>
								</div>
							</div>
						</div>
					</div>
					<p>Вы уверены, что хотите удалить этот аукцион?</p>
					<div class="alert alert-warning">
						<i class="ri-information-line me-2"></i>
						<strong>Внимание:</strong> Аукцион будет удален навсегда. Это действие нельзя отменить.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn theme-btn-danger" @click="confirmDeleteAuction">
						<i class="ri-delete-bin-line me-1"></i>Удалить аукцион
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Модальное окно подтверждения активации аукциона -->
	<div class="modal fade" id="confirmActivateAuctionModal" tabindex="-1"
		aria-labelledby="confirmActivateAuctionModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmActivateAuctionModalLabel">
						<i class="ri-play-line me-2 text-success"></i>Активировать аукцион
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="auctionToActivate" class="mb-3">
						<div class="d-flex align-items-center mb-3">
							<div class="product-img me-3" :style="{
								backgroundImage: 'url(' + getItemImage(auctionToActivate.listing) + ')',
								width: '64px',
								height: '64px',
								backgroundSize: 'contain',
								backgroundRepeat: 'no-repeat',
								backgroundPosition: 'center'
							}"></div>
							<div>
								<h6 class="mb-1">{{ getItemName(auctionToActivate.listing) }}</h6>
								<small class="text-muted">{{ auctionToActivate.listing.market_hash_name }}</small>
								<div class="mt-1">
									<span class="badge bg-secondary">{{ getExteriorTag(auctionToActivate.listing)
									}}</span>
								</div>
							</div>
						</div>

						<!-- Данные аукциона -->
						<div class="p-3 bg-light rounded">
							<h6 class="mb-3">Параметры аукциона:</h6>
							<div class="row g-2 small">
								<div class="col-6">
									<strong>Стартовая цена:</strong><br>
									<span v-html="formatPrice(auctionToActivate.starting_price)"></span>
								</div>
								<div class="col-6">
									<strong>Минимальный шаг:</strong><br>
									<span v-html="formatPrice(auctionToActivate.min_bid_increment)"></span>
								</div>
								<div class="col-6">
									<strong>Длительность:</strong><br>
									{{ formatDurationFromHours(auctionToActivate.duration_hours) }}
								</div>
								<div class="col-6">
									<strong>Автопродление:</strong><br>
									{{ auctionToActivate.auto_extend ? 'Включено' : 'Отключено' }}
								</div>
								<div class="col-12 mt-2">
									<strong>Цена выкупа:</strong><br>
									<span v-html="formatPrice(auctionToActivate.listing.price)"></span>
								</div>
							</div>
						</div>
					</div>
					<p class="mb-3">Вы уверены, что хотите активировать этот аукцион?</p>
					<div class="alert alert-info">
						<i class="ri-information-line me-2"></i>
						<strong>Внимание:</strong> После активации аукцион нельзя будет редактировать. Деактивация
						возможна только при отсутствии ставок.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn" @click="confirmActivateAuction">
						<i class="ri-play-line me-1"></i>Активировать сейчас
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { formatPrice } from '../../../shared/utils/helpers';
import axios from 'axios';
import EmptyState from '../EmptyState.vue';

export default {
	name: 'ProfileAuctions',
	components: {
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
			auctions: [],
			isLoading: false,
			activeAuctionTab: 'pending',
			auctionToEdit: null,
			auctionToDelete: null,
			auctionToActivate: null,
			editForm: {
				starting_price: 0,
				min_bid_increment: 1,
				duration_hours: 24,
				auto_extend: true
			}
		}
	},
	computed: {
		pendingAuctions() {
			return this.auctions.filter(auction => auction.status === 'pending');
		},
		activeAuctions() {
			return this.auctions.filter(auction => auction.status === 'active');
		},
		completedAuctions() {
			return this.auctions.filter(auction => auction.status === 'completed');
		},
		currentTabAuctions() {
			switch (this.activeAuctionTab) {
				case 'pending': return this.pendingAuctions;
				case 'active': return this.activeAuctions;
				case 'completed': return this.completedAuctions;
				default: return [];
			}
		},

		isValidStartingPrice() {
			const price = parseFloat(this.editForm.starting_price);
			return !isNaN(price) && price >= 0.01 && price <= 100000;
		},

		isValidMinBidIncrement() {
			const increment = parseFloat(this.editForm.min_bid_increment);
			return !isNaN(increment) && increment >= 1;
		},

		isValidEditForm() {
			return this.isValidStartingPrice && this.isValidMinBidIncrement;
		}
	},
	methods: {
		setActiveAuctionTab(tab) {
			this.activeAuctionTab = tab;
		},

		async loadAuctions() {
			this.isLoading = true;
			try {
				const response = await axios.get('/profile/auctions');
				const data = response.data;

				if (data.success) {
					this.auctions = data.data;
				}
			} catch (error) {
				console.error('Error loading auctions:', error);
			} finally {
				this.isLoading = false;
			}
		},

		getItemImage(listing) {
			if (!listing) {
				return '/images/skin_no_image.svg';
			}

			if (listing.inventory_icon_url) {
				if (!listing.inventory_icon_url.startsWith('http')) {
					return `https://community.steamstatic.com/economy/image/${listing.inventory_icon_url}`;
				}
				return listing.inventory_icon_url;
			}

			if (listing.item && listing.item.image_url) {
				return listing.item.image_url;
			}

			return '/images/skin_no_image.svg';
		},

		getItemName(listing) {
			if (!listing) {
				return 'Неизвестный предмет';
			}

			if (listing.inventory_item_name) {
				return listing.inventory_item_name;
			}

			if (listing.item && listing.item.name_ru) {
				return listing.item.name_ru;
			}

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

		getExteriorTag(listing) {
			if (listing.wear_value !== null && listing.wear_value !== undefined) {
				if (listing.wear_value >= 0.45) return 'Закаленное в боях';
				if (listing.wear_value >= 0.37) return 'Поношенное';
				if (listing.wear_value >= 0.15) return 'После полевых испытаний';
				if (listing.wear_value >= 0.07) return 'Немного поношенное';
				return 'Прямо с завода';
			}
			return null;
		},

		getStatusText(status) {
			switch (status) {
				case 'pending': return 'Черновик';
				case 'active': return 'Активен';
				case 'completed': return 'Завершен';
				case 'cancelled': return 'Отменен';
				default: return status;
			}
		},

		getStatusBadgeClass(status) {
			switch (status) {
				case 'pending': return 'bg-warning';
				case 'active': return 'bg-success';
				case 'completed': return 'bg-info';
				case 'cancelled': return 'bg-secondary';
				default: return 'bg-secondary';
			}
		},

		formatDurationFromHours(hours) {
			if (!hours) return 'Не указано';

			const days = Math.floor(hours / 24);
			const remainingHours = hours % 24;

			if (days >= 1) {
				if (remainingHours > 0) {
					return `${days}д ${remainingHours}ч`;
				}
				return `${days} д`;
			}

			return `${hours} ч`;
		},

		getEmptyMessage(tabId) {
			switch (tabId) {
				case 'pending': return 'Нет черновиков аукционов';
				case 'active': return 'Нет активных аукционов';
				case 'completed': return 'Нет завершенных аукционов';
				default: return 'Нет аукционов';
			}
		},

		getEmptyDescription(tabId) {
			switch (tabId) {
				case 'pending': return 'Созданные аукционы появятся здесь для активации';
				case 'active': return 'Запущенные аукционы будут отображаться в этом разделе';
				case 'completed': return 'Завершенные аукционы сохраняются в этом разделе';
				default: return '';
			}
		},

		setAuctionToEdit(auction) {
			this.auctionToEdit = auction;
			// Заполняем форму текущими значениями
			this.editForm = {
				starting_price: auction.starting_price,
				min_bid_increment: auction.min_bid_increment,
				duration_hours: auction.duration_hours || 24,
				auto_extend: auction.auto_extend ?? true
			};
		},

		clearEditForm() {
			this.auctionToEdit = null;
			this.editForm = {
				starting_price: 0,
				min_bid_increment: 1,
				duration_hours: 24,
				auto_extend: true
			};
		},


		async saveAuctionChanges() {
			if (!this.auctionToEdit || !this.isValidEditForm) return;

			try {
				const response = await axios.patch(`/api/auctions/${this.auctionToEdit.id}`, {
					starting_price: this.editForm.starting_price,
					min_bid_increment: this.editForm.min_bid_increment,
					duration_hours: this.editForm.duration_hours,
					auto_extend: this.editForm.auto_extend
				});

				const data = response.data;
				if (data.success) {
					// Обновляем аукцион в списке
					const index = this.auctions.findIndex(a => a.id === this.auctionToEdit.id);
					if (index !== -1) {
						// Обновляем только нужные поля
						this.auctions[index] = { ...this.auctions[index], ...data.auction };
					}

					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('editAuctionModal'));
					if (modal) {
						modal.hide();
					}

					this.clearEditForm();
					window.toast.success('Аукцион успешно обновлен');
				}
			} catch (error) {
				console.error('Error updating auction:', error);
			}
		},

		setAuctionToDelete(auction) {
			this.auctionToDelete = auction;
		},

		setAuctionToActivate(auction) {
			this.auctionToActivate = auction;
		},

		async confirmDeleteAuction() {
			if (!this.auctionToDelete) return;

			try {
				const response = await axios.delete(`/api/auctions/${this.auctionToDelete.id}`);
				const data = response.data;

				if (data.success) {
					// Удаляем аукцион из списка
					this.auctions = this.auctions.filter(a => a.id !== this.auctionToDelete.id);

					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteAuctionModal'));
					if (modal) {
						modal.hide();
					}

					this.auctionToDelete = null;
					window.toast.success('Аукцион успешно удален');
				}
			} catch (error) {
				console.error('Error deleting auction:', error);
			} finally {
				// Очищаем данные в любом случае
				this.auctionToDelete = null;
			}
		},

		async confirmActivateAuction() {
			if (!this.auctionToActivate) return;

			const response = await axios.patch(`/api/auctions/${this.auctionToActivate.id}/activate`);
			const data = response.data;

			if (data.success) {
				// Обновляем аукцион в списке
				const index = this.auctions.findIndex(a => a.id === this.auctionToActivate.id);
				if (index !== -1) {
					this.auctions[index] = { ...this.auctions[index], ...data.auction };
				}

				// Закрываем модальное окно
				const modal = bootstrap.Modal.getInstance(document.getElementById('confirmActivateAuctionModal'));
				if (modal) {
					modal.hide();
				}

				this.auctionToActivate = null;
				window.toast.success('Аукцион успешно активирован');
			}
		},

		async deactivateAuction(auction) {
			const response = await axios.patch(`/api/auctions/${auction.id}/deactivate`);
			const data = response.data;

			if (data.success) {
				// Обновляем аукцион в списке
				const index = this.auctions.findIndex(a => a.id === auction.id);
				if (index !== -1) {
					this.auctions[index] = { ...this.auctions[index], ...data.auction };
				}

				window.toast.success('Аукцион переведен в черновик');
			}
		},

	},

	mounted() {
		this.loadAuctions();
	}
}
</script>