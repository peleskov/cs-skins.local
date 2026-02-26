<template>
	<div class="container py-4">
		<div class="info-block row g-3 mb-4">
			<div class="col-md-4">
				<div class="w-100 h-100 card card-upgrade-skins justify-content-center align-items-center p-0">
					<template v-if="selectedGiveItems.length > 0">
						<div class="w-100 case-content row justify-content-center aling-items-center"
							:class="selectedGiveItems.length > 1 ? 'g-2' : 'flex-grow-1 g-0'">
							<div v-for="(item, index) in selectedGiveItems" :key="item.id"
								:class="getSelectedItemColClass(index)">
								<div class="h-100 case-content-item"
									:class="[getRarityClass(item), selectedGiveItems.length === 1 ? 'single' : '']">
									<div
										class="h-100 position-relative d-flex flex-column justify-content-center align-items-center">
										<div class="w-100 top-box d-flex align-items-center justify-content-between">
											<span class="price" v-html="formatPrice(item.price)"></span>
											<button class="btn-remove" @click="toggleItem(item)"></button>
										</div>
										<div class="w-100 image"
											:style="{ backgroundImage: `url(${getItemImageUrl(item)})` }">
										</div>
										<div class="d-flex flex-column align-items-center">
											<p class="w-75 text-center mb-0">{{ item.name }}</p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</template>
					<template v-else>
						<img class="w-75" src="/images/background/bg_info_block_skins.png" alt="">
					</template>
					<span v-if="selectedGiveItems.length == 0" class="title-upgrade">Вы отдаете</span>
					<span v-else-if="selectedGiveItems.length == 1" class="title-upgrade">Улучшить предмет</span>
				</div>
			</div>
			<div class="col-md-4">
				<div class="w-100 h-100 card card-upgrade-skins indicator">
					<div class="card-body d-flex flex-column align-items-center justify-content-center">
						<div class="chance-indicator" :class="resultStatus ? `result-${resultStatus}` : ''">
							<svg class="chance-ring" viewBox="0 0 200 200" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<!-- Фоновая обводка -->
								<circle cx="100" cy="100" r="99.6846" stroke="#616161" stroke-width="0.630758" />

								<!-- Внутренний темный круг -->
								<circle cx="100" cy="100" r="89" fill="#464646" />
								<circle cx="100" cy="100" r="89" fill="#040305" />

								<!-- Прогресс дуга -->
								<circle class="chance-ring-progress" cx="100" cy="100" r="94" fill="none"
									stroke="url(#chanceGradient)" stroke-width="12"
									:stroke-dasharray="circumference"
									:stroke-dashoffset="progressOffset"
									transform="rotate(-90 100 100)" />

								<!-- Указатель (вращается при анимации) -->
								<g :transform="`rotate(${pointerAngle} 100 100)`">
									<path
										d="M100 11L92.5 33.5C97.5 34.2 102.5 34.2 107.5 33.5L100 11Z"
										fill="#0A8210" />
								</g>

								<defs>
									<linearGradient id="chanceGradient" x1="100" y1="0" x2="100" y2="200"
										gradientUnits="userSpaceOnUse">
										<stop stop-color="#2EF838" />
										<stop offset="1" stop-color="#30AD36" />
									</linearGradient>
								</defs>
							</svg>
							<svg class="win-ring" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path
									d="M189 100C189 149.153 149.153 189 100 189C50.8467 189 11 149.153 11 100C11 50.8467 50.8467 11 100 11C149.153 11 189 50.8467 189 100Z"
									fill="#464646" />
								<path
									d="M189 100C189 149.153 149.153 189 100 189C50.8467 189 11 149.153 11 100C11 50.8467 50.8467 11 100 11C149.153 11 189 50.8467 189 100Z"
									fill="#238829" fill-opacity="0.5" />
								<circle cx="100" cy="100" r="99.6846" stroke="#616161" stroke-width="0.630758" />
								<path
									d="M100.5 36V164M36 99.5H164M163.5 107V92M158.5 104V95M152.5 106V93M146.5 104V95M36.5 92V107M41.4995 95.0664V104.066M47.499 93.147V106.147M53.4985 95.2271V104.227M108 35.5H93M104.87 40.4985H95.8704M106.714 46.4966H93.7141M107 58.5H94M104.558 52.4946H95.5582M105 64.5H96M107 70.5H94M105 76.5H96M107 82.5H94M108 117.5H93M104.87 122.499H95.8704M106.714 128.497H93.7141M107 140.5H94M104.558 134.495H95.5582M105 146.5H96M107 152.5H94M105 158.5H96M107 164.5H94"
									stroke="#474747" />
								<path d="M100.5 164V189M164 99.5H189M100.5 11V36M11 99.5H36" stroke="black" />
							</svg>
							<svg class="lose-ring" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path
									d="M189 100C189 149.153 149.153 189 100 189C50.8467 189 11 149.153 11 100C11 50.8467 50.8467 11 100 11C149.153 11 189 50.8467 189 100Z"
									fill="#464646" />
								<path
									d="M189 100C189 149.153 149.153 189 100 189C50.8467 189 11 149.153 11 100C11 50.8467 50.8467 11 100 11C149.153 11 189 50.8467 189 100Z"
									fill="#770303" fill-opacity="0.5" />
								<circle cx="100" cy="100" r="99.6846" stroke="#616161" stroke-width="0.630758" />
								<path
									d="M100.5 36V164M36 99.5H164M163.5 107V92M158.5 104V95M152.5 106V93M146.5 104V95M36.5 92V107M41.4995 95.0664V104.066M47.499 93.147V106.147M53.4985 95.2271V104.227M108 35.5H93M104.87 40.4985H95.8704M106.714 46.4966H93.7141M107 58.5H94M104.558 52.4946H95.5582M105 64.5H96M107 70.5H94M105 76.5H96M107 82.5H94M108 117.5H93M104.87 122.499H95.8704M106.714 128.497H93.7141M107 140.5H94M104.558 134.495H95.5582M105 146.5H96M107 152.5H94M105 158.5H96M107 164.5H94"
									stroke="#474747" />
								<path d="M100.5 164V189M164 99.5H189M100.5 11V36M11 99.5H36" stroke="black" />
							</svg>
							<div class="chance d-flex flex-column justify-content-center text-center">
								<template v-if="resultStatus === 'win'">
									<span class="dig result-win">Успех</span>
								</template>
								<template v-else-if="resultStatus === 'lose'">
									<span class="dig result-lose">Неудача</span>
								</template>
								<template v-else>
									<span class="dig">{{ formattedChance }} %</span>
									<span class="text">Шанс улучшения</span>
								</template>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="w-100 h-100 card card-upgrade-skins justify-content-center align-items-center p-0">
					<template v-if="selectedGetItems.length > 0">
						<div class="w-100 case-content row justify-content-center aling-items-center flex-grow-1 g-0">
							<div v-for="item in selectedGetItems" :key="item.id" class="col-12">
								<div class="h-100 case-content-item single" :class="getRarityClass(item)">
									<div
										class="h-100 position-relative d-flex flex-column justify-content-center align-items-center">
										<div class="w-100 top-box d-flex align-items-center justify-content-between">
											<span class="price" v-html="formatPrice(item.price)"></span>
											<button class="btn-remove" @click="toggleGetItem(item)"></button>
										</div>
										<div class="w-100 image"
											:style="{ backgroundImage: `url(${getItemImageUrl(item)})` }">
										</div>
										<div class="d-flex flex-column align-items-center">
											<p class="w-75 text-center mb-0">{{ item.name }}</p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</template>
					<template v-else>
						<img class="w-75" src="/images/background/bg_info_block_skins2.png" alt="">
					</template>
					<span v-if="selectedGetItems.length == 0" class="title-upgrade">Вы получаете</span>
					<span v-else class="title-upgrade">Получить предмет</span>
				</div>
			</div>
		</div>
		<div class="info-block row g-3 mb-4">
			<div class="col-md-4">
				<input type="number" class="form-control" placeholder="Добавить сумму" v-model.number="balanceAmount"
					:max="user.balance" min="0">
			</div>
			<div class="col-md-4">
				<button class="w-100 h-100 btn btn-primary btn-upgrade" :disabled="!canUpgrade || upgradeLoading"
					@click="executeUpgrade">
					<span v-if="upgradeLoading" class="spinner-border spinner-border-sm me-2"></span>
					{{ upgradeLoading ? 'Апгрейд...' : 'Апгрейд' }}
				</button>

			</div>
			<div class="col-md-4">
				<div class="h-100 chance-slider d-flex align-items-center" :class="{ disabled: totalBet <= 0 }">
					<div class="chance-buttons d-flex align-items-center gap-2 w-100">
						<button v-for="val in chanceSteps" :key="val" class="chance-btn flex-fill"
							:class="{ active: selectedChance === val }" :disabled="totalBet <= 0"
							@click="selectChance(val)">{{
								val }}%</button>
					</div>
					<div class="chance-info" data-bs-toggle="tooltip" data-bs-placement="top"
						title="Фильтр для предметов апгрейда"></div>
				</div>
			</div>
		</div>
		<div class="row position-relative">
			<!-- Ваши предметы -->
			<div class="col-6">
				<h2 class="category-title upgrade text-center mb-3"><span>Ваши предметы</span></h2>
				<div class="filter-box d-flex align-items-center gap-2 mb-3">
					<div class="col">
						<input type="text" class="form-control flex-grow-1" placeholder="Поиск по скинам..."
							v-model="inventorySearch">
					</div>
					<div class="col-auto">
						<button class="btn-clear" @click="clearInventoryFilter"
							:class="{ active: inventorySearch }"></button>
					</div>
				</div>
				<div class="case-content row g-4">
					<div v-for="item in filteredInventory" :key="item.id" class="col-6">
						<div class="h-100 case-content-item" :class="getRarityClass(item)" @click="toggleItem(item)"
							style="cursor: pointer;">
							<div class="top-box">
								<div class="btn-group" :class="isItemSelected(item.id) ? 'selected' : ''">
									<span class="price" v-html="formatPrice(item.price)"></span>
									<button class="btn btn-quinary btn-select">
										{{ isItemSelected(item.id) ? 'Выбрано' : 'Выбрать' }}
									</button>
								</div>
							</div>
							<div class="image mt-5" :style="{ backgroundImage: `url(${getItemImageUrl(item)})` }"></div>
							<div class="d-flex flex-column align-items-center">
								<p class="w-75 text-center mb-0">{{ item.name }}</p>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="upgrade-divider"></div>
			<!-- Предметы для апгрейда -->
			<div class="col-6">
				<h2 class="category-title upgrade text-center mb-3"><span>Предметы для апгрейда</span></h2>
				<div class="filter-box d-flex align-items-center gap-2 mb-3">
					<div class="col">
						<input type="text" class="form-control" placeholder="Поиск по скинам..."
							v-model="targetsSearch">
					</div>
					<div class="col-2">
						<input type="number" class="form-control" placeholder="От" v-model.number="targetsPriceFrom"
							min="0">
					</div>
					<div class="col-2">
						<input type="number" class="form-control" placeholder="До" v-model.number="targetsPriceTo"
							min="0">
					</div>
					<div class="col-auto">
						<button class="btn-clear" @click="clearTargetsFilter"
							:class="{ active: hasTargetsFilter }"></button>
					</div>
				</div>
				<div v-if="targetsLoading" class="text-center py-4">
					<span class="spinner-border spinner-border-sm me-2"></span>
					Загрузка...
				</div>
				<div v-else-if="targets.length === 0" class="text-center py-4">
					<p>Выберите предметы слева для отображения целей</p>
				</div>
				<div v-else-if="filteredTargets.length === 0" class="text-center py-4">
					<p>Нет предметов с таким шансом</p>
				</div>
				<div v-else class="case-content row g-4">
					<div v-for="target in filteredTargets" :key="target.id" class="col-6">
						<div class="h-100 case-content-item" :class="getRarityClass(target)"
							@click="selectTarget(target)" style="cursor: pointer;">
							<div class="top-box">
								<div class="btn-group" :class="isTargetSelected(target.id) ? 'selected' : ''">
									<span class="price" v-html="formatPrice(target.price)"></span>
									<button class="btn btn-quinary btn-select">
										{{ isTargetSelected(target.id) ? 'Выбрано' : 'Выбрать' }}
									</button>
								</div>
							</div>
							<div class="image mt-5" :style="{ backgroundImage: `url(${getItemImageUrl(target)})` }">
							</div>
							<div class="d-flex flex-column align-items-center">
								<p class="w-75 text-center mb-0">{{ target.name }}</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';
import { formatPrice } from '../../shared/utils/helpers';

const upgradeSounds = {
	success: new Audio('/sounds/upgrade_success.mp3'),
	error: new Audio('/sounds/upgrade_error.mp3'),
};
Object.values(upgradeSounds).forEach(s => s.load());

export default {
	name: 'Upgrade',

	props: {
		inventory: {
			type: Array,
			default: () => []
		},
		user: {
			type: Object,
			required: true
		},
		settings: {
			type: Object,
			required: true
		},
		routes: {
			type: Object,
			required: true
		}
	},

	data() {
		return {
			// Инвентарь и выбранные предметы
			availableItems: [],
			selectedGiveItems: [],
			selectedGetItems: [],
			balanceAmount: null,

			// Фильтры инвентаря
			inventorySearch: '',

			// Целевые предметы
			targets: [],
			targetItem: null,
			targetsLoading: false,

			// Фильтры целей
			targetsSearch: '',
			targetsPriceFrom: null,
			targetsPriceTo: null,

			// Кнопки шанса
			chanceSteps: [1, 5, 10, 25, 50, 75],
			selectedChance: null,

			// Апгрейд
			upgradeLoading: false,
			upgradeResult: null,
			resultStatus: null, // 'win' | 'lose' | null
			resultTimeout: null,

			// Анимация указателя
			pointerAngle: 0,
			isAnimating: false,
			animationFrame: null,


			// Дебаунс для загрузки целей
			loadTargetsTimeout: null,
		};
	},

	created() {
		this.availableItems = [...this.inventory];
	},

	mounted() {
		if (window.bootstrap?.Tooltip) {
			this.$el.querySelectorAll('[data-bs-toggle="tooltip"]')
				.forEach(el => new window.bootstrap.Tooltip(el));
		}
	},

	beforeUnmount() {
		if (this.animationFrame) {
			cancelAnimationFrame(this.animationFrame);
		}
		if (this.resultTimeout) {
			clearTimeout(this.resultTimeout);
		}
		if (window.bootstrap?.Tooltip) {
			this.$el.querySelectorAll('[data-bs-toggle="tooltip"]')
				.forEach(el => {
					const tooltip = window.bootstrap.Tooltip.getInstance(el);
					if (tooltip) tooltip.dispose();
				});
		}
	},

	setup() {
		return { formatPrice };
	},

	computed: {
		// Фильтрованный инвентарь
		filteredInventory() {
			if (!this.inventorySearch) {
				return this.availableItems;
			}
			const search = this.inventorySearch.toLowerCase();
			return this.availableItems.filter(item =>
				item.name.toLowerCase().includes(search)
			);
		},

		// Есть ли активные фильтры для целей
		hasTargetsFilter() {
			return this.targetsSearch || this.targetsPriceFrom || this.targetsPriceTo;
		},

		// Сумма выбранных предметов
		selectedGiveItemsTotal() {
			return this.selectedGiveItems.reduce((sum, item) => sum + item.price, 0);
		},

		// Общая ставка
		totalBet() {
			return this.selectedGiveItemsTotal + (this.balanceAmount || 0);
		},

		// Расчетный шанс
		calculatedChance() {
			if (!this.targetItem || this.totalBet <= 0) return 0;

			const commission = this.settings.commission || 15;
			let chance = (this.totalBet / this.targetItem.price) * (100 - commission);

			// Ограничения
			chance = Math.max(this.settings.min_chance || 1, Math.min(this.settings.max_chance || 70, chance));

			return Math.round(chance * 100) / 100;
		},

		// Можно ли выполнить апгрейд
		canUpgrade() {
			return this.totalBet > 0 &&
				this.targetItem !== null &&
				this.calculatedChance >= (this.settings.min_chance || 1) &&
				!this.upgradeLoading;
		},

		circumference() {
			return 2 * Math.PI * 94;
		},

		progressOffset() {
			const progress = this.calculatedChance / 100;
			return this.circumference * (1 - progress);
		},

		formattedChance() {
			return this.calculatedChance.toFixed(2).replace('.', ',');
		},

		chanceMaxBound() {
			const bounds = { 1: 1.5, 5: 5.5, 10: 12, 25: 27, 50: 55, 75: 100 };
			return bounds[this.selectedChance] || null;
		},

		chanceMinBound() {
			const bounds = { 1: 0, 5: 1.5, 10: 5.5, 25: 12, 50: 27, 75: 55 };
			return bounds[this.selectedChance] ?? null;
		},

		// Фильтрованные targets по шансу (ползунок)
		filteredTargets() {
			if (this.targets.length === 0) {
				return this.targets;
			}

			let result = this.targets;

			// Фильтр по шансу (ползунок) — от 0 до верхней границы
			if (this.selectedChance && this.chanceMaxBound) {
				result = result.filter(t => t.chance <= this.chanceMaxBound);
			}

			// Сортировка от большего шанса к меньшему
			result = [...result].sort((a, b) => b.chance - a.chance);

			return result;
		},
	},

	watch: {
		// Следим за изменением ставки
		totalBet(newVal) {
			if (newVal > 0) {
				this.debouncedLoadTargets();
			} else {
				this.targets = [];
				this.targetItem = null;
			}
		},
		// Сбрасываем стрелку при смене шанса
		calculatedChance() {
			this.pointerAngle = 0;
		},
		// Перезагружаем цели при выборе шанса
		selectedChance() {
			if (this.totalBet > 0) this.debouncedLoadTargets();
		},
		// Перезагружаем цели при изменении фильтров
		targetsSearch() {
			this.selectedChance = null;
			if (this.totalBet > 0) this.debouncedLoadTargets();
		},
		targetsPriceFrom() {
			this.selectedChance = null;
			if (this.totalBet > 0) this.debouncedLoadTargets();
		},
		targetsPriceTo() {
			this.selectedChance = null;
			if (this.totalBet > 0) this.debouncedLoadTargets();
		},
	},

	methods: {
		// ==================== ПРЕДМЕТЫ ====================
		getSelectedItemColClass(index) {
			const count = this.selectedGiveItems.length;
			if (count === 1) return 'col-12';
			if (count === 2) return 'col-6';
			if (count === 3) return index === 2 ? 'col-6' : 'col-6';
			return 'col-6'; // 4 items: 2x2
		},

		toggleItem(item) {
			if (this.upgradeLoading || this.isAnimating) return;
			if (this.isItemSelected(item.id)) {
				this.selectedGiveItems = this.selectedGiveItems.filter(i => i.id !== item.id);
			} else if (this.selectedGiveItems.length < 4) {
				this.selectedGiveItems.push(item);
			}
			// Сбрасываем выбранную цель при изменении ставки
			this.selectedGetItems = [];
			this.targetItem = null;
		},

		toggleGetItem(item) {
			if (this.upgradeLoading || this.isAnimating) return;
			if (this.isGetItemSelected(item.id)) {
				this.selectedGetItems = [];
				this.targetItem = null;
			} else {
				this.selectedGetItems = [item];
				this.targetItem = item;
			}
		},

		isItemSelected(itemId) {
			return this.selectedGiveItems.some(i => i.id === itemId);
		},

		isGetItemSelected(itemId) {
			return this.selectedGetItems.some(i => i.id === itemId);
		},

		// ==================== ЦЕЛИ ====================
		debouncedLoadTargets() {
			if (this.loadTargetsTimeout) {
				clearTimeout(this.loadTargetsTimeout);
			}
			this.loadTargetsTimeout = setTimeout(() => {
				this.loadTargets();
			}, 300);
		},

		async loadTargets() {
			if (this.totalBet <= 0) {
				this.targets = [];
				return;
			}

			this.targetsLoading = true;

			try {
				const params = { bet_total: this.totalBet };
				if (this.targetsSearch) params.search = this.targetsSearch;
				if (this.targetsPriceFrom) params.price_from = this.targetsPriceFrom;
				if (this.targetsPriceTo) params.price_to = this.targetsPriceTo;
				if (this.selectedChance && this.chanceMaxBound) params.chance_max = this.chanceMaxBound;
				if (this.selectedChance && this.chanceMinBound > 0) params.chance_min = this.chanceMinBound;

				const response = await axios.get('/api/upgrade/targets', { params });

				if (response.data.success) {
					this.targets = response.data.targets;

					// Сбрасываем выбранную цель при обновлении списка
					this.selectedGetItems = [];
					this.targetItem = null;
				}
			} catch (error) {
				console.error('Load targets error:', error);
				this.targets = [];
			} finally {
				this.targetsLoading = false;
			}
		},

		selectTarget(target) {
			this.toggleGetItem(target);
		},

		isTargetSelected(targetId) {
			return this.isGetItemSelected(targetId);
		},

		// ==================== ПОЛЗУНОК ШАНСА ====================
		selectChance(chance) {
			if (this.upgradeLoading || this.isAnimating) return;
			this.selectedChance = chance;
			this.filterTargetsByChance();
		},

		filterTargetsByChance() {
			// Сбрасываем выбор
			this.selectedGetItems = [];
			this.targetItem = null;
		},

		// ==================== АПГРЕЙД ====================
		async executeUpgrade() {
			if (!this.canUpgrade || this.isAnimating) return;

			this.upgradeLoading = true;
			this.upgradeResult = null;

			try {
				// Отправляем запрос на сервер
				const response = await axios.post('/api/upgrade/execute', {
					item_ids: this.selectedGiveItems.map(item => item.id),
					balance_amount: this.balanceAmount || 0,
					target_id: this.targetItem.id,
				});

				if (!response.data.success) {
					throw new Error(response.data.message || 'Ошибка апгрейда');
				}

				const { is_win, roll_value, chance, won_item, balance } = response.data;

				// Вычисляем финальный угол стрелки с отступом от краёв дуги
				const finalAngle = this.calcFinalAngle(is_win, roll_value, chance);

				// Звук результата параллельно с анимацией
				const snd = upgradeSounds[is_win ? 'success' : 'error'];
				snd.currentTime = 0;
				snd.play().catch(() => { });

				// Запускаем анимацию
				await this.runPointerAnimation(finalAngle);

				// Сохраняем результат
				this.upgradeResult = {
					isWin: is_win,
					rollValue: roll_value,
					chance: chance,
					wonItem: won_item || null,
				};

				// Обновляем баланс
				this.updateBalance(balance);

				// Показываем результат на 3 секунды
				this.showResult(is_win);

				// Обрабатываем результат
				if (is_win) {
					this.handleWin(won_item);
				} else {
					this.handleLose();
				}

			} catch (error) {
				console.error('Upgrade error:', error);
				alert(error.response?.data?.message || error.message || 'Ошибка при выполнении апгрейда');
			} finally {
				this.upgradeLoading = false;
			}
		},

		/**
		 * Обработка выигрыша
		 */
		handleWin(wonItem) {
			// Удаляем использованные предметы из инвентаря
			const usedIds = this.selectedGiveItems.map(item => item.id);
			this.availableItems = this.availableItems.filter(item => !usedIds.includes(item.id));

			// Добавляем выигранный предмет в инвентарь
			if (wonItem) {
				this.availableItems.unshift(wonItem);
			}

			// Сбрасываем выбор
			this.resetSelection();
		},

		/**
		 * Обработка проигрыша
		 */
		handleLose() {
			// Удаляем использованные предметы из инвентаря
			const usedIds = this.selectedGiveItems.map(item => item.id);
			this.availableItems = this.availableItems.filter(item => !usedIds.includes(item.id));

			// Сбрасываем выбор
			this.resetSelection();
		},

		showResult(isWin) {
			if (this.resultTimeout) clearTimeout(this.resultTimeout);
			this.resultStatus = isWin ? 'win' : 'lose';
			this.resultTimeout = setTimeout(() => {
				this.resultStatus = null;
			}, 3000);
		},

		/**
		 * Сброс выбора после апгрейда
		 */
		resetSelection() {
			this.selectedGiveItems = [];
			this.selectedGetItems = [];
			this.targetItem = null;
			this.balanceAmount = null;
			this.targets = [];
			this.pointerAngle = 0;
		},

		/**
		 * Обновление баланса в header
		 */
		updateBalance(newBalance) {
			// Обновляем локальное значение
			this.user.balance = newBalance;

			// Отправляем событие для обновления в header
			window.dispatchEvent(new CustomEvent('balance-updated', {
				detail: { main: newBalance }
			}));
		},

		// Вычисляет угол стрелки с отступом ±1% от краёв дуги
		calcFinalAngle(isWin, rollValue, chance) {
			const arcAngle = (chance / 100) * 360;
			const margin = 3.6; // 1% от 360°
			let angle = (rollValue / 100) * 360;

			if (isWin) {
				// Внутри дуги (0 .. arcAngle)
				if (arcAngle < margin * 2) {
					// Дуга слишком короткая — стрелка по центру
					angle = arcAngle / 2;
				} else {
					angle = Math.max(margin, Math.min(arcAngle - margin, angle));
				}
			} else {
				// Снаружи дуги (arcAngle .. 360)
				const outsideAngle = 360 - arcAngle;
				if (outsideAngle < margin * 2) {
					angle = arcAngle + outsideAngle / 2;
				} else {
					angle = Math.max(arcAngle + margin, Math.min(360 - margin, angle));
				}
			}

			return angle;
		},

		// Анимация указателя - вращение только по часовой стрелке с плавным замедлением
		runPointerAnimation(finalAngle) {
			return new Promise((resolve) => {
				this.isAnimating = true;

				// Добавляем несколько полных оборотов для эффекта рулетки
				const extraRotations = 3; // количество полных оборотов
				const targetAngle = extraRotations * 360 + finalAngle;

				const duration = 4000; // длительность анимации в мс
				const startTime = performance.now();
				const startAngle = this.pointerAngle;

				// Easing функция - плавное замедление в конце
				const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

				const animate = (currentTime) => {
					const elapsed = currentTime - startTime;
					const progress = Math.min(elapsed / duration, 1);

					// Применяем easing для плавного замедления
					const easedProgress = easeOutCubic(progress);

					// Вычисляем текущий угол
					this.pointerAngle = startAngle + (targetAngle - startAngle) * easedProgress;

					if (progress < 1) {
						this.animationFrame = requestAnimationFrame(animate);
					} else {
						// Анимация завершена - устанавливаем финальный угол
						this.pointerAngle = finalAngle; // нормализуем до 0-360
						this.isAnimating = false;
						resolve();
					}
				};

				this.animationFrame = requestAnimationFrame(animate);
			});
		},

		// ==================== ФИЛЬТРЫ ====================
		clearInventoryFilter() {
			this.inventorySearch = '';
		},

		clearTargetsFilter() {
			this.targetsSearch = '';
			this.targetsPriceFrom = null;
			this.targetsPriceTo = null;
		},

		// ==================== HELPERS ====================
		getItemImageUrl(item) {
			if (!item || !item.image_url) {
				return '/images/logo_ico.svg';
			}

			if (item.image_url.startsWith('http://') || item.image_url.startsWith('https://')) {
				return item.image_url;
			}

			return `https://community.steamstatic.com/economy/image/${item.image_url}`;
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
	}
}
</script>
