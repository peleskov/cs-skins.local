<template>
	<div id="Trading" class="change-profile-content position-relative">
		<a href="/profile#profile" class="btn-to-profile d-lg-none"><i class="m-ico m-ico-back"></i>Назад</a>
		<div class="title">
			<div class="loader-line d-none d-lg-block"></div>
			<div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center">
				<h3 class="mb-4 mb-lg-0">Торговля</h3>
				<div class="btn-group">
					<a href="/rukovodstvo-po-torgovle" class="btn theme-outline" title="Руководство по торговле">
						<i class="ri-question-line me-1"></i>
						<span>Как начать торговлю</span>
					</a>
					<a v-if="isWebStoreExtension" :href="extensionDownloadUrl" target="_blank" rel="noopener"
						class="d-none d-lg-flex btn theme-outline"><i class="ri-chrome-line me-2"></i>Расширение </a>
					<a v-else class="d-none d-lg-flex btn theme-outline" href="#" data-bs-toggle="modal"
						data-bs-target="#extensionModal"><i class="ri-download-2-line me-2"></i>Расширение </a>
				</div>
			</div>
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
				<li class="nav-item flex-fill flex-lg-grow-0 flex-lg-shrink-0" role="presentation">
					<button class="nav-link d-flex align-items-center"
						:class="{ active: activeTradingTab === 'pending' }" id="pending-tab" data-bs-toggle="tab"
						data-bs-target="#pending" type="button" role="tab" @click="setActiveTradingTab('pending')">
						Черновики
						<span v-if="pendingListings.length > 0"
							class="badge bg-body-secondary ms-1 d-none d-lg-block">{{
								pendingListings.length }}</span>
					</button>
				</li>
				<li class="nav-item flex-fill flex-lg-grow-0 flex-lg-shrink-0" role="presentation">
					<button class="nav-link d-flex align-items-center"
						:class="{ active: activeTradingTab === 'active' }" id="active-tab" data-bs-toggle="tab"
						data-bs-target="#active" type="button" role="tab" @click="setActiveTradingTab('active')">
						Активные
						<span v-if="activeListings.length > 0" class="badge bg-body-secondary ms-1 d-none d-lg-block">{{
							activeListings.length }}</span>
					</button>
				</li>
				<li class="nav-item flex-fill flex-lg-grow-0 flex-lg-shrink-0" role="presentation">
					<button class="nav-link d-flex align-items-center"
						:class="{ active: activeTradingTab === 'reserved' }" id="reserved-tab" data-bs-toggle="tab"
						data-bs-target="#reserved" type="button" role="tab" @click="setActiveTradingTab('reserved')">
						Резерв
						<span v-if="reservedListings.length > 0"
							class="badge bg-body-secondary ms-1 d-none d-lg-block">{{
								reservedListings.length }}</span>
					</button>
				</li>
				<li class="nav-item flex-fill flex-lg-grow-0 flex-lg-shrink-0" role="presentation">
					<button class="nav-link d-flex align-items-center"
						:class="{ active: activeTradingTab === 'cancelled' }" id="cancelled-tab" data-bs-toggle="tab"
						data-bs-target="#cancelled" type="button" role="tab" @click="setActiveTradingTab('cancelled')">
						Отмененные
						<span v-if="cancelledListings.length > 0"
							class="badge bg-body-secondary ms-1 d-none d-lg-block">{{
								cancelledListings.length }}</span>
					</button>
				</li>
			</ul>

			<div class="tab-content" id="tradingTabContent">
				<!-- Единый компонент для всех табов -->
				<div v-for="tabId in ['pending', 'active', 'reserved', 'cancelled']" :key="tabId" class="tab-pane fade"
					:class="{ 'show active': activeTradingTab === tabId }" :id="tabId" role="tabpanel"
					:aria-labelledby="`${tabId}-tab`" tabindex="0">
					<!-- Мобильная сетка карточек -->
					<div v-if="currentTabListings.length > 0 && activeTradingTab === tabId" class="row g-3 d-lg-none">
						<div v-for="listing in currentTabListings" :key="`m-${listing.id}`" class="col-6">
							<div class="m-listing-card h-100 d-flex flex-column" :class="getRarityClass(listing)">
								<div class="m-lc-img">
									<img class="w-100" :src="getItemImage(listing)" :alt="getItemName(listing)">
								</div>
								<div class="px-3 mt-2 m-lc-title">{{ getItemName(listing) }}</div>
								<div class="px-3 m-lc-wear">
									<span v-if="getExteriorTag(listing)">{{ getExteriorTag(listing) }}</span>
								</div>
								<div class="px-3 mt-2 m-trade-prices">
									<div class="d-flex justify-content-between">
										<span class="m-trade-label">Цена</span>
										<span class="m-trade-value" v-html="formatPrice(listing.price)"></span>
									</div>
									<div class="d-flex justify-content-between">
										<span class="m-trade-label">ТОП-1</span>
										<span class="m-trade-value"
											v-html="listing.min_market_price ? formatPrice(listing.min_market_price) : '-'"></span>
									</div>
									<div class="d-flex justify-content-between">
										<span class="m-trade-label">Выкуп</span>
										<span class="m-trade-value"
											v-html="listing.buyout_price ? formatPrice(listing.buyout_price) : '-'"></span>
									</div>
								</div>
								<div class="m-lc-actions px-3 pb-3 pt-2 mt-auto d-flex flex-column gap-2">
									<template v-if="activeTradingTab === 'pending'">
										<button class="btn m-trade-btn" @click="activateListing(listing)"
											:disabled="listing.price == 0">
											<i class="ri-play-line me-1"></i>Активировать
										</button>
										<button class="btn m-trade-btn-outline" @click="editPrice(listing)">
											<i class="ri-edit-line me-1"></i>Цена
										</button>
										<button class="btn m-trade-btn-outline" @click="removeListing(listing)">
											<i class="ri-delete-bin-line me-1"></i>Удалить
										</button>
									</template>
									<template v-if="activeTradingTab === 'active'">
										<a :href="`/marketplace/${listing.id}`" target="_blank"
											class="btn m-trade-btn-outline">
											<i class="ri-external-link-line me-1"></i>Смотреть
										</a>
										<button class="btn m-trade-btn-outline" @click="editPrice(listing)">
											<i class="ri-edit-line me-1"></i>Цена
										</button>
										<button class="btn m-trade-btn-outline"
											@click="createAuctionForListing(listing)">
											<i class="ri-auction-line me-1"></i>Аукцион
										</button>
										<button class="btn m-trade-btn-outline" @click="deactivateListing(listing)">
											<i class="ri-pause-line me-1"></i>Пауза
										</button>
									</template>
									<template v-if="activeTradingTab === 'cancelled'">
										<button class="btn m-trade-btn" @click="reactivateListing(listing)">
											<i class="ri-restart-line me-1"></i>Возобновить
										</button>
										<button class="btn m-trade-btn-outline" @click="removeListing(listing)">
											<i class="ri-delete-bin-line me-1"></i>Удалить
										</button>
									</template>
								</div>
							</div>
						</div>
					</div>
					<!-- Десктопный список -->
					<div v-if="currentTabListings.length > 0 && activeTradingTab === tabId"
						class="product-box-section section-b-space d-none d-lg-block">
						<div class="product-details-box-list">
							<div v-for="listing in currentTabListings" :key="listing.id"
								class="product-details-box flex-column flex-md-row gap-2">
								<div class="product-img"
									:style="{ backgroundImage: 'url(' + getItemImage(listing) + ')' }">
								</div>
								<div
									class="description d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between flex-grow-1 gap-3">
									<div class="w-100 w-md-auto">
										<div class="d-flex align-items-center gap-2">
											<h6 class="product-name">{{ getItemName(listing) }}</h6>
										</div>
										<div class="rating-section">
											<div class="d-flex align-items-center gap-2">
												<span v-if="getExteriorTag(listing)" class="badge bg-secondary">
													{{ getExteriorTag(listing) }}
													<span
														v-if="listing.wear_value !== null && listing.wear_value !== undefined"
														class="ms-1">
														({{ listing.wear_value.toFixed(4) }})
													</span>
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

											<span class="small content-color"
												v-html="formatPrice(listing.price)"></span>
											<span class="small content-color" v-html="listing.min_market_price ?
												formatPrice(listing.min_market_price) : '-'"></span>
											<span class="small content-color" v-html="listing.buyout_price ?
												formatPrice(listing.buyout_price) : 'Не востребован'"></span>
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
												<button class="btn theme-outline" @click="editPrice(listing)"
													title="Изменить цену">
													<i class="ri-edit-line me-1"></i>Редактировать
												</button>
												<button class="btn theme-outline"
													@click="createAuctionForListing(listing)"
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
					<EmptyState v-else-if="activeTradingTab === tabId" :icon="tabConfig[tabId].emptyIcon"
						:title="tabConfig[tabId].emptyTitle" :description="tabConfig[tabId].emptyText" />
				</div>
			</div>
		</div>

		<!-- Empty state -->
		<EmptyState v-else icon="ri-shopping-bag-3-line" title="Нет активных листингов"
			description="Перейдите в раздел «Инвентарь» чтобы выставить предметы на продажу"
			button-text="Перейти в инвентарь" button-href="#inventory" />
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
								:placeholder="itemToEdit ? itemToEdit.price : '0'" min="0.01" max="10000000" step="0.01"
								@keyup.enter="confirmEditPrice">
							<span class="input-group-text">₽</span>
						</div>
						<div class="form-text">
							Минимальная цена: 0.01 ₽
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
									<span class="badge bg-success" v-html="formatPrice(itemToAuction.price)"></span>
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
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal"
						@click="clearAuctionData">Отмена</button>
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
							Ваш аукцион создан с настройками по умолчанию. Вы можете отредактировать параметры и
							активировать его в разделе "Мои аукционы".
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
import { formatPrice, handleApiError } from '../../../shared/utils/helpers';
import EmptyState from '../EmptyState.vue';

export default {
	components: { EmptyState },
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
	computed: {
		extensionDownloadUrl() {
			return document.querySelector('[data-extension-download-url]')?.dataset.extensionDownloadUrl || '';
		},
		isWebStoreExtension() {
			return this.extensionDownloadUrl.startsWith('https://chromewebstore.google.com/');
		}
	},
	data() {
		return {
			listings: [],
			isLoading: false,
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
			return !isNaN(price) && price >= 0.01 && price <= 10000000;
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
		getRarityClass(listing) {
			if (!listing || !listing.structured_tags) return '';
			const tag = listing.structured_tags.find(t => t.category_code === 'rarity');
			return tag ? `rarity-${tag.normalized_value}` : '';
		},
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

		async activateListing(listing) {
			if (listing.price <= 0) {
				window.toast.error('Сначала установите цену для листинга');
				return;
			}

			try {
				const response = await axios.post('/api/listings/activate', {
					listing_id: listing.id
				});
				const data = response.data;

				if (data.success) {
					listing.status = 'active';
					listing.listed_at = new Date().toISOString();
					window.toast.success('Листинг активирован');
					await this.updateMinMarketPrice(listing.market_hash_name);
				} else {
					if (data.require_trade_url) {
						this.itemToActivate = listing;
						const tradeUrlModal = new bootstrap.Modal(document.getElementById('tradeUrlRequiredModal'));
						tradeUrlModal.show();
					}
				}
			} catch (error) {
				console.error('Activate listing error:', error);
			}
		},

		async removeListing(listing) {
			try {
				const response = await axios.post('/api/listings/delete', {
					listing_id: listing.id
				});
				const data = response.data;

				if (data.success) {
					this.listings = this.listings.filter(l => l.id !== listing.id);
					window.toast.success(data.message || 'Предмет удален из торговли');
					await this.updateMinMarketPrice(listing.market_hash_name);
				}
			} catch (error) {
				console.error('Delete listing error:', error);
			}
		},

		editPrice(listing) {
			this.itemToEdit = listing;
			this.editPriceValue = listing.price;
			const modal = new bootstrap.Modal(document.getElementById('editPriceModal'));

			// Добавляем обработчик события полного открытия модального окна
			const modalElement = document.getElementById('editPriceModal');
			modalElement.addEventListener('shown.bs.modal', function selectPriceText() {
				const priceInput = document.getElementById('priceInput');
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