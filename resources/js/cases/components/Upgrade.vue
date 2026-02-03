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
						<div class="chance-indicator">
							<svg class="chance-ring" viewBox="0 0 200 200" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<!-- Фоновая обводка -->
								<circle cx="100" cy="100" r="99.6846" stroke="#616161" stroke-width="0.630758" />

								<!-- Внутренний темный круг -->
								<circle cx="100" cy="100" r="89" fill="#464646" />
								<circle cx="100" cy="100" r="89" fill="#040305" />

								<!-- Прогресс дуга -->
								<circle class="chance-ring-progress" cx="100" cy="100" r="94" fill="none"
									stroke="url(#chanceGradient)" stroke-width="12" stroke-linecap="round"
									:stroke-dasharray="circumference" :stroke-dashoffset="progressOffset"
									transform="rotate(-89 100 100)" />

								<!-- Указатель (вращается при анимации) -->
								<g :transform="`rotate(${pointerAngle} 100 100)`">
									<path
										d="M100.5 23L93 0.556008C98.8151 -0.187308 102.114 -0.18336 108 0.556009L100.5 23Z"
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
							<div class="chance d-flex flex-column justify-content-center text-center">
								<span class="dig">{{ formattedChance }} %</span>
								<span class="text">Шанс улучшения</span>
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
				<div class="h-100 chance-slider d-flex flex-column justify-content-between"
					:class="{ disabled: totalBet <= 0 }">
					<div class="chance-labels d-flex justify-content-between align-items-center">
						<span v-for="val in chanceSteps" :key="val" :class="{ active: selectedChance === val }"
							@click="totalBet > 0 && selectChance(val)">{{ val }}%</span>
					</div>
					<div class="chance-track" ref="chanceTrack" @mousedown="totalBet > 0 && onTrackClick($event)">
						<div class="chance-thumb" :style="{ left: chanceTrackWidth + '%' }"
							@mousedown.stop="totalBet > 0 && startDrag($event)"></div>
					</div>
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
						<div class="h-100 case-content-item" :class="getRarityClass(item)">
							<div class="top-box">
								<div class="btn-group" :class="isItemSelected(item.id) ? 'selected' : ''">
									<span class="price" v-html="formatPrice(item.price)"></span>
									<button class="btn btn-quinary btn-select" @click="toggleItem(item)">
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
						<input type="number" class="form-control" placeholder="От"
							v-model.number="targetsPriceFrom" min="0">
					</div>
					<div class="col-2">
						<input type="number" class="form-control" placeholder="До"
							v-model.number="targetsPriceTo" min="0">
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
						<div class="h-100 case-content-item" :class="getRarityClass(target)">
							<div class="top-box">
								<div class="btn-group" :class="isTargetSelected(target.id) ? 'selected' : ''">
									<span class="price" v-html="formatPrice(target.price)"></span>
									<button class="btn btn-quinary btn-select" @click="selectTarget(target)">
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

			// Ползунок шанса
			chanceSteps: [1, 5, 10, 25, 50, 75, 90],
			selectedChance: null,
			isDragging: false,

			// Апгрейд
			upgradeLoading: false,
			upgradeResult: null,

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

	beforeUnmount() {
		document.removeEventListener('mousemove', this.onDrag);
		document.removeEventListener('mouseup', this.stopDrag);
		if (this.animationFrame) {
			cancelAnimationFrame(this.animationFrame);
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

		// Параметры для SVG кольца (r = 94)
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

		chanceTrackWidth() {
			if (!this.selectedChance) return 0;
			const index = this.chanceSteps.indexOf(this.selectedChance);
			if (index === -1) return 0;
			return (index / (this.chanceSteps.length - 1)) * 100;
		},

		// Фильтрованные targets по шансу, поиску и цене
		filteredTargets() {
			if (this.targets.length === 0) {
				return this.targets;
			}

			let result = this.targets;

			// Фильтр по шансу (ползунок)
			if (this.selectedChance) {
				result = result.filter(t => t.chance >= this.selectedChance);
			}

			// Фильтр по поиску
			if (this.targetsSearch) {
				const search = this.targetsSearch.toLowerCase();
				result = result.filter(t => t.name.toLowerCase().includes(search));
			}

			// Фильтр по цене "От"
			if (this.targetsPriceFrom) {
				result = result.filter(t => t.price >= this.targetsPriceFrom);
			}

			// Фильтр по цене "До"
			if (this.targetsPriceTo) {
				result = result.filter(t => t.price <= this.targetsPriceTo);
			}

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
				const response = await axios.get('/api/upgrade/targets', {
					params: { bet_total: this.totalBet }
				});

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
			this.selectedChance = chance;
			this.filterTargetsByChance();
		},

		onChanceChange(value) {
			this.filterTargetsByChance();
		},

		filterTargetsByChance() {
			// Сбрасываем выбор
			this.selectedGetItems = [];
			this.targetItem = null;
		},

		// ==================== DRAG ПОЛЗУНКА ====================
		startDrag(e) {
			this.isDragging = true;
			document.addEventListener('mousemove', this.onDrag);
			document.addEventListener('mouseup', this.stopDrag);
		},

		onDrag(e) {
			if (!this.isDragging) return;
			this.updateChanceFromPosition(e.clientX);
		},

		stopDrag() {
			this.isDragging = false;
			document.removeEventListener('mousemove', this.onDrag);
			document.removeEventListener('mouseup', this.stopDrag);
		},

		onTrackClick(e) {
			this.updateChanceFromPosition(e.clientX);
		},

		updateChanceFromPosition(clientX) {
			const track = this.$refs.chanceTrack;
			if (!track) return;

			const rect = track.getBoundingClientRect();
			const percent = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
			const index = Math.round(percent * (this.chanceSteps.length - 1));
			const newChance = this.chanceSteps[index];

			if (newChance !== this.selectedChance) {
				this.selectChance(newChance);
			}
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

				// Переводим roll_value (0-100) в градусы (0-360°)
				const finalAngle = (roll_value / 100) * 360;

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

		// Анимация указателя - физическая симуляция с динамической пружиной
		runPointerAnimation(finalAngle) {
			return new Promise((resolve) => {
				this.isAnimating = true;

				// Цель - просто finalAngle (без кругов)
				const targetAngle = finalAngle;

				// Физические параметры
				let position = 0;
				let velocity = 30; // начальная скорость
				const friction = 0.994; // меньше трение = медленнее замедляется
				const minSpring = 0.0002; // слабая пружина в начале
				const maxSpring = 0.012; // сильная пружина в конце
				const springDamping = 0.96;
				const minVelocity = 0.1;

				let lastTime = performance.now();
				const startTime = lastTime;

				const animate = (currentTime) => {
					const deltaTime = Math.min((currentTime - lastTime) / 16.67, 3);
					lastTime = currentTime;

					// Время с начала (для расчёта силы пружины)
					const elapsed = (currentTime - startTime) / 1000; // в секундах

					// Применяем трение
					velocity *= Math.pow(friction, deltaTime);

					// Пружина усиливается со временем (слабая → сильная)
					// Медленно в начале, резко усиливается в конце
					const springProgress = Math.min(elapsed / 15, 1);
					const easedProgress = Math.pow(springProgress, 4); // степень 4 - сильнее смещено к концу
					const springStrength = minSpring + (maxSpring - minSpring) * easedProgress;

					// Пружина тянет к цели
					const distanceToTarget = position - targetAngle;
					const springForce = distanceToTarget * springStrength * deltaTime;

					// Дополнительное затухание амплитуды - ступенчатое
					let dampingAmount = 0;
					if (elapsed < 2) {
						// Первые 2 сек - нет затухания
						dampingAmount = 0;
					} else if (elapsed < 4) {
						// 2-4 сек - 20% затухания
						dampingAmount = 0.2;
					} else {
						// После 4 сек - постепенно до 100%
						const progress = Math.min((elapsed - 4) / 10, 1);
						dampingAmount = 0.2 + 0.8 * progress;
					}

					if (dampingAmount > 0) {
						const currentDamping = 1 - (1 - springDamping) * dampingAmount;
						velocity *= Math.pow(currentDamping, deltaTime);
					}

					velocity -= springForce;

					// Двигаем позицию
					position += velocity * deltaTime;

					this.pointerAngle = position;

					// Проверяем остановку - когда движение почти незаметно
					const distToTarget = Math.abs(position - targetAngle);
					if (Math.abs(velocity) < 0.05 && distToTarget < 2) {
						// Останавливаемся там где есть (без прыжка)
						this.isAnimating = false;
						resolve();
						return;
					}

					// Защита от бесконечной анимации (макс 10 сек)
					if (currentTime - lastTime > 10000) {
						this.pointerAngle = targetAngle;
						this.isAnimating = false;
						resolve();
						return;
					}

					this.animationFrame = requestAnimationFrame(animate);
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
				return '/images/item-placeholder.png';
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
