<template>
	<section class="mb-5">
		<div class="container">
			<div class="row justify-content-between align-items-center mb-3">
				<div class="col-auto">
					<a :href="routes.cases" class="text-uppercase link-tocases d-flex align-items-center gap-3">{{
						caseData.name }}</a>
				</div>
				<div class="col-auto" v-if="freeInfo && freeInfo.available">
					<p class="small text-white mb-0">Доступно
						{{ freeInfo.opens_remaining }} {{ pluralize(freeInfo.opens_remaining, 'открытие', 'открытия',
							'открытий') }} {{ freeCountdown }}</p>
				</div>
				<div class="col-auto" v-if="isLimitedCase">
					<p class="small text-white mb-0" v-if="limitedRemainingOpens !== null && !limitedTimeExpired">
						Осталось {{ limitedRemainingOpens }} / {{ caseData.total_opens_limit }} {{
							pluralize(limitedRemainingOpens, 'открытие', 'открытия', 'открытий') }}
					</p>
					<p class="small text-white mb-0" v-if="caseData.available_until">
						{{ limitedCountdown }}
					</p>
				</div>
			</div>
			<div class="case-box mb-5 position-relative">
				<button type="button" class="case-sound-toggle" @click="toggleSound"
					:title="soundMuted ? 'Включить звук' : 'Выключить звук'"
					:aria-label="soundMuted ? 'Включить звук' : 'Выключить звук'">
					<i :class="soundMuted ? 'ri-volume-mute-line' : 'ri-volume-up-line'"></i>
				</button>
				<div class="case-box-images mb-3 d-flex justify-content-center align-items-center gap-3 flex-wrap"
					:class="`case-box-images-${selectedMultiplier}`"
					v-if="!isSpinning && !showResults && selectedMultiplier <= 1">
					<div v-if="freeInfo && (freeInfo.reason === 'insufficient_deposits' || freeInfo.reason === 'no_opens_left')"
						class="limit-box d-flex flex-column justify-content-center align-items-center">
						<h2>До следующего кейса еще <span class="color"
								v-html="formatPrice(freeInfo.remaining, 'RUB', false, false)"></span> из <span
								v-html="formatPrice(freeInfo.required_deposits, 'RUB', false, false)"></span></h2>
						<div class="rest-limit">
							<div class="fill" :style="{ width: depositProgress + '%' }"></div>
							<div class="point" :style="{ left: depositProgress + '%' }"></div>
							<div class="btn btn-secondary text-nowrap" data-bs-toggle="tooltip"
								data-bs-title="При достижении нужной суммы пополнения кейс становится доступным для открытия. Каждые 24 часа условия необходимо выполнить заново. Все неоткрытые кейсы пропадают.">
								Как это работает ?</div>
						</div>
					</div>
					<div v-for="n in selectedMultiplier" :key="`case-image-${n}`" class="case-box-image"
						:class="{ 'case-box-image-small': selectedMultiplier > 3 }"
						:style="{ backgroundImage: selectedMultiplier > 1 ? `url(/images/logo_white.svg?v=2)` : `url(/storage/${caseData.image_url})` }">
					</div>
				</div>

				<!-- Карточки с лого (множитель 2+, до и после открытия) -->
				<div class="case-box-images mb-3 d-flex justify-content-center align-items-center gap-3 flex-wrap"
					:class="`case-box-images-${selectedMultiplier}`"
					v-if="selectedMultiplier > 1">
					<div v-for="(card, index) in cardFlips" :key="`card-${index}`"
						class="d-flex flex-column align-items-center gap-2">
						<div class="case-box-image case-card-flip"
							:class="[
								{ 'case-box-image-small': selectedMultiplier > 3, 'flipped': card.flipped },
								card.flipped ? card.rarityClass : ''
							]">
							<div class="case-card-inner">
								<div class="case-card-front"
									:style="{ backgroundImage: `url(/images/logo_white.svg?v=2)` }">
								</div>
								<div class="case-card-back">
									<span v-if="card.prize.is_anti_unluck" class="badge-anti-unluck">Анти-анлак</span>
									<span v-if="card.flipped && card.prize.price !== undefined"
										class="price"
										v-html="formatPrice(card.prize.price)"></span>
									<div class="image" :style="{ backgroundImage: `url(${getItemImageUrl(card.prize)})` }">
									</div>
									<p>{{ getNameWithoutWear(card.prize.name) }}</p>
								</div>
							</div>
						</div>
						<template v-if="showResults && card.prize.inventory_id">
							<template v-if="!soldInventoryIds.includes(card.prize.inventory_id)">
								<button class="btn btn-quinary btn-sm d-inline-flex align-items-center gap-1"
									@click="sellSingleWonItem(card.prize)"
									:disabled="sellingSingleId === card.prize.inventory_id">
									<span v-if="sellingSingleId === card.prize.inventory_id"
										class="spinner-border spinner-border-sm"></span>
									<i v-else class="ico sale"></i>Продать
								</button>
							</template>
							<span v-else class="badge sold">Продано</span>
						</template>
					</div>
				</div>

				<!-- Слайдер для розыгрыша (только множитель 1) -->
				<div class="case-prize-slider" v-if="selectedMultiplier <= 1 && (isSpinning || showResults)">
					<!-- Рулетки для каждого приза -->
					<div v-for="(roulette, rouletteIndex) in roulettes" :key="`roulette-${rouletteIndex}`"
						class="case-roulette-container mb-3" :class="{ 'roulette-active': roulette.isActive }">
						<!-- Окошко для приза -->
						<div class="roulette-window"></div>

						<!-- Слайдер -->
						<div class="roulette-wrapper">
							<div class="roulette-track" :class="{ 'spinning': roulette.isSpinning }"
								:style="{ transform: `translateX(${roulette.sliderOffset}px)` }">
								<div v-for="(item, index) in roulette.displayItems"
									:key="`item-${rouletteIndex}-${index}`" :data-item-id="item.id"
									class="roulette-item case-content-item" :class="[
										{
											'winner': item.isWinner,
											'center': getItemPosition(roulette, index) === 'center'
										},
										getRarityClass(item)
									]">
									<span v-if="item.isWinner && roulette.prize.is_anti_unluck" class="badge-anti-unluck">Анти-анлак</span>
									<div class="image" :style="{ backgroundImage: `url(${getItemImageUrl(item)})` }">
									</div>
									<div class="d-flex justify-content-center name-box">
										<p class="w-75 text-center">{{ getNameWithoutWear(item.name) }}</p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="case-box-footer p-2 position-relative" v-if="allItems.length > 0">
					<div class="multiplier-box" v-if="!isSpinning && !showResults">
						<ul class="nav justify-content-center justify-content-lg-start case-box-qty gap-1">
							<li class="nav-item" v-for="mult in allMultipliers" :key="mult">
								<button class="btn btn-tertiary" :class="{ 'active': selectedMultiplier === mult }"
									:disabled="!isMultiplierAvailable(mult) || isProcessing || freeDisabled || limitedDisabled"
									@click="selectMultiplier(mult)">
									{{ mult }}
								</button>
							</li>
						</ul>
					</div>
					<div class="d-flex justify-content-center align-items-center mb-3 mb-lg-0 gap-2 position-relative">
						<template v-if="showResults">
							<div class="col-6 d-flex justify-content-end">
								<button class="btn btn-primary px-4" @click="resetAndReopen">
									Попробовать еще раз
								</button>
							</div>
							<div class="col-6">
								<button class="btn btn-secondary px-4" @click="sellWonItems"
									:disabled="sellingWonItems || remainingWonItems.length === 0">
									<span v-if="sellingWonItems" class="spinner-border spinner-border-sm me-1"></span>
									Продать за&nbsp;<span v-html="formatPrice(totalWonPrice)"></span>
								</button>
							</div>
						</template>
						<template v-else-if="isProcessing || isSpinning">
							<button class="btn btn-quaternary px-5" disabled>
								<span class="spinner-border spinner-border-sm me-2"></span>
								Кейс открывается...
							</button>
						</template>
						<template v-else>
							<div class="col-6 d-flex justify-content-end">
								<button type="button" class="btn btn-primary px-5"
									:disabled="freeDisabled || limitedDisabled" @click="confirmPurchase">
									Открыть за&nbsp;<span
										v-html="formatPrice(totalPrice, 'RUB', false, false)"></span>&nbsp;<span
										v-if="hasDiscount" class="original-price"
										v-html="formatPrice(totalOriginalPrice, 'RUB', false, false)"></span>
								</button>
							</div>
							<div class="col-6">
								<button class="btn btn-tertiary px-4" :disabled="freeDisabled || limitedDisabled"
									@click="openFast" v-if="!showResults && !isProcessing && !isSpinning">
									Открыть быстро за&nbsp;<span
										v-html="formatPrice(totalPrice, 'RUB', false, false)"></span>&nbsp;<span
										v-if="hasDiscount" class="original-price"
										v-html="formatPrice(totalOriginalPrice, 'RUB', false, false)"></span>
								</button>
							</div>
						</template>
					</div>
				</div>
			</div>
			<h3 class="category-title text-center mb-5 d-flex align-items-center justify-content-center gap-2"
				v-if="allItems.length > 0">
				<svg width="19" height="15" viewBox="0 0 19 15" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path
						d="M18.4298 12.5C18.6703 12.5 18.8005 12.7825 18.6436 12.9648L17.7657 13.9844C17.552 14.2325 17.2406 14.375 16.9132 14.375H1.7989C1.47146 14.375 1.16006 14.2325 0.946363 13.9844L0.0684336 12.9648C-0.0884056 12.7825 0.0417616 12.5 0.282301 12.5H18.4298Z"
						fill="url(#paint0_linear_2034_1647)" />
					<path fill-rule="evenodd" clip-rule="evenodd"
						d="M4.07625 2.50195C4.25093 2.92094 4.6604 3.19419 5.11433 3.19434H13.5977C14.0516 3.19416 14.4611 2.9209 14.6358 2.50195L15.1798 1.19824H18.2589C18.4141 1.1983 18.5401 1.3242 18.5401 1.47949V11.2988C18.54 11.454 18.4141 11.58 18.2589 11.5801H0.453199C0.297984 11.5801 0.172097 11.454 0.171949 11.2988V1.47949C0.171949 1.32418 0.297892 1.19827 0.453199 1.19824H3.5323L4.07625 2.50195ZM6.84187 5.59082C6.68666 5.59096 6.56062 5.71683 6.56062 5.87207V7.30566C6.56077 7.46078 6.68676 7.58677 6.84187 7.58691H11.8702C12.0253 7.58674 12.1513 7.46076 12.1514 7.30566V5.87207C12.1514 5.71685 12.0254 5.591 11.8702 5.59082H6.84187Z"
						fill="url(#paint1_linear_2034_1647)" />
					<path
						d="M14.2247 0C14.431 9.78978e-07 14.5667 0.2148 14.4786 0.401367L14.088 1.23047C13.9021 1.62399 13.5056 1.87494 13.0704 1.875H5.64168C5.20649 1.87494 4.80993 1.62399 4.6241 1.23047L4.23347 0.401367C4.14537 0.214799 4.28106 0 4.48738 0H14.2247Z"
						fill="url(#paint2_linear_2034_1647)" />
					<defs>
						<linearGradient id="paint0_linear_2034_1647" x1="9.35603" y1="0" x2="9.35603" y2="14.375"
							gradientUnits="userSpaceOnUse">
							<stop stop-color="#FF8C00" />
							<stop offset="1" stop-color="#FFD400" />
						</linearGradient>
						<linearGradient id="paint1_linear_2034_1647" x1="9.35603" y1="0" x2="9.35603" y2="14.375"
							gradientUnits="userSpaceOnUse">
							<stop stop-color="#FF8C00" />
							<stop offset="1" stop-color="#FFD400" />
						</linearGradient>
						<linearGradient id="paint2_linear_2034_1647" x1="9.35603" y1="0" x2="9.35603" y2="14.375"
							gradientUnits="userSpaceOnUse">
							<stop stop-color="#FF8C00" />
							<stop offset="1" stop-color="#FFD400" />
						</linearGradient>
					</defs>
				</svg>
				Содержимое кейса
			</h3>
			<div class="case-content row g-4" v-if="allItems.length > 0">
				<div v-for="item in allItems" :key="item.id" class="col-lg-2 col-md-3 col-sm-4 col-6">
					<div class="h-100 case-content-item" :class="getRarityClass(item)">
						<div class="image" :style="{ backgroundImage: `url(${getItemImageUrl(item)})` }">
						</div>
						<div class="d-flex flex-column align-items-center">
							<p class="w-75 text-center mb-1">{{ item.name }}</p>
							<span class="item-price" v-html="formatPrice(item.price)"></span>
						</div>
					</div>
				</div>
			</div>

			<!-- Сообщение если предметов нет -->
			<div v-if="allItems.length === 0" class="text-center py-5">
				<i class="ri-archive-line fs-1 text-muted mb-3"></i>
				<h4>Предметы не найдены</h4>
				<p class="text-muted">В этом кейсе пока нет предметов.</p>
			</div>

		</div>

		<DepositModal
			ref="depositModal"
			modal-id="case-deposit-modal"
			class="theme-cases-modal"
			:deposit-settings="depositSettings"
			title="Пополнение баланса"
			@success="onDepositSuccess" />
	</section>
</template>

<script>
import { formatPrice, pluralize } from '../../shared/utils/helpers';
import axios from 'axios';
import DepositModal from '../../shared/components/DepositModal.vue';

// Animation constants
const ANIMATION_CONFIG = {
	ITEM_WIDTH: 200,
	ITEM_GAP: 10,
	MAX_SPEED: 40,
	MIN_SPEED: 4,
	DECELERATION_CARDS: 8,
	AUTO_SCROLL_SPEED: 2,
	ANIMATION_FRAME_RATE: 16,
	COMPLETION_DELAY: 200,
	OPACITY_ANIMATION_DURATION: '0.8s',
	FINAL_OPACITY: '0.05',
	MIN_WINNING_INDEX: 10,
	DISPLAY_ITEMS_COUNT: 50,
	MIN_SCROLL_CARDS: 25,
	OVERSHOOT_DISTANCE: 50,
	OVERSHOOT_RETURN_DURATION: 1500,
	OVERSHOOT_EASING: 'cubic-bezier(0.22, 1, 0.36, 1)',
	FAST_OPEN_MULTIPLIER: 2,
	ROULETTE_DELAY: 500 // Задержка между запуском рулеток (мс)
};

// Preload sounds
const dropSounds = {
	approval: new Audio('/sounds/approval_v2.mp3'),
	fast: new Audio('/sounds/main_v2.mp3'),
	final: new Audio('/sounds/final_v2.mp3'),
	error: new Audio('/sounds/error.mp3'),
};
Object.values(dropSounds).forEach(s => s.load());

export default {
	name: 'CaseDetails',

	components: { DepositModal },

	props: {
		initialCase: {
			type: Object,
			required: true
		},
		caseSlug: {
			type: String,
			required: true
		},
		routes: {
			type: Object,
			required: true
		},
		depositSettings: {
			type: Object,
			default: () => ({})
		},
		userBalance: {
			type: Object,
			default: null
		}
	},

	setup() {
		return { formatPrice, pluralize };
	},

	data() {
		return {
			// Case data
			caseData: { ...this.initialCase },
			allItems: [],
			activeLoopSound: null,
			soundMuted: (typeof localStorage !== 'undefined' && localStorage.getItem('cs_case_sound_muted') === '1'),

			// Animation state
			isOpening: false,
			isProcessing: false,
			isSpinning: false,
			showResults: false,
			wonItems: [],
			sellingWonItems: false,

			// Multiple roulettes support
			roulettes: [],
			currentRouletteIndex: 0,

			// Card flip (multiplier 2+)
			cardFlips: [],
			soldInventoryIds: [],
			sellingSingleId: null,

			// Multiplier selection
			allMultipliers: [1, 2, 3, 4, 5, 10],
			selectedMultiplier: 1,
			availableMultipliers: [],

			// Slider state for auto-scroll preview
			displayItems: [],
			sliderOffset: 0,
			animationInterval: null,

			// Configuration
			itemWidth: ANIMATION_CONFIG.ITEM_WIDTH,
			itemGap: ANIMATION_CONFIG.ITEM_GAP,

			// Free case countdown
			freeCountdown: '',
			freeCountdownInterval: null,

			// Limited case countdown
			limitedCountdown: '',
			limitedCountdownInterval: null,

			// Локальный баланс для проверки перед покупкой
			localBalance: this.userBalance ? { ...this.userBalance } : null,
		};
	},

	computed: {
		totalPrice() {
			return this.caseData.price * this.selectedMultiplier;
		},
		totalOriginalPrice() {
			return (this.caseData.original_price || this.caseData.price) * this.selectedMultiplier;
		},
		hasDiscount() {
			return this.caseData.has_discount;
		},
		totalWonPrice() {
			return this.wonItems
				.filter(item => !this.soldInventoryIds.includes(item.inventory_id))
				.reduce((sum, item) => sum + item.price, 0);
		},
		remainingWonItems() {
			return this.wonItems.filter(item => !this.soldInventoryIds.includes(item.inventory_id));
		},
		freeInfo() {
			return this.caseData.free_opens_info || null;
		},
		depositProgress() {
			if (!this.freeInfo || !this.freeInfo.required_deposits) return 0;
			const deposited = this.freeInfo.required_deposits - this.freeInfo.remaining;
			return Math.round((deposited / this.freeInfo.required_deposits) * 100);
		},
		isFreeCase() {
			return this.caseData.case_type === 'free';
		},
		hasFreeOpens() {
			return this.freeInfo && this.freeInfo.available === true;
		},
		freeDisabled() {
			return this.isFreeCase && !this.hasFreeOpens;
		},
		isLimitedCase() {
			return this.caseData.case_type === 'limited';
		},
		limitedRemainingOpens() {
			if (!this.isLimitedCase || this.caseData.total_opens_limit === null) return null;
			return Math.max(0, this.caseData.total_opens_limit - (this.caseData.total_opens_count || 0));
		},
		limitedTimeExpired() {
			return this.isLimitedCase && this.caseData.available_until && new Date(this.caseData.available_until) <= Date.now();
		},
		limitedDisabled() {
			if (!this.isLimitedCase) return false;
			if (this.limitedRemainingOpens !== null && this.limitedRemainingOpens <= 0) return true;
			if (this.caseData.available_until && new Date(this.caseData.available_until) <= Date.now()) return true;
			return false;
		},
	},

	watch: {
		'caseData.free_opens_info': {
			handler() {
				this.startFreeCountdown();
			},
			immediate: true,
		},
		'caseData.available_until': {
			handler() {
				this.startLimitedCountdown();
			},
			immediate: true,
		},
		selectedMultiplier: {
			handler(val) {
				if (val > 1) {
					this.initCardFlips(val);
				}
			},
			immediate: true,
		},
	},

	methods: {
		startFreeCountdown() {
			if (this.freeCountdownInterval) {
				clearInterval(this.freeCountdownInterval);
				this.freeCountdownInterval = null;
			}
			if (!this.freeInfo || !this.freeInfo.expires_at) return;

			const update = () => {
				const diff = new Date(this.freeInfo.expires_at) - Date.now();
				if (diff <= 0) {
					this.freeCountdown = '';
					clearInterval(this.freeCountdownInterval);
					this.freeCountdownInterval = null;
					this.loadCaseDetails();
					return;
				}
				const h = Math.floor(diff / 3600000);
				const m = Math.floor((diff % 3600000) / 60000);
				const s = Math.floor((diff % 60000) / 1000);
				this.freeCountdown = `${h} ч ${m} м ${s} сек`;
			};
			update();
			this.freeCountdownInterval = setInterval(update, 1000);
		},

		startLimitedCountdown() {
			if (this.limitedCountdownInterval) {
				clearInterval(this.limitedCountdownInterval);
				this.limitedCountdownInterval = null;
			}
			if (!this.caseData.available_until) return;

			const update = () => {
				const diff = new Date(this.caseData.available_until) - Date.now();
				if (diff <= 0) {
					this.limitedCountdown = 'Время вышло';
					clearInterval(this.limitedCountdownInterval);
					this.limitedCountdownInterval = null;
					return;
				}
				const d = Math.floor(diff / 86400000);
				const h = Math.floor((diff % 86400000) / 3600000);
				const m = Math.floor((diff % 3600000) / 60000);
				const s = Math.floor((diff % 60000) / 1000);
				this.limitedCountdown = d > 0
					? `${d}д ${h} ч ${m} м ${s} сек`
					: `${h} ч ${m} м ${s} сек`;
			};
			update();
			this.limitedCountdownInterval = setInterval(update, 1000);
		},

		getNameWithoutWear(name) {
			if (!name) return '';
			// Убираем качество в скобках: (Factory New), (Field-Tested) и т.д.
			return name.replace(/\s*\((Factory New|Minimal Wear|Field-Tested|Well-Worn|Battle-Scarred)\)\s*$/i, '').trim();
		},

		async loadCaseDetails() {
			try {
				const response = await axios.get(`/api/cases/${this.caseSlug}`);
				this.caseData = response.data.data;
				this.processItems();
				this.updateAvailableMultipliers();
			} catch (error) {
				console.error('Error loading case details:', error);
			}
		},

		processItems() {
			const items = [];

			if (this.caseData.tiers) {
				this.caseData.tiers.forEach(tier => {
					if (tier.items) {
						tier.items.forEach(item => {
							items.push({
								id: item.id,
								name: item.name,
								price: item.price,
								image_url: item.image_url,
								tier_id: tier.id,
								tier_name: tier.name,
								probability: tier.probability,
								rarity: item.rarity,
								rarity_color: item.rarity_color,
								quality: item.quality,
								weapon_type: item.weapon_type,
							});
						});
					}
				});
			}

			this.allItems = this.sortItemsByRarity([...items]);
			this.generateDisplayItems();
			this.startAutoScroll();
		},

		updateAvailableMultipliers() {
			if (this.caseData.multipliers && this.caseData.multipliers.available) {
				this.availableMultipliers = this.caseData.multipliers.available;
			} else {
				this.availableMultipliers = [1];
			}

			// Если текущий множитель недоступен, сбрасываем на 1
			if (!this.availableMultipliers.includes(this.selectedMultiplier)) {
				this.selectedMultiplier = this.availableMultipliers[0] || 1;
			}
		},

		isMultiplierAvailable(mult) {
			return this.availableMultipliers.includes(mult);
		},

		selectMultiplier(mult) {
			if (this.isMultiplierAvailable(mult)) {
				this.selectedMultiplier = mult;
			}
		},

		generateDisplayItems() {
			if (this.allItems.length === 0) return;

			const items = [];
			for (let i = 0; i < ANIMATION_CONFIG.DISPLAY_ITEMS_COUNT; i++) {
				const item = this.allItems[i % this.allItems.length];
				items.push({
					...item,
					uniqId: `item-${i}`,
					isWinner: false
				});
			}

			this.displayItems = items;
		},

		createRouletteDisplayItems() {
			if (this.allItems.length === 0) return [];

			const items = [];
			for (let i = 0; i < ANIMATION_CONFIG.DISPLAY_ITEMS_COUNT; i++) {
				const item = this.allItems[i % this.allItems.length];
				items.push({
					...item,
					uniqId: `roulette-item-${i}`,
					isWinner: false
				});
			}
			return items;
		},

		startAutoScroll() {
			if (!this.displayItems || this.displayItems.length === 0) return;

			if (this.animationInterval) {
				clearInterval(this.animationInterval);
			}

			this.animationInterval = setInterval(() => {
				if (!this.isSpinning && !this.showResults) {
					this.sliderOffset -= ANIMATION_CONFIG.AUTO_SCROLL_SPEED;

					if (Math.abs(this.sliderOffset) >= (this.itemWidth + this.itemGap)) {
						this.sliderOffset = 0;
						this.displayItems.push(this.displayItems.shift());
					}
				}
			}, ANIMATION_CONFIG.ANIMATION_FRAME_RATE);
		},

		sortItemsByRarity(items) {
			const rarityOrder = {
				'Contraband': 7,
				'Covert': 6,
				'Classified': 5,
				'Restricted': 4,
				'Mil-Spec Grade': 3,
				'Mil-Spec': 3,
				'Industrial Grade': 2,
				'Consumer Grade': 1,
				'Extraordinary': 6,
				'Exotic': 5,
				'Remarkable': 4,
				'High Grade': 2,
				'Base Grade': 1,
			};

			return items.sort((a, b) => {
				const rarityA = rarityOrder[a.rarity] || 0;
				const rarityB = rarityOrder[b.rarity] || 0;

				if (rarityB !== rarityA) {
					return rarityB - rarityA;
				}

				return (b.price || 0) - (a.price || 0);
			});
		},

		shuffleArray(array) {
			const shuffled = [...array];
			for (let i = shuffled.length - 1; i > 0; i--) {
				const j = Math.floor(Math.random() * (i + 1));
				[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
			}
			return shuffled;
		},

		async confirmPurchase() {
			await this.purchaseCase(false);
		},

		async openFast() {
			await this.purchaseCase(true);
		},

		handleBalanceUpdated(e) {
			if (!this.localBalance) this.localBalance = { main: 0, bonus: 0 };
			if (typeof e.detail?.main === 'number') this.localBalance.main = e.detail.main;
			if (typeof e.detail?.bonus === 'number') this.localBalance.bonus = e.detail.bonus;
		},

		onDepositSuccess(amount) {
			if (!this.localBalance) this.localBalance = { main: 0, bonus: 0 };
			this.localBalance.main = (this.localBalance.main || 0) + parseFloat(amount);
			window.dispatchEvent(new CustomEvent('balance-updated', { detail: { main: this.localBalance.main } }));
		},

		async purchaseCase(fastMode = false) {
			// Клиентская проверка баланса (основной + бонусный) — если не хватает,
			// открываем модалку пополнения вместо запроса на сервер.
			if (this.localBalance && !this.isFreeCase && this.totalPrice > 0) {
				const totalAvailable = (this.localBalance.main || 0) + (this.localBalance.bonus || 0);
				if (totalAvailable < this.totalPrice) {
					const required = this.totalPrice - totalAvailable;
					this.playDropSound('error');
					this.$refs.depositModal?.open({
						amount: required,
						message: `Недостаточно средств для открытия кейса. Не хватает ${Math.ceil(required)} ₽.`
					});
					return;
				}
			}

			this.playDropSound('approval');
			this.isProcessing = true;

			try {
				const response = await axios.post(`/api/cases/purchase`, {
					case_id: this.caseData.id,
					count: this.selectedMultiplier
				}, { suppressToast: true });

				if (response.data.success) {
					const prizes = response.data.prizes;
					this.wonItems = prizes;

					// Обновляем баланс в хедере
					if (response.data.balance) {
						window.dispatchEvent(new CustomEvent('balance-updated', {
							detail: response.data.balance
						}));
					}

					this.isProcessing = false;

					// Запускаем анимацию
					if (this.selectedMultiplier > 1) {
						await this.startCardFlipAnimation(prizes, fastMode);
					} else {
						await this.startMultipleRoulettes(prizes, fastMode);
					}
				}
			} catch (error) {
				this.isProcessing = false;
				const msg = error.response?.data?.message || '';
				// Если сервер сообщил о недостатке средств — открываем модалку пополнения
				if (msg.toLowerCase().includes('недостаточно средств')) {
					const required = this.localBalance
						? Math.max(0, this.totalPrice - ((this.localBalance.main || 0) + (this.localBalance.bonus || 0)))
						: this.totalPrice;
					this.playDropSound('error');
					this.$refs.depositModal?.open({
						amount: required || this.totalPrice,
						message: 'Недостаточно средств для открытия кейса'
					});
				} else if (msg) {
					window.toast?.error(msg);
				}
				console.error('Error purchasing case:', error);
			}
		},

		async startMultipleRoulettes(prizes, fastMode = false) {
			this.isSpinning = true;
			this.showResults = false;

			// Останавливаем автопрокрутку
			if (this.animationInterval) {
				clearInterval(this.animationInterval);
			}

			// Создаём рулетки для каждого приза
			this.roulettes = prizes.map((prize, index) => ({
				prize: prize,
				displayItems: this.createRouletteDisplayItems(),
				sliderOffset: 0,
				isSpinning: false,
				isActive: false,
				isCompleted: false,
				wonItemIndex: null
			}));

			await this.$nextTick();

			// Пауза после звука approval перед стартом рулетки (одиночное открытие; в fast — в 2 раза короче)
			if (this.roulettes.length === 1) {
				await this.delay(fastMode ? 250 : 500);
			}

			// Запускаем все рулетки одновременно
			const roulettePromises = this.roulettes.map((roulette, i) => {
				roulette.isActive = true;
				return this.spinSingleRoulette(i, fastMode);
			});

			// Ждём завершения всех рулеток
			await Promise.all(roulettePromises);

			// Все рулетки завершены
			this.isSpinning = false;
			this.showResults = true;
		},

		initCardFlips(count) {
			this.cardFlips = Array.from({ length: count }, () => ({
				prize: { name: '', image_url: '' },
				flipped: false,
				rarityClass: '',
			}));
		},

		async startCardFlipAnimation(prizes, fastMode = false) {
			this.isSpinning = true;
			this.showResults = false;

			if (this.animationInterval) {
				clearInterval(this.animationInterval);
			}

			// Заполняем карточки призами
			this.cardFlips = prizes.map(prize => ({
				prize: prize,
				flipped: false,
				rarityClass: this.getRarityClass(prize),
			}));

			await this.$nextTick();

			if (fastMode) {
				this.cardFlips.forEach(card => { card.flipped = true; });
				this.playDropSound('final');
			} else {
				for (let i = 0; i < this.cardFlips.length; i++) {
					this.cardFlips[i].flipped = true;
					this.playDropSound('final');
					if (i < this.cardFlips.length - 1) {
						await this.delay(500);
					}
				}
			}

			await this.delay(300);
			this.isSpinning = false;
			this.showResults = true;
		},

		playDropSound(type) {
			if (this.soundMuted) return;
			const sound = dropSounds[type];
			if (!sound) return;
			const clone = new Audio(sound.src);
			clone.volume = sound.volume;
			clone.play().catch(() => { });
		},

		toggleSound() {
			this.soundMuted = !this.soundMuted;
			try { localStorage.setItem('cs_case_sound_muted', this.soundMuted ? '1' : '0'); } catch (e) { }
			if (this.soundMuted) this.stopLoopSound();
		},

		/**
		 * Запускает серию тиков `type` с интервалом, который вычисляется callback'ом
		 * перед каждым тиком (можно завязать на текущую скорость рулетки).
		 * getInterval возвращает мс до следующего тика или null чтобы остановить.
		 */
		startTickSound(getInterval) {
			this.stopLoopSound();

			const tick = () => {
				const res = getInterval();
				if (res == null) {
					this.activeLoopSound = null;
					return;
				}
				const { interval, type } = typeof res === 'number' ? { interval: res, type: 'fast' } : res;
				const sound = dropSounds[type];
				if (sound) {
					const clone = sound.cloneNode();
					clone.volume = sound.volume;
					clone.play().catch(() => { });
				}
				this.activeLoopSound = setTimeout(tick, interval);
			};
			tick();
		},

		stopLoopSound() {
			if (this.activeLoopSound) {
				try {
					if (typeof this.activeLoopSound === 'number') {
						clearTimeout(this.activeLoopSound);
					} else {
						this.activeLoopSound.pause();
						this.activeLoopSound.currentTime = 0;
					}
				} catch (e) { }
				this.activeLoopSound = null;
			}
		},

		spinSingleRoulette(rouletteIndex, fastMode = false) {
			return new Promise(async (resolve) => {
				const roulette = this.roulettes[rouletteIndex];
				const prize = roulette.prize;

				roulette.isSpinning = true;

				await this.$nextTick();

				// Находим позицию для остановки
				const stopItemIndex = this.findWinningStopPositionForRoulette(roulette, prize.id);

				// Получаем размеры контейнера
				const containers = this.$el?.querySelectorAll('.case-roulette-container');
				const containerElement = containers ? containers[rouletteIndex] : null;
				const containerWidth = containerElement ? containerElement.offsetWidth : window.innerWidth;
				const containerCenter = containerWidth / 2;

				// Получаем реальные размеры карточки
				const trackElement = containerElement?.querySelector('.roulette-track');
				const firstCard = trackElement?.querySelector('.roulette-item');

				let realItemWidth = this.itemWidth;
				let realItemGap = this.itemGap;

				if (firstCard) {
					const style = window.getComputedStyle(firstCard);
					const marginLeft = parseInt(style.marginLeft) || 0;
					const marginRight = parseInt(style.marginRight) || 0;
					realItemWidth = firstCard.offsetWidth;
					realItemGap = marginLeft + marginRight;
				}

				// Вычисляем финальную позицию
				const cardLeftEdge = stopItemIndex * (realItemWidth + realItemGap);
				const cardCenterInTrack = cardLeftEdge + (realItemGap / 2) + (realItemWidth / 2);
				const finalTargetOffset = containerCenter - cardCenterInTrack;

				const speedMultiplier = fastMode ? ANIMATION_CONFIG.FAST_OPEN_MULTIPLIER : 1;
				const overshootDistance = ANIMATION_CONFIG.OVERSHOOT_DISTANCE;

				// При открытии одного кейса — рандомная точка остановки в пределах ширины предмета
				// (5 позиций: ~левый край, между левым и центром, центр, между центром и правым, ~правый край).
				// Затем returnRouletteToCenter плавно доводит до центра.
				let overshootTargetOffset;
				if (this.roulettes.length === 1) {
					// 4 точки между центром и краями (рулетка останавливается, делает паузу, потом плавно доезжает в центр).
					const stopOffsets = [-0.45, -0.3, 0.3, 0.45];
					const pick = stopOffsets[Math.floor(Math.random() * stopOffsets.length)];
					overshootTargetOffset = finalTargetOffset + pick * realItemWidth;
				} else {
					overshootTargetOffset = finalTargetOffset - overshootDistance;
				}

				const isSingle = this.roulettes.length === 1;
				// Для одиночного открытия удлиняем фазу замедления и повышаем пиковую скорость.
				// В fast-режиме всё ещё дополнительно ускоряется через speedMultiplier=FAST_OPEN_MULTIPLIER.
				const decelerationCards = isSingle ? ANIMATION_CONFIG.DECELERATION_CARDS * 3 : ANIMATION_CONFIG.DECELERATION_CARDS;
				const maxSpeedBase = isSingle ? ANIMATION_CONFIG.MAX_SPEED * 2.5 : ANIMATION_CONFIG.MAX_SPEED;

				const slowDownDistance = decelerationCards * (realItemWidth + realItemGap);
				const slowDownPoint = finalTargetOffset + slowDownDistance;

				let currentSpeed = maxSpeedBase * speedMultiplier;
				const maxSpeed = maxSpeedBase * speedMultiplier;
				const minSpeed = ANIMATION_CONFIG.MIN_SPEED * speedMultiplier;

				const cardSpacing = realItemWidth + realItemGap;
				const playTicks = isSingle;
				let lastCardIndex = Math.floor((containerCenter - roulette.sliderOffset) / cardSpacing);
				let finalPlayed = false;

				const spinInterval = setInterval(() => {
					roulette.sliderOffset -= currentSpeed;

					if (roulette.sliderOffset <= slowDownPoint) {
						const remainingDistance = Math.abs(roulette.sliderOffset - overshootTargetOffset);
						const slowDownDistanceCalc = Math.abs(slowDownPoint - overshootTargetOffset);

						if (remainingDistance > 0) {
							const progress = remainingDistance / slowDownDistanceCalc;
							const easedProgress = progress * progress;
							currentSpeed = Math.max(minSpeed, easedProgress * (maxSpeed - minSpeed) + minSpeed);
						}
					}

					// Звук тика — в момент пересечения стрелкой левого края очередной карточки.
					if (playTicks) {
						const cardIndex = Math.floor((containerCenter - roulette.sliderOffset) / cardSpacing);
						if (cardIndex > lastCardIndex) {
							lastCardIndex = cardIndex;
							this.playDropSound('fast');
						}
					}

					if (roulette.sliderOffset <= overshootTargetOffset) {
						clearInterval(spinInterval);
						roulette.sliderOffset = overshootTargetOffset;
						// Для multi/fast — final сразу при остановке.
						// Для single — final играется в returnRouletteToCenter в момент isCompleted (когда появляются кнопки).
						if (!playTicks && !finalPlayed) {
							finalPlayed = true;
							this.playDropSound('final');
						}
						// Пауза после остановки перед доводкой в центр (только одиночное обычное открытие).
						const pause = playTicks ? 10 : 0;
						setTimeout(() => {
							this.returnRouletteToCenтer(rouletteIndex, finalTargetOffset, stopItemIndex, speedMultiplier, resolve, playTicks);
						}, pause);
					}
				}, ANIMATION_CONFIG.ANIMATION_FRAME_RATE);
			});
		},

		returnRouletteToCenтer(rouletteIndex, finalTargetOffset, stopItemIndex, speedMultiplier, callback, playFinalAtEnd = false) {
			const containers = this.$el?.querySelectorAll('.case-roulette-container');
			const containerElement = containers ? containers[rouletteIndex] : null;
			const trackElement = containerElement?.querySelector('.roulette-track');
			const roulette = this.roulettes[rouletteIndex];

			if (!trackElement) {
				roulette.sliderOffset = finalTargetOffset;
				roulette.isSpinning = false;
				roulette.isCompleted = true;
				if (playFinalAtEnd) this.playDropSound('final');
				callback();
				return;
			}

			const returnDuration = ANIMATION_CONFIG.OVERSHOOT_RETURN_DURATION / speedMultiplier;
			trackElement.style.transition = `transform ${returnDuration}ms ${ANIMATION_CONFIG.OVERSHOOT_EASING}`;

			roulette.sliderOffset = finalTargetOffset;

			setTimeout(() => {
				trackElement.style.transition = '';
				roulette.wonItemIndex = stopItemIndex;

				// Делаем все карточки прозрачными кроме выигранной
				const cardItems = trackElement?.querySelectorAll('.roulette-item');
				if (cardItems) {
					cardItems.forEach((card, index) => {
						if (index !== stopItemIndex) {
							card.style.transition = `opacity ${ANIMATION_CONFIG.OPACITY_ANIMATION_DURATION} ease`;
							card.style.opacity = ANIMATION_CONFIG.FINAL_OPACITY;
						}
					});
				}

				setTimeout(() => {
					roulette.isSpinning = false;
					roulette.isCompleted = true;
					if (playFinalAtEnd) this.playDropSound('final');
					callback();
				}, ANIMATION_CONFIG.COMPLETION_DELAY);
			}, returnDuration);
		},

		findWinningStopPositionForRoulette(roulette, winningItemId) {
			const currentScrolled = Math.abs(roulette.sliderOffset);
			const itemFullWidth = this.itemWidth + this.itemGap;
			const scrolledCards = Math.floor(currentScrolled / itemFullWidth);
			const minScrollCards = Math.max(ANIMATION_CONFIG.MIN_SCROLL_CARDS, scrolledCards + ANIMATION_CONFIG.MIN_SCROLL_CARDS);

			// Ищем карточку с нужным ID
			for (let i = minScrollCards; i < roulette.displayItems.length; i++) {
				if (roulette.displayItems[i].id === winningItemId) {
					return i;
				}
			}

			for (let i = 0; i < roulette.displayItems.length; i++) {
				if (roulette.displayItems[i].id === winningItemId) {
					return i + roulette.displayItems.length + ANIMATION_CONFIG.MIN_SCROLL_CARDS;
				}
			}

			return this.createWinningPositionForRoulette(roulette, winningItemId, minScrollCards);
		},

		createWinningPositionForRoulette(roulette, winningItemId, minPosition) {
			const originalItem = this.allItems.find(item => item.id === winningItemId);

			if (originalItem) {
				const targetIndex = minPosition + Math.floor(Math.random() * 10);

				if (targetIndex < roulette.displayItems.length) {
					roulette.displayItems[targetIndex] = {
						...originalItem,
						uniqId: `winning-${targetIndex}`,
						isWinner: false
					};
					return targetIndex;
				} else {
					roulette.displayItems.push({
						...originalItem,
						uniqId: `winning-end`,
						isWinner: false
					});
					return roulette.displayItems.length - 1;
				}
			}

			return minPosition + Math.floor(Math.random() * 5);
		},

		delay(ms) {
			return new Promise(resolve => setTimeout(resolve, ms));
		},

		resetAndReopen() {
			// Сбрасываем состояние
			this.showResults = false;
			this.wonItems = [];
			this.roulettes = [];
			this.cardFlips = [];
			this.soldInventoryIds = [];
			this.sellingSingleId = null;
			this.currentRouletteIndex = 0;
			this.selectedMultiplier = 1;

			// Перезагружаем данные кейса для обновления множителей
			this.loadCaseDetails();
		},

		async sellWonItems() {
			const remaining = this.remainingWonItems;
			if (this.sellingWonItems || remaining.length === 0) return;

			this.sellingWonItems = true;

			try {
				const itemIds = remaining.map(item => item.inventory_id);
				const response = await axios.post('/api/case-inventory/sell', {
					item_ids: itemIds
				});

				if (response.data.success) {
					// Обновляем баланс в хедере
					if (response.data.balance) {
						window.dispatchEvent(new CustomEvent('balance-updated', {
							detail: { main: response.data.balance }
						}));
					}

					// Сбрасываем состояние и перезагружаем
					this.resetAndReopen();
				}
			} catch (error) {
				console.error('Ошибка продажи:', error);
				alert(error.response?.data?.message || 'Ошибка при продаже предметов');
			} finally {
				this.sellingWonItems = false;
			}
		},

		async sellSingleWonItem(prize) {
			if (!prize?.inventory_id) return;
			if (this.sellingSingleId !== null) return;
			if (this.soldInventoryIds.includes(prize.inventory_id)) return;

			this.sellingSingleId = prize.inventory_id;

			try {
				const response = await axios.post('/api/case-inventory/sell', {
					item_ids: [prize.inventory_id]
				});

				if (response.data.success) {
					this.soldInventoryIds.push(prize.inventory_id);

					if (response.data.balance) {
						window.dispatchEvent(new CustomEvent('balance-updated', {
							detail: { main: response.data.balance }
						}));
					}
				}
			} catch (error) {
				console.error('Ошибка продажи:', error);
				alert(error.response?.data?.message || 'Ошибка при продаже предмета');
			} finally {
				this.sellingSingleId = null;
			}
		},

		getItemImageUrl(item) {
			if (!item.image_url) {
				return '/images/logo_ico.svg';
			}

			// Если URL уже полный (содержит http/https) - возвращаем как есть
			if (item.image_url.startsWith('http://') || item.image_url.startsWith('https://')) {
				return item.image_url;
			}

			// Иначе добавляем префикс Steam CDN
			return `https://community.steamstatic.com/economy/image/${item.image_url}`;
		},

		handleImageError(event) {
			event.target.src = '/images/logo_ico.svg';
		},

		getTierName(tierId) {
			const tier = this.caseData.tiers?.find(t => t.id === tierId);
			return tier ? tier.name : 'Неизвестно';
		},

		handleCurrencyChange() {
			this.loadCaseDetails();
		},

		getItemPosition(roulette, index) {
			const containers = this.$el?.querySelectorAll('.case-roulette-container');
			const rouletteIdx = this.roulettes.indexOf(roulette);
			const containerElement = containers ? containers[rouletteIdx] : null;

			if (!containerElement) return null;

			const containerWidth = containerElement.offsetWidth;
			const containerCenter = containerWidth / 2;

			const halfItemWidth = this.itemWidth / 2;
			const centerZone = halfItemWidth * 0.5;
			const centerStart = containerCenter - centerZone;
			const centerEnd = containerCenter + centerZone;

			const elementPosition = (index * (this.itemWidth + this.itemGap)) + roulette.sliderOffset;
			const elementCenter = elementPosition + halfItemWidth;

			if (elementCenter >= centerStart && elementCenter <= centerEnd) {
				return 'center';
			}

			return null;
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

		handleResize() {
			// Пересчитываем позиции для всех завершённых рулеток
			this.roulettes.forEach((roulette, index) => {
				if (roulette.isCompleted && roulette.wonItemIndex !== null) {
					this.recalculateRoulettePosition(index);
				}
			});
		},

		handlePageShow(event) {
			if (event.persisted) {
				// Страница восстановлена из bfcache — полный сброс
				if (this.animationInterval) {
					clearInterval(this.animationInterval);
					this.animationInterval = null;
				}
				if (this.freeCountdownInterval) {
					clearInterval(this.freeCountdownInterval);
					this.freeCountdownInterval = null;
				}
				if (this.limitedCountdownInterval) {
					clearInterval(this.limitedCountdownInterval);
					this.limitedCountdownInterval = null;
				}

				this.isOpening = false;
				this.isProcessing = false;
				this.isSpinning = false;
				this.showResults = false;
				this.wonItems = [];
				this.roulettes = [];
				this.currentRouletteIndex = 0;
				this.sellingWonItems = false;
				this.sliderOffset = 0;

				this.loadCaseDetails();
			}
		},

		initTooltips() {
			if (window.bootstrap?.Tooltip) {
				this.$el.querySelectorAll('[data-bs-toggle="tooltip"]')
					.forEach(el => {
						if (!window.bootstrap.Tooltip.getInstance(el)) {
							new window.bootstrap.Tooltip(el);
						}
					});
			}
		},

		recalculateRoulettePosition(rouletteIndex) {
			const roulette = this.roulettes[rouletteIndex];
			if (!roulette || roulette.isSpinning) return;

			const containers = this.$el?.querySelectorAll('.case-roulette-container');
			const containerElement = containers ? containers[rouletteIndex] : null;
			if (!containerElement) return;

			const containerWidth = containerElement.offsetWidth;
			const containerCenter = containerWidth / 2;

			const trackElement = containerElement.querySelector('.roulette-track');
			const firstCard = trackElement?.querySelector('.roulette-item');

			let realItemWidth = this.itemWidth;
			let realItemGap = this.itemGap;

			if (firstCard) {
				const style = window.getComputedStyle(firstCard);
				const marginLeft = parseInt(style.marginLeft) || 0;
				const marginRight = parseInt(style.marginRight) || 0;
				realItemWidth = firstCard.offsetWidth;
				realItemGap = marginLeft + marginRight;
			}

			const cardLeftEdge = roulette.wonItemIndex * (realItemWidth + realItemGap);
			const cardCenterInTrack = cardLeftEdge + (realItemGap / 2) + (realItemWidth / 2);
			roulette.sliderOffset = containerCenter - cardCenterInTrack;
		}
	},

	mounted() {
		this.processItems();
		this.updateAvailableMultipliers();
		window.addEventListener('currency-changed', this.handleCurrencyChange);
		window.addEventListener('resize', this.handleResize);
		window.addEventListener('pageshow', this.handlePageShow);
		window.addEventListener('balance-updated', this.handleBalanceUpdated);
	},

	updated() {
		this.initTooltips();
	},

	beforeUnmount() {
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
		window.removeEventListener('resize', this.handleResize);
		window.removeEventListener('pageshow', this.handlePageShow);
		window.removeEventListener('balance-updated', this.handleBalanceUpdated);

		if (this.animationInterval) {
			clearInterval(this.animationInterval);
		}
		if (this.freeCountdownInterval) {
			clearInterval(this.freeCountdownInterval);
		}
		if (this.limitedCountdownInterval) {
			clearInterval(this.limitedCountdownInterval);
		}
	}
}
</script>
