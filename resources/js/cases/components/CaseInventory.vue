<template>
	<div class="container py-4">
		<div class="info-block row g-3 mb-4">
			<!-- Блок профиля -->
			<div class="col-md-4">
				<div class="w-100 h-100 card card-info-block">
					<div class="card-header d-flex align-items-center gap-2">
						<div class="avatar position-relative"
							:style="user.avatar_border_color ? { 'border-color': user.avatar_border_color } : {}">
							<img class="img-fluid" :src="user.avatar" alt="profile">
							<!--
							<span v-if="user.is_premium" class="badge-premium">VIP</span>
							-->
						</div>
						<div>
							<p class="mb-2" :style="user.nickname_color ? { color: user.nickname_color } : {}">{{
								user.name }}</p>
							<span>ID #{{ user.id }}</span>
						</div>
					</div>
					<div class="card-body d-flex">
						<div class="balance d-flex gap-2 align-items-center">
							<div class="ico bonus"></div>
							<div>
								<span class="mb-1">Бонусный</span>
								<p class="mb-0" v-html="formatPrice(localUser.bonus_balance)"></p>
							</div>
						</div>
						<div class="dev mx-3"></div>
						<div class="balance d-flex gap-2 align-items-center">
							<div class="ico ruble"></div>
							<div>
								<span class="mb-1">Баланс</span>
								<p class="mb-0" v-html="formatPrice(localUser.balance)"></p>
							</div>
						</div>
					</div>
					<div class="card-footer d-flex gap-2">
						<div class="col-6">
							<a :href="routes.profile + '#balance'" class="w-100 btn btn-senary gap-2 px-2">
								<i class="ico add-balance"></i> Пополнить
							</a>
						</div>
						<div class="col-6">
							<a :href="routes.profile + '#profile'" class="w-100 btn btn-septenary gap-2 px-2">
								<i class="ico trade-link"></i> Трейд ссылка
							</a>
						</div>
					</div>
				</div>
			</div>

			<!-- Любимый кейс -->
			<div class="col-md-4">
				<template v-if="favoriteCase">
					<a :href="`${routes.cases}/${favoriteCase.slug}`"
						class="w-100 h-100 card card-info-block best-case text-decoration-none">
						<div class="card-body d-flex flex-column align-items-center justify-content-center">
							<img :src="getCaseImageUrl(favoriteCase)" :alt="favoriteCase.name"
								class="favorite-case-img mb-1">
							<h6 class="mb-0 text-center">{{ favoriteCase.name }}</h6>
						</div>
						<div class="card-footer">
							<h6 class="mb-0">Любимый кейс</h6>
						</div>
					</a>
				</template>
				<template v-else>
					<div class="w-100 h-100 card card-info-block best-case empty">
						<div class="card-body d-flex flex-grow-1 flex-column align-items-center justify-content-center">
							<img class="favorite-case-img" src="/images/background/bg_favorite_case_empty.png"
								alt="favorite-case">
						</div>
						<div class="card-footer d-flex justify-content-between align-items-center">
							<h6 class="mb-0">Любимый кейс</h6>
							<a :href="routes.cases" class="btn btn-primary btn-lower">Открыть</a>
						</div>
					</div>
				</template>
			</div>

			<!-- Лучший предмет -->
			<div class="col-md-4">
				<template v-if="bestItem">
					<a :href="`${routes.cases}/${favoriteCase.slug}`"
						class="w-100 h-100 card card-info-block best-item text-decoration-none"
						:class="getBestItemRarityClass()">
						<div class="card-body d-flex flex-column align-items-center justify-content-center"
							:class="getBestItemRarityClass()">
							<div class="price" v-html="formatPrice(bestItem.price)"></div>
							<img :src="getItemImageUrl(bestItem)" :alt="bestItem.name" class="best-item-img">
							<h6 class="mb-0 text-center">{{ bestItem.name }}</h6>
						</div>
						<div class="card-footer">
							<h6 class="mb-0">Лучший предмет</h6>
						</div>
					</a>
				</template>
				<template v-else>
					<div class="w-100 h-100 card card-info-block best-item empty">
						<div class="card-body d-flex flex-grow-1 flex-column align-items-center justify-content-center">
							<img class="best-item-img" src="/images/background/bg_favorite_item_empty.png"
								alt="favorite-item">
						</div>
						<div class="card-footer d-flex justify-content-between align-items-center">
							<h6 class="mb-0">Лучший предмет</h6>
							<a :href="routes.cases" class="btn btn-primary btn-lower">Открыть</a>
						</div>
					</div>
				</template>
			</div>
		</div>
		<div class="nav-block d-flex align-items-center justify-content-between mb-4">
			<div class="col">
				<ul class="nav nav-tabs" id="inventoryTabs" role="tablist">
					<li class="nav-item" role="presentation">
						<button class="nav-link active d-flex justify-content-center align-items-center gap-2"
							id="items-tab" data-bs-toggle="tab" data-bs-target="#items-pane" type="button" role="tab">
							Предметы <span class="badge ms-1">{{ localItems.length }}</span>
						</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link d-flex justify-content-center align-items-center gap-2"
							id="upgrades-tab" data-bs-toggle="tab" data-bs-target="#upgrades-pane" type="button"
							role="tab">
							Апгрейды <span class="badge ms-1">{{ upgradeHistory.length }}</span>
						</button>
					</li>
				</ul>
			</div>
			<div class="col-auto d-flex align-items-center gap-3">
				<div class="form-check form-switch d-flex align-items-center gap-2 flex-row-reverse">
					<input class="form-check-input m-0" type="checkbox" role="switch" id="availableForSale"
						v-model="showOnlyAvailable">
					<label class="form-check-label" for="availableForSale">Доступно для продажи</label>
				</div>
				<button class="btn btn-primary" @click="confirmSellAll" :disabled="availableCount === 0">
					Продать все
				</button>
			</div>
		</div>

		<div class="tab-content" id="inventoryTabContent">
			<!-- Таб Предметы -->
			<div class="tab-pane fade show active" id="items-pane" role="tabpanel" tabindex="0">
				<div v-if="filteredItems.length === 0"
					class="case-inventory-empty d-flex justify-content-between align-items-center mt-5">
					<img src="/images/background/bg_empy_case_inv.png" alt="">
					<div>
						<h3 class="text-white mb-2">Нет предметов</h3>
						<p class="text-white mb-0">Начните открывать кейсы</p>
					</div>
					<a :href="routes.cases" class="btn btn-primary">Перейти к кейсам</a>
				</div>
				<div v-else class="case-content row g-4">
					<div v-for="item in filteredItems" :key="item.id" class="col-inventory">
						<div class="h-100 case-content-item" :class="getItemClass(item)">
							<div class="top-box d-flex flex-column">
								<template v-if="item.status === 'available'">
									<div class="btn-group mb-2">
										<button class="btn btn-quinary align-items-center gap-1"
											@click="sellItem(item.id)" :disabled="isSelling(item.id)">
											<i class="ico sale"></i>Продать
										</button>
										<button class="btn btn-quinary align-items-center gap-1"
											@click="withdrawItem(item.id)">
											<i class="ico withdraw"></i>Вывести
										</button>
									</div>
									<div class="d-flex align-items-center justify-content-between">
										<span class="price align-self-start" v-html="formatPrice(item.price)"></span>
										<span v-if="item.is_anti_unluck" class="badge-anti-unluck">Анти-анлак</span>
									</div>
								</template>
								<template v-else>
									<div class="badge" :class="item.status">
										{{ getStatusLabel(item.status) }}
									</div>
								</template>
							</div>
							<div class="image mt-5" :style="{ backgroundImage: `url(${getItemImageUrl(item)})` }"></div>
							<div class="d-flex flex-column align-items-center">
								<p class="w-75 text-center mb-0">{{ item.name }}</p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Таб Апгрейды -->
			<div class="tab-pane fade" id="upgrades-pane" role="tabpanel" tabindex="0">
				<!-- Загрузка -->
				<div v-if="upgradeHistoryLoading" class="text-center py-5">
					<span class="spinner-border spinner-border-sm me-2"></span>
					Загрузка...
				</div>
				<!-- Пустой список -->
				<div v-else-if="upgradeHistory.length === 0" class="text-center py-5">
					<svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
						class="mb-3 text-muted">
						<path
							d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"
							stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
					<p class="text-white mb-4">У Вас не найдено ни одного апгрейда</p>
					<a :href="routes.upgrade" class="btn btn-primary">Перейти к апгрейду</a>
				</div>
				<!-- Список апгрейдов -->
				<div v-else class="row g-3">
					<div v-for="upgrade in upgradeHistory" :key="upgrade.id" class="col-4">
						<div class="h-100 upgrade-box d-flex flex-column">
							<h4 class="text-center">ШАНС</h4>
							<h5 class="text-center">{{ upgrade.chance.toFixed(2) }}%</h5>
							<div class="flex-grow-1 row g-4">
								<div class="col-6 d-flex flex-column">
									<h5 class="text-center result-title">СТАВКА</h5>
									<!-- Предметы ставки (до 4 шт) -->
									<template v-if="upgrade.bet_items.length > 0">
										<div class="row g-2 flex-grow-1 justify-content-center  align-items-center position-relative"
											:class="{ 'bet-items-grid': upgrade.bet_items.length > 1 }">
											<div v-for="(betItem, idx) in upgrade.bet_items" :key="idx"
												:class="upgrade.bet_items.length === 1 ? 'col-12 align-self-stretch' : 'col-6'">
												<div class="h-100 case-content-item"
													:class="[getUpgradeRarityClass(betItem), { 'compact': upgrade.bet_items.length > 1 }]">
													<!-- Цена и название только если 1 предмет -->
													<template v-if="upgrade.bet_items.length === 1">
														<div class="top-box d-flex flex-column">
															<span class="price align-self-start"
																v-html="formatPrice(betItem.price)"></span>
														</div>
													</template>
													<div class="image"
														:class="{ 'mt-5': upgrade.bet_items.length === 1, 'mt-2': upgrade.bet_items.length > 1 }"
														:style="{ backgroundImage: `url(${getUpgradeItemImageUrl(betItem)})` }">
													</div>
													<div v-if="upgrade.bet_items.length === 1"
														class="d-flex flex-column align-items-center">
														<p class="w-75 text-center mb-0">{{ betItem.name }}</p>
													</div>
												</div>
											</div>
											<div class="bet-arrow"></div>
										</div>
									</template>
									<template v-else>
										<div class="case-content-item flex-grow-1 position-relative">
											<div class="top-box d-flex flex-column">
												<span class="price align-self-start"
													v-html="formatPrice(upgrade.bet_balance)"></span>
											</div>
											<div
												class="d-flex flex-column align-items-center justify-content-center h-100">
												<p class="text-center mb-0">Баланс</p>
											</div>
											<div class="bet-arrow"></div>
										</div>
									</template>
								</div>
								<div class="col-6 d-flex flex-column">
									<h5 class="text-center result-title" :class="upgrade.is_win ? 'win' : 'lose'">{{
										upgrade.is_win ?
											'ВЫИГРЫШ' :
											'ПРОИГРЫШ' }}</h5>
									<div class="case-content-item flex-grow-1"
										:class="getUpgradeRarityClass(upgrade.target)">
										<div class="top-box d-flex flex-column">
											<span class="price align-self-start"
												v-html="formatPrice(upgrade.target.price)"></span>
										</div>
										<div class="image mt-5"
											:style="{ backgroundImage: `url(${getUpgradeItemImageUrl(upgrade.target)})` }">
										</div>
										<div class="d-flex flex-column align-items-center">
											<p class="w-75 text-center mb-0">{{ upgrade.target.name }}</p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Trade URL Modal -->
		<div class="modal theme-cases-modal fade" id="tradeUrlModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<form @submit.prevent="saveTradeUrl">
						<div class="modal-header justify-content-center">
							<h1 class="modal-title">Добавить Trade URL</h1>
							<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="tradeUrlInput" class="form-label">Steam Trade URL</label>
								<input type="url" class="form-control mb-2" id="tradeUrlInput" v-model="tradeUrlInput"
									placeholder="https://steamcommunity.com/tradeoffer/new/?partner=123456&token=abcdef"
									required>
								<small class="text-muted">
									Найдите Trade URL в своем Steam: Мой профиль → Инвентарь → Предложения обмена → <a
										:href="`https://steamcommunity.com/profiles/${user.steam_id}/tradeoffers/privacy`"
										target="_blank">Кто может отправлять мне предложения обмена?</a>
								</small>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-primary mt-0" data-bs-dismiss="modal">Отмена</button>
							<button type="submit" class="btn btn-secondary mt-0" :disabled="tradeUrlSaving">
								<span v-if="tradeUrlSaving">
									<span class="spinner-border spinner-border-sm me-1" role="status"></span>
									Сохраняем...
								</span>
								<span v-else>Сохранить</span>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- Sell All Confirm Modal -->
		<div class="modal theme-cases-modal fade" id="sellAllModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header justify-content-center">
						<h1 class="modal-title">Продать все предметы</h1>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body text-center">
						<p>Вы уверены, что хотите продать все доступные предметы?</p>
						<p class="mb-0">
							<strong>{{ availableCount }}</strong> предметов на сумму
							<strong v-html="formatPrice(availableTotal)"></strong>
						</p>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-primary mt-0" data-bs-dismiss="modal">Отмена</button>
						<button type="button" class="btn btn-secondary mt-0" @click="sellAllItems"
							:disabled="sellingIds.length > 0">
							<span v-if="sellingIds.length > 0">
								<span class="spinner-border spinner-border-sm me-1" role="status"></span>
								Продаём...
							</span>
							<span v-else>Продать все</span>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Replacement Modal -->
		<div class="modal theme-cases-modal fade" id="replacementModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered modal-xl">
				<div class="modal-content">
					<div class="modal-header d-block">
						<h1 class="modal-title text-center text-uppercase mb-4">Предмета нет в наличии</h1>
						<h1 class="modal-title text-center text-white">Пожалуйста, выберите ему замену:</h1>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<!-- Список замен -->
						<div v-if="replacementsLoading" class="text-center py-4">
							<span class="spinner-border spinner-border-sm me-2"></span>
							Загрузка...
						</div>
						<div v-else-if="replacements.length === 0" class="text-center py-4">
							<p class="mb-3">Нет доступных предметов для вывода, попробуйте позже или вы можете продать
								предмет
							</p>
						</div>
						<div v-else class="container-fluid">
							<div class="d-flex justify-content-start mb-3">
								<button class="price-sort" :class="sortPriceAsc ? 'asc' : 'desc'"
									@click="sortPriceAsc = !sortPriceAsc">
									Цена
								</button>
							</div>
							<div class="row g-4">
								<div v-for="listing in sortedReplacements" :key="listing.id" class="col-3">
									<div class="h-100 case-content-item" :class="getListingRarityClass(listing)">
										<div class="top-box">
											<div class="btn-group"
												:class="selectedReplacementId === listing.id ? 'selected' : ''">
												<span class="price" v-html="formatPrice(listing.price)"></span>
												<button class="btn btn-quinary btn-select"
													@click="selectedReplacementId = listing.id">
													{{ selectedReplacementId === listing.id ? 'Выбрано' : 'Выбрать' }}
												</button>
											</div>
										</div>
										<div class="image mt-5"
											:style="{ backgroundImage: `url(${listing.image_url})` }"></div>
										<div class="d-flex flex-column align-items-center">
											<p class="w-75 text-center mb-0">{{ listing.name }}</p>
											<small class="text-muted" v-if="listing.wear_name">{{
												listing.wear_name.toUpperCase() }}</small>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer" v-if="replacements.length > 0">
						<button type="button" class="btn btn-secondary" @click="confirmReplacement"
							:disabled="!selectedReplacementId || withdrawLoading">
							<span v-if="withdrawLoading">
								<span class="spinner-border spinner-border-sm me-1"></span>
								Выводим...
							</span>
							<span v-else>Подтвердить</span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';
import { formatPrice } from '../../shared/utils/helpers';

export default {
	name: 'CaseInventory',

	props: {
		items: {
			type: Array,
			default: () => []
		},
		user: {
			type: Object,
			required: true
		},
		favoriteCase: {
			type: Object,
			default: null
		},
		bestItem: {
			type: Object,
			default: null
		},
		routes: {
			type: Object,
			required: true
		}
	},

	data() {
		return {
			localItems: [],
			localUser: null,
			sellingIds: [],
			showOnlyAvailable: false,
			// Trade URL
			tradeUrlInput: '',
			tradeUrlSaving: false,
			// Withdraw
			withdrawingItemId: null,
			withdrawLoading: false,
			replacements: [],
			replacementsLoading: false,
			priceRange: null,
			originalItem: null,
			replacementSearch: '',
			selectedReplacementId: null,
			sortPriceAsc: false,
			// Upgrade history
			upgradeHistory: [],
			upgradeHistoryLoading: false,
		};
	},

	created() {
		this.localItems = [...this.items];
		this.localUser = { ...this.user };
		this.loadUpgradeHistory();
	},

	mounted() {
		// Восстанавливаем активную вкладку из URL hash
		this.restoreActiveTab();

		// Слушаем смену вкладок для обновления hash
		const tabEls = this.$el.querySelectorAll('button[data-bs-toggle="tab"]');
		tabEls.forEach(tabEl => {
			tabEl.addEventListener('shown.bs.tab', (event) => {
				const targetId = event.target.getAttribute('data-bs-target');
				if (targetId) {
					window.location.hash = targetId.replace('#', '');
				}
			});
		});
	},

	setup() {
		return { formatPrice };
	},

	computed: {
		availableItems() {
			return this.localItems.filter(item => item.status === 'available');
		},
		availableCount() {
			return this.availableItems.length;
		},
		availableTotal() {
			return this.availableItems.reduce((sum, item) => sum + item.price, 0);
		},
		filteredItems() {
			if (this.showOnlyAvailable) {
				return this.availableItems;
			}
			return this.localItems;
		},
		sortedReplacements() {
			if (!this.replacements || this.replacements.length === 0) return [];
			return [...this.replacements].sort((a, b) => {
				return this.sortPriceAsc ? a.price - b.price : b.price - a.price;
			});
		},
	},

	methods: {
		// ==================== TABS ====================
		restoreActiveTab() {
			const hash = window.location.hash.replace('#', '');
			if (hash) {
				const tabButton = this.$el.querySelector(`button[data-bs-target="#${hash}"]`);
				if (tabButton && window.bootstrap?.Tab) {
					const tab = new window.bootstrap.Tab(tabButton);
					tab.show();
				}
			}
		},

		// ==================== UPGRADE HISTORY ====================
		async loadUpgradeHistory() {
			this.upgradeHistoryLoading = true;
			try {
				const response = await axios.get('/api/upgrade/history');
				if (response.data.success) {
					this.upgradeHistory = response.data.history;
				}
			} catch (error) {
				console.error('Load upgrade history error:', error);
			} finally {
				this.upgradeHistoryLoading = false;
			}
		},

		getUpgradeItemImageUrl(item) {
			if (!item || !item.image_url) return '/images/logo_ico.svg';
			if (item.image_url.startsWith('http')) return item.image_url;
			return `https://community.steamstatic.com/economy/image/${item.image_url}`;
		},

		getUpgradeRarityClass(item) {
			if (!item || !item.rarity) return '';
			const rarityMap = {
				'Consumer Grade': 'consumer',
				'Industrial Grade': 'industrial',
				'Mil-Spec Grade': 'milspec',
				'Mil-Spec': 'milspec',
				'Restricted': 'restricted',
				'Classified': 'classified',
				'Covert': 'covert',
				'Contraband': 'contraband',
			};
			const key = rarityMap[item.rarity] || 'common';
			return `rarity-${key}`;
		},

		// ==================== SELL METHODS ====================
		async sellItem(itemId) {
			if (this.sellingIds.includes(itemId)) return;

			this.sellingIds.push(itemId);

			try {
				const response = await axios.post('/api/case-inventory/sell', {
					item_ids: [itemId]
				});

				if (response.data.success) {
					// Обновляем статус предмета
					response.data.sold_ids.forEach(id => {
						const item = this.localItems.find(i => i.id === id);
						if (item) {
							item.status = 'sold';
						}
					});

					// Обновляем баланс
					this.localUser.balance = response.data.balance;
					this.updateHeaderBalance(response.data.balance);
				}
			} catch (error) {
				console.error('Ошибка продажи:', error);
			} finally {
				this.sellingIds = this.sellingIds.filter(id => id !== itemId);
			}
		},

		async sellAllItems() {
			if (this.availableCount === 0) return;
			if (this.sellingIds.length > 0) return;

			// Блокируем все available предметы
			const idsToSell = this.availableItems.map(i => i.id);
			this.sellingIds = [...idsToSell];

			try {
				const response = await axios.post('/api/case-inventory/sell', {
					all: true
				});

				if (response.data.success) {
					// Обновляем статусы
					response.data.sold_ids.forEach(id => {
						const item = this.localItems.find(i => i.id === id);
						if (item) {
							item.status = 'sold';
						}
					});

					// Обновляем баланс
					this.localUser.balance = response.data.balance;
					this.updateHeaderBalance(response.data.balance);

					// Закрываем модалку
					const modal = bootstrap.Modal.getInstance(document.getElementById('sellAllModal'));
					if (modal) modal.hide();
				}
			} catch (error) {
				console.error('Ошибка продажи:', error);
			} finally {
				this.sellingIds = [];
			}
		},

		confirmSellAll() {
			if (this.availableCount === 0) return;

			const modal = new bootstrap.Modal(document.getElementById('sellAllModal'));
			modal.show();
		},

		updateHeaderBalance(newBalance) {
			window.dispatchEvent(new CustomEvent('balance-updated', {
				detail: { main: newBalance }
			}));
		},

		isSelling(itemId) {
			return this.sellingIds.includes(itemId);
		},

		// ==================== WITHDRAW METHODS ====================
		async withdrawItem(itemId) {
			if (!this.localUser.trade_url) {
				const modal = new bootstrap.Modal(document.getElementById('tradeUrlModal'));
				modal.show();
				return;
			}

			this.withdrawingItemId = itemId;
			this.withdrawLoading = true;

			try {
				// Пробуем вывести напрямую
				const response = await axios.post(`/api/case-inventory/${itemId}/withdraw`);

				if (response.data.success) {
					// Успешный вывод - обновляем список с сервера
					await this.refreshItems();
				} else if (response.data.need_replacement) {
					// Нужна замена - показываем модалку
					await this.loadReplacements(itemId);
				}
			} catch (error) {
				console.error('Withdraw error:', error);
			} finally {
				this.withdrawLoading = false;
			}
		},

		async loadReplacements(itemId) {
			// Сбрасываем данные и открываем модалку сразу
			this.replacements = [];
			this.originalItem = null;
			this.priceRange = null;
			this.replacementSearch = '';
			this.selectedReplacementId = null;
			this.replacementsLoading = true;

			const modal = new bootstrap.Modal(document.getElementById('replacementModal'));
			modal.show();

			// Загружаем данные
			try {
				const response = await axios.get(`/api/case-inventory/${itemId}/replacements`);

				if (response.data.success) {
					this.originalItem = response.data.original_item;
					this.priceRange = response.data.price_range;
					this.replacements = response.data.replacements;
				}
			} catch (error) {
				console.error('Load replacements error:', error);
			} finally {
				this.replacementsLoading = false;
			}
		},

		async confirmReplacement() {
			if (!this.selectedReplacementId || !this.withdrawingItemId) return;
			await this.selectReplacement(this.selectedReplacementId);
		},

		async searchReplacements() {
			if (!this.withdrawingItemId) return;

			this.replacementsLoading = true;

			try {
				const response = await axios.get(`/api/case-inventory/${this.withdrawingItemId}/replacements`, {
					params: { search: this.replacementSearch }
				});

				if (response.data.success) {
					this.replacements = response.data.replacements;
				}
			} catch (error) {
				console.error('Search error:', error);
			} finally {
				this.replacementsLoading = false;
			}
		},

		async selectReplacement(listingId) {
			if (!this.withdrawingItemId) return;

			this.withdrawLoading = true;
			const itemId = this.withdrawingItemId;

			try {
				const response = await axios.post(`/api/case-inventory/${itemId}/withdraw`, {
					listing_id: listingId
				});

				if (response.data.success) {
					// Сразу обновляем статус локально
					const item = this.localItems.find(i => i.id === itemId);
					if (item) {
						item.status = 'pending_withdrawal';
					}

					const modal = bootstrap.Modal.getInstance(document.getElementById('replacementModal'));
					if (modal) modal.hide();

					// Синхронизируем с сервером
					await this.refreshItems();
				}
			} catch (error) {
				console.error('Select replacement error:', error);
			} finally {
				this.withdrawLoading = false;
				this.withdrawingItemId = null;
			}
		},

		async refreshItems() {
			try {
				const response = await axios.get('/api/case-inventory');
				if (response.data.success) {
					this.localItems = response.data.data;
				}
			} catch (error) {
				console.error('Refresh items error:', error);
			}
		},

		sellFromReplacementModal() {
			const modal = bootstrap.Modal.getInstance(document.getElementById('replacementModal'));
			if (modal) modal.hide();

			if (this.withdrawingItemId) {
				this.sellItem(this.withdrawingItemId);
			}
		},

		async saveTradeUrl() {
			if (!this.tradeUrlInput || this.tradeUrlSaving) return;

			this.tradeUrlSaving = true;

			try {
				const response = await axios.post('/profile/update-trade-url', {
					trade_url: this.tradeUrlInput
				});

				if (response.data.success) {
					this.localUser.trade_url = this.tradeUrlInput;
					this.tradeUrlInput = '';

					const modal = bootstrap.Modal.getInstance(document.getElementById('tradeUrlModal'));
					if (modal) modal.hide();
				}
			} catch (error) {
				console.error('Trade URL error:', error);
			} finally {
				this.tradeUrlSaving = false;
			}
		},

		getItemClass(item) {
			const classes = [this.getRarityClass(item)];
			if (item.status !== 'available') {
				classes.push('not_available');
			}
			return classes;
		},

		getItemImageUrl(item) {
			if (!item || !item.image_url) {
				return '/images/logo_ico.svg';
			}

			if (item.image_url.startsWith('http://') || item.image_url.startsWith('https://')) {
				return item.image_url;
			}

			return `https://community.steamstatic.com/economy/image/${item.image_url}`;
		},

		getCaseImageUrl(caseItem) {
			if (!caseItem || !caseItem.image_url) {
				return '/images/case-placeholder.png';
			}

			if (caseItem.image_url.startsWith('http://') || caseItem.image_url.startsWith('https://')) {
				return caseItem.image_url;
			}

			return `/storage/${caseItem.image_url}`;
		},

		getRarityClass(item) {
			if (!item || !item.rarity) return '';

			const rarityMap = {
				'Consumer Grade': 'consumer',
				'Industrial Grade': 'industrial',
				'Mil-Spec Grade': 'milspec',
				'Mil-Spec': 'milspec',
				'Restricted': 'restricted',
				'Classified': 'classified',
				'Covert': 'covert',
				'Contraband': 'contraband',
				'Extraordinary': 'extraordinary',
				'Exotic': 'exotic',
				'Remarkable': 'remarkable',
				'High Grade': 'highgrade',
				'Base Grade': 'basegrade',
			};

			const key = rarityMap[item.rarity] || 'common';
			return `rarity-${key}`;
		},

		getBestItemRarityClass() {
			if (!this.bestItem || !this.bestItem.rarity) return '';
			return this.getRarityClass(this.bestItem);
		},

		getStatusLabel(status) {
			const labels = {
				'available': 'Доступен',
				'pending_withdrawal': 'Ожидается вывод',
				'sold': 'Продан',
				'withdrawn': 'Выведен',
				'upgraded': 'Использован в апгрейде'
			};
			return labels[status] || status;
		},

		getStatusBadgeClass(status) {
			const classes = {
				'available': 'bg-success',
				'pending_withdrawal': 'bg-lime',
				'sold': 'bg-secondary',
				'withdrawn': 'bg-info',
				'upgraded': 'bg-warning'
			};
			return classes[status] || 'bg-secondary';
		},

		getListingRarityClass(listing) {
			if (!listing || !listing.rarity) return '';
			return `rarity-${listing.rarity}`;
		}
	}
}
</script>

<style scoped>
.empty-inventory {
	padding: 60px 20px;
}

.empty-inventory svg {
	opacity: 0.5;
}

.item-status {
	text-align: center;
}

.item-status .badge {
	font-size: 0.7rem;
	padding: 4px 8px;
}
</style>
