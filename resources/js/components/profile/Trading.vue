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

		<!-- Trading listings with tabs -->
		<div v-else-if="listings.length > 0" class="trading-listings">
			<!-- Tabs Navigation -->
			<ul class="nav nav-tabs tab-style1 mb-4" id="tradingTab" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeTradingTab === 'pending' }" id="pending-tab"
						data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab"
						@click="setActiveTradingTab('pending')">
						Черновики
						<span v-if="pendingListings.length > 0" class="badge bg-body-secondary ms-1">{{
							pendingListings.length }}</span>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeTradingTab === 'active' }" id="active-tab"
						data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab"
						@click="setActiveTradingTab('active')">
						Активные
						<span v-if="activeListings.length > 0" class="badge bg-body-secondary ms-1">{{
							activeListings.length }}</span>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeTradingTab === 'reserved' }" id="reserved-tab"
						data-bs-toggle="tab" data-bs-target="#reserved" type="button" role="tab"
						@click="setActiveTradingTab('reserved')">
						Резервированные
						<span v-if="reservedListings.length > 0" class="badge bg-body-secondary ms-1">{{
							reservedListings.length }}</span>
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" :class="{ active: activeTradingTab === 'cancelled' }" id="cancelled-tab"
						data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab"
						@click="setActiveTradingTab('cancelled')">
						Отмененные
						<span v-if="cancelledListings.length > 0" class="badge bg-body-secondary ms-1">{{
							cancelledListings.length }}</span>
					</button>
				</li>
			</ul>

			<div class="tab-content" id="tradingTabContent">
				<!-- Единый компонент для всех табов -->
				<div v-for="tabId in ['pending', 'active', 'reserved', 'cancelled']" :key="tabId" class="tab-pane fade"
					:class="{ 'show active': activeTradingTab === tabId }" :id="tabId" role="tabpanel"
					:aria-labelledby="`${tabId}-tab`" tabindex="0">
					<div v-if="currentTabListings.length > 0 && activeTradingTab === tabId"
						class="product-box-section section-b-space">
						<div class="product-details-box-list">
							<div v-for="listing in currentTabListings" :key="listing.id"
								class="product-details-box gap-2">
								<div class="product-img"
									:style="{ backgroundImage: 'url(' + getItemImage(listing) + ')' }">
								</div>
								<div
									class="description d-flex align-items-center justify-content-between flex-grow-1 gap-3">
									<div>
										<div class="d-flex align-items-center gap-2">
											<h6 class="product-name">{{ getItemName(listing) }}</h6>
										</div>
										<div class="rating-section">
											<div class="d-flex align-items-center gap-2">
												<span v-if="getExteriorTag(listing)" class="badge bg-secondary">
													{{ getExteriorTag(listing) }}
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

											<span class="small">{{ formatPrice(listing.price) }}</span>
											<span class="small">{{ listing.min_market_price ?
												formatPrice(listing.min_market_price) : '-' }}</span>
											<span class="small">{{ listing.buyout_price ?
												formatPrice(listing.buyout_price) : 'Не востребован' }}</span>
										</div>
										<div class="btn-group">
											<!-- Кнопки для черновиков -->
											<template v-if="activeTradingTab === 'pending'">
												<button class="btn theme-outline" @click="activateListing(listing)"
													:disabled="listing.price == 0"
													:title="listing.price == 0 ? 'Сначала установите цену' : 'Активировать'">
													<i class="ri-play-line me-1"></i>Активировать
												</button>
												<button class="btn theme-outline" @click="editPrice(listing)"
													title="Изменить цену">
													<i class="ri-edit-line me-1"></i>Редактировать
												</button>
												<button class="btn theme-outline" @click="removeListing(listing)"
													title="Снять с продажи">
													<i class="ri-delete-bin-line me-1"></i>Удалить
												</button>
											</template>

											<!-- Кнопки для активных -->
											<template v-if="activeTradingTab === 'active'">
												<a :href="`/marketplace/${listing.id}`" target="_blank"
													class="btn theme-outline" title="Просмотреть на маркетплейсе">
													<i class="ri-external-link-line me-1"></i>Смотреть
												</a>
												<button class="btn theme-outline" @click="createAuctionForListing(listing)"
													title="Создать аукцион для этого предмета">
													<i class="ri-auction-line me-1"></i>Аукцион
												</button>
												<button class="btn theme-outline" @click="deactivateListing(listing)"
													title="Деактивировать">
													<i class="ri-pause-line me-1"></i>Пауза
												</button>
											</template>

											<!-- Кнопки для отмененных -->
											<template v-if="activeTradingTab === 'cancelled'">
												<button class="btn theme-outline" @click="reactivateListing(listing)"
													title="Вернуть в торговлю">
													<i class="ri-restart-line me-1"></i>Возобновить
												</button>
												<button class="btn theme-outline" @click="removeListing(listing)"
													title="Удалить окончательно">
													<i class="ri-delete-bin-line me-1"></i>Удалить
												</button>
											</template>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div v-else-if="activeTradingTab === tabId" class="text-center py-5">
						<i :class="[tabConfig[tabId].emptyIcon, 'display-4 text-muted mb-3']"></i>
						<h4>{{ tabConfig[tabId].emptyTitle }}</h4>
						<p class="text-muted">{{ tabConfig[tabId].emptyText }}</p>
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
									<span class="badge bg-success">{{ formatPrice(itemToActivate.price) }}</span>
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

	<!-- Модальное окно создания аукциона -->
	<div class="modal fade" id="createAuctionModal" tabindex="-1" aria-labelledby="createAuctionModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="createAuctionModalLabel">
						<i class="ri-auction-line me-2 text-info"></i>Создать аукцион
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="itemToAuction" class="mb-3">
						<div class="d-flex align-items-center">
							<div class="product-img me-3" :style="{
								backgroundImage: 'url(' + getItemImage(itemToAuction) + ')',
								width: '64px',
								height: '64px',
								backgroundSize: 'contain',
								backgroundRepeat: 'no-repeat',
								backgroundPosition: 'center'
							}"></div>
							<div>
								<h6 class="mb-1">{{ getItemName(itemToAuction) }}</h6>
								<small class="text-muted">{{ itemToAuction.market_hash_name }}</small>
								<div class="mt-1">
									<span class="badge bg-success">{{ formatPrice(itemToAuction.price) }}</span>
								</div>
							</div>
						</div>
					</div>
					
					<div class="alert alert-info">
						<i class="ri-information-line me-2"></i>
						<strong>Что такое аукцион?</strong>
						<ul class="mt-2 mb-0">
							<li>Покупатели могут делать ставки на ваш предмет</li>
							<li>Предмет также остается доступным для прямой покупки по указанной цене</li>
							<li>Аукцион завершится автоматически через заданное время</li>
							<li>Победитель получит предмет по цене своей ставки</li>
						</ul>
					</div>
					
					<p class="mb-0">Вы уверены, что хотите создать аукцион для этого предмета?</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal" @click="clearAuctionData">Отмена</button>
					<button type="button" class="btn theme-btn theme-btn-info" @click="confirmCreateAuction">
						<i class="ri-auction-line me-1"></i>Создать аукцион
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Модальное окно успешного создания аукциона -->
	<div class="modal fade" id="auctionCreatedModal" tabindex="-1" aria-labelledby="auctionCreatedModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="auctionCreatedModalLabel">
						<i class="ri-check-double-line me-2 text-success"></i>Аукцион создан
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="text-center py-3">
						<div class="mb-4">
							<i class="ri-auction-line display-4 text-success"></i>
						</div>
						<h5 class="mb-3">Аукцион успешно создан!</h5>
						<p class="text-muted mb-4">
							Ваш аукцион создан с настройками по умолчанию. Вы можете отредактировать параметры и активировать его в разделе "Мои аукционы".
						</p>
						<div class="alert alert-info">
							<i class="ri-information-line me-2"></i>
							<strong>Настройки по умолчанию:</strong>
							<ul class="mb-0 mt-2 text-start">
								<li>Стартовая цена: 50% от цены листинга</li>
								<li>Длительность: 24 часа</li>
								<li>Минимальный шаг ставки: 1.00</li>
								<li>Статус: Pending (требует активации)</li>
							</ul>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Закрыть</button>
					<button type="button" class="btn theme-btn" @click="goToAuctions">
						<i class="ri-settings-4-line me-1"></i>Управлять аукционами
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
								placeholder="https://steamcommunity.com/tradeoffer/new/?partner=..." required>
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
import axios from 'axios';
import { formatPrice, handleApiError } from '../../utils/helpers';

export default {
	name: 'ProfileTrading',
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
			listings: [],
			isLoading: false,
			itemToDelete: null,
			itemToEdit: null,
			editPriceValue: null,
			itemToAuction: null,
			itemToActivate: null,
			itemToDeactivate: null,
			tradeUrlValue: '',
			activeTradingTab: 'pending'
		}
	},
	computed: {
		isValidPrice() {
			const price = parseFloat(this.editPriceValue);
			return !isNaN(price) && price >= 0.01 && price <= 100000;
		},
		pendingListings() {
			return this.listings.filter(listing => listing.status === 'pending');
		},
		activeListings() {
			return this.listings.filter(listing => listing.status === 'active');
		},
		reservedListings() {
			return this.listings.filter(listing => listing.status === 'reserved');
		},
		cancelledListings() {
			return this.listings.filter(listing => listing.status === 'cancelled');
		},
		currentTabListings() {
			switch (this.activeTradingTab) {
				case 'pending': return this.pendingListings;
				case 'active': return this.activeListings;
				case 'reserved': return this.reservedListings;
				case 'cancelled': return this.cancelledListings;
				default: return [];
			}
		},
		tabConfig() {
			return {
				pending: {
					emptyIcon: 'ri-draft-line',
					emptyTitle: 'Нет черновиков',
					emptyText: 'Черновики появляются когда вы добавляете предметы из инвентаря для продажи'
				},
				active: {
					emptyIcon: 'ri-store-line',
					emptyTitle: 'Нет активных лотов',
					emptyText: 'Активируйте черновики чтобы они появились на маркетплейсе'
				},
				reserved: {
					emptyIcon: 'ri-bookmark-line',
					emptyTitle: 'Нет резервированных лотов', 
					emptyText: 'Здесь отображаются предметы которые находятся в процессе продажи'
				},
				cancelled: {
					emptyIcon: 'ri-close-circle-line',
					emptyTitle: 'Нет отмененных лотов',
					emptyText: 'Здесь будут отображаться лоты которые вы сняли с продажи'
				}
			};
		}
	},
	methods: {
		async loadListings() {
			this.isLoading = true;
			try {
				const response = await axios.get('/api/listings/my');
				const data = response.data;

				if (data.success) {
					this.listings = data.data;
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Error loading listings:', error);
				// Глобальный обработчик покажет toast автоматически
			} finally {
				this.isLoading = false;
			}
		},

		getItemImage(listing) {
			// Проверяем что listing существует
			if (!listing) {
				return '/images/skin_no_image.svg';
			}

			// Сначала используем inventory_icon_url из снимка инвентаря
			if (listing.inventory_icon_url) {
				// Добавляем Steam CDN URL если это не полный URL
				if (!listing.inventory_icon_url.startsWith('http')) {
					return `https://community.steamstatic.com/economy/image/${listing.inventory_icon_url}`;
				}
				return listing.inventory_icon_url;
			}

			// Fallback: пытаемся получить изображение из связанного item
			if (listing.item && listing.item.image_url) {
				return listing.item.image_url;
			}

			return '/images/skin_no_image.svg';
		},

		getItemName(listing) {
			// Проверяем что listing существует
			if (!listing) {
				return 'Неизвестный предмет';
			}

			// Сначала используем inventory_item_name из снимка инвентаря
			if (listing.inventory_item_name) {
				return listing.inventory_item_name;
			}

			// Fallback: пытаемся получить русское название из связанного item
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

		getExteriorTag(listing) {
			// Используем wear_value если есть
			if (listing.wear_value !== null && listing.wear_value !== undefined) {
				if (listing.wear_value <= 0.07) return 'Прямо с завода';
				if (listing.wear_value <= 0.15) return 'Немного поношенное';
				if (listing.wear_value <= 0.38) return 'После полевых испытаний';
				if (listing.wear_value <= 0.45) return 'Поношенное';
				return 'Закалённое в боях';
			}

			// Если есть structured_tags, ищем в них exterior тег
			if (listing.structured_tags && listing.structured_tags.length > 0) {
				const exteriorTag = listing.structured_tags.find(tag =>
					tag.category_code === 'exterior'
				);
				return exteriorTag ? exteriorTag.display_name : null;
			}

			return null;
		},



		handleImageError(event) {
			event.target.src = '/images/skin_no_image.svg';
		},

		activateListing(listing) {
			if (listing.price <= 0) {
				window.toast.error('Сначала установите цену для листинга');
				return;
			}

			this.itemToActivate = listing;
			const modal = new bootstrap.Modal(document.getElementById('confirmActivateModal'));
			modal.show();
		},

		async confirmActivateListing() {
			if (!this.itemToActivate) return;

			try {
				const response = await axios.post('/api/listings/activate', {
					listing_id: this.itemToActivate.id
				});
				const data = response.data;

				if (data.success) {
					// Обновляем статус в локальных данных
					this.itemToActivate.status = 'active';
					this.itemToActivate.listed_at = new Date().toISOString();
					window.toast.success('Листинг активирован');

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
						// Глобальный обработчик покажет toast автоматически

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
						// Глобальный обработчик покажет toast автоматически
					}
				}
			} catch (error) {
				console.error('Activate listing error:', error);
				// Глобальный обработчик покажет toast автоматически
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
				const response = await axios.post('/api/listings/delete', {
					listing_id: this.itemToDelete.id
				});
				const data = response.data;

				if (data.success) {
					const deletedMarketHashName = this.itemToDelete.market_hash_name;

					// Удаляем листинг из локального массива
					this.listings = this.listings.filter(listing => listing.id !== this.itemToDelete.id);
					window.toast.success(data.message || 'Предмет удален из торговли');

					// Обновляем минимальную цену для этого предмета
					await this.updateMinMarketPrice(deletedMarketHashName);
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Delete listing error:', error);
				// Глобальный обработчик покажет toast автоматически
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
				window.toast.error('Введите корректную цену');
				return;
			}

			const numPrice = parseFloat(this.editPriceValue);

			try {
				const response = await axios.post('/api/listings/update-price', {
					listing_id: this.itemToEdit.id,
					price: numPrice
				});
				const data = response.data;

				if (data.success) {
					// Обновляем цену в локальных данных
					this.itemToEdit.price = numPrice;
					window.toast.success('Цена обновлена');

					// Обновляем минимальную цену для этого предмета
					await this.updateMinMarketPrice(this.itemToEdit.market_hash_name);

					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('editPriceModal'));
					if (modal) {
						modal.hide();
					}
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Update price error:', error);
				// Глобальный обработчик покажет toast автоматически
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
				const response = await axios.post('/api/listings/deactivate', {
					listing_id: this.itemToDeactivate.id
				});
				const data = response.data;

				if (data.success) {
					// Обновляем статус в локальных данных
					this.itemToDeactivate.status = 'pending';
					this.itemToDeactivate.listed_at = null;
					window.toast.success('Листинг деактивирован');

					// Обновляем минимальную цену для этого предмета
					await this.updateMinMarketPrice(this.itemToDeactivate.market_hash_name);

					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeactivateModal'));
					if (modal) {
						modal.hide();
					}
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Deactivate listing error:', error);
				// Глобальный обработчик покажет toast автоматически
			} finally {
				this.itemToDeactivate = null;
			}
		},

		async updateMinMarketPrice(marketHashName) {
			try {
				const response = await axios.post('/api/listings/min-price', {
					market_hash_name: marketHashName
				});
				const data = response.data;

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
				window.toast.error('Введите Trade URL');
				return;
			}

			try {
				const response = await axios.post('/profile/update-trade-url', {
					trade_url: tradeUrl
				});
				const data = response.data;

				if (data.success) {
					// Обновляем Trade URL в клиенте
					this.$emit('update-client', { steam_trade_url: tradeUrl });
					window.toast.success('Trade URL сохранен');

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
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Trade URL save error:', error);
				// Глобальный обработчик покажет toast автоматически
			}
		},

		setActiveTradingTab(tab) {
			this.activeTradingTab = tab;
		},

		async reactivateListing(listing) {
			try {
				const response = await axios.post('/api/listings/reactivate', {
					listing_id: listing.id
				});
				const data = response.data;

				if (data.success) {
					// Обновляем статус в локальных данных
					listing.status = 'pending';
					window.toast.success('Листинг возвращен в черновики');
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Reactivate listing error:', error);
				// Глобальный обработчик покажет toast автоматически
			}
		},

		createAuctionForListing(listing) {
			this.itemToAuction = listing;
			const modal = new bootstrap.Modal(document.getElementById('createAuctionModal'));
			modal.show();
		},

		async confirmCreateAuction() {
			if (!this.itemToAuction) return;

			try {
				const response = await axios.post('/api/auctions', {
					listing_id: this.itemToAuction.id,
					starting_price: Math.max(1.00, this.itemToAuction.price * 0.5), // Стартовая цена - 50% от цены листинга
					duration_hours: 24, // 24 часа по умолчанию
					min_bid_increment: 1.00 // Минимальное увеличение ставки
				}, {
					// Отключаем глобальный обработчик ошибок
					skipErrorHandler: true
				});
				const data = response.data;

				if (data.success) {
					// Очищаем данные только при успехе
					this.itemToAuction = null;
					
					// Закрываем модальное окно создания
					const modal = bootstrap.Modal.getInstance(document.getElementById('createAuctionModal'));
					if (modal) {
						modal.hide();
					}

					// Показываем модальное окно успеха
					setTimeout(() => {
						const successModal = new bootstrap.Modal(document.getElementById('auctionCreatedModal'));
						successModal.show();
					}, 300);
					
				} else {
					// Глобальный обработчик покажет toast автоматически
					// НЕ очищаем itemToAuction, чтобы модальное окно оставалось с данными
				}
			} catch (error) {
				console.error('Create auction error:', error);
				// Глобальный обработчик покажет toast автоматически
				// НЕ очищаем itemToAuction, чтобы модальное окно оставалось с данными
			}
		},

		clearAuctionData() {
			// Очищаем данные о предмете для аукциона
			this.itemToAuction = null;
		},

		goToAuctions() {
			// Закрываем модальное окно
			const modal = bootstrap.Modal.getInstance(document.getElementById('auctionCreatedModal'));
			if (modal) {
				modal.hide();
			}
			
			// Переходим на раздел аукционов в профиле
			window.location.href = '/profile#auctions';
		}
	},

	mounted() {
		this.loadListings();
		
		// Добавляем обработчик закрытия модального окна создания аукциона
		const createAuctionModal = document.getElementById('createAuctionModal');
		if (createAuctionModal) {
			createAuctionModal.addEventListener('hidden.bs.modal', () => {
				this.clearAuctionData();
			});
		}
	}
}
</script>