<template>
	<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden bg-white">
		<div class="container">
			<!-- Заголовок кейса -->
			<div class="title text-center">
				<h2>{{ caseData.name }}</h2>
				<div class="loader-line" style="left: calc(50% - 40px);"></div>
				<div class="sub-title" v-if="caseData.description">
					<p>{{ caseData.description }}</p>
				</div>
				<div class="text-center mt-2">
					<h4 class="mb-1" v-html="formatPrice(caseData.price)"></h4>
				</div>
			</div>

			<!-- Слайдер для розыгрыша -->
			<section class="banner-section section-b-space case-prize-slider">
				<div class="container">
					<div class="case-roulette-container">
						<!-- Окошко для приза -->
						<div class="roulette-window"></div>

						<!-- Слайдер -->
						<div class="roulette-wrapper">
							<div class="roulette-track" :class="{ 'spinning': isSpinning }"
								:style="{ transform: `translateX(${sliderOffset}px)` }">
								<div v-for="(item, index) in displayItems" :key="`item-${index}`"
									:data-item-id="item.id" class="roulette-item" :class="[
										{
											'winner': item.isWinner,
											'center': getItemPosition(index) === 'center'
										},
										getRarityClass(item)
									]">
									<div class="case-banner-part d-flex flex-column justify-content-end"
										:style="{ backgroundImage: `url('${getItemImageUrl(item)}')` }">
										<div class="case-banner-text">
											<p class="fw-semibold dark-text">{{ item.name }}</p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Кнопка открытия кейса -->
					<div class="text-center mt-4" v-if="!wonItem">
						<button class="btn theme-btn btn-lg px-5" 
							data-bs-toggle="modal" 
							data-bs-target="#confirmPurchaseModal"
							:disabled="isProcessing || isSpinning">
							<span v-if="isProcessing || isSpinning"
								class="spinner-border spinner-border-sm me-2"></span>
							{{ isProcessing || isSpinning ? 'Открываем...' : 'Открыть кейс' }}
						</button>
					</div>

					<!-- Результат открытия -->
					<div v-if="wonItem" class="text-center mt-4">
						<div class="alert alert-success">
							<h4 class="alert-heading">Поздравляем!</h4>
							<p class="mb-0">Вы выиграли: <strong>{{ wonItem.name }}</strong></p>
							<p class="mb-3">Стоимость: <strong v-html="formatPrice(wonItem.price)"></strong></p>
							<button class="btn theme-btn btn-lg px-5" @click="reloadPage">
								Еще раз
							</button>
						</div>
					</div>
				</div>
			</section>

			<!-- Все предметы кейса -->
			<div class="title text-center">
				<h3>Возможные предметы</h3>
				<div class="loader-line" style="left: calc(50% - 40px);"></div>
			</div>

			<div class="row g-4" v-if="allItems.length > 0">
				<div v-for="item in allItems" :key="item.id" class="col-lg-2 col-md-3 col-sm-4 col-6">
					<div class="vertical-product-box h-100" :class="getRarityClass(item)">
						<div class="vertical-product-box-img">
							<div>
								<img class="product-img-top w-100 bg-img" :src="getItemImageUrl(item)" :alt="item.name"
									@error="handleImageError">
							</div>
							<div class="offers">
								<div class="d-flex align-items-center justify-content-between">
									<h4 v-html="formatPrice(item.price)"></h4>
								</div>
							</div>
						</div>
						<div class="vertical-product-body">
							<div class="d-flex flex-column mt-sm-3 mt-2">
								<FloatBar 
									:item="item" 
									:show-value="false" 
									:show-min-max="false" 
								/>
								<h4 class="vertical-product-title">{{ item.name }}</h4>
							</div>
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
		
		<!-- Модальное окно подтверждения покупки -->
		<div class="modal fade" id="confirmPurchaseModal" tabindex="-1" data-bs-backdrop="static">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Подтверждение покупки</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div class="text-center">
							<h4 class="mb-3">{{ caseData.name }}</h4>
							<p class="fs-5">Стоимость: <strong class="text-primary" v-html="formatPrice(caseData.price)"></strong></p>
							<p class="text-muted">Средства будут списаны с вашего баланса</p>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
						<button type="button" class="btn theme-btn" @click="confirmPurchase" :disabled="isProcessing">
							<span v-if="isProcessing" class="spinner-border spinner-border-sm me-2"></span>
							Подтвердить покупку
						</button>
					</div>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import { formatPrice } from '../utils/helpers';
import axios from 'axios';
import FloatBar from './FloatBar.vue';

// Animation constants
const ANIMATION_CONFIG = {
	ITEM_WIDTH: 200,
	ITEM_GAP: 20,
	MAX_SPEED: 40,
	MIN_SPEED: 3,
	DECELERATION_CARDS: 6,
	AUTO_SCROLL_SPEED: 2,
	ANIMATION_FRAME_RATE: 16,
	COMPLETION_DELAY: 200,
	OPACITY_ANIMATION_DURATION: '0.8s',
	FINAL_OPACITY: '0.05',
	MIN_WINNING_INDEX: 10,
	DISPLAY_ITEMS_COUNT: 50,
	MIN_SCROLL_CARDS: 25
};

export default {
	name: 'CaseDetails',
	
	components: {
		FloatBar
	},

	props: {
		initialCase: {
			type: Object,
			required: true
		},
		caseSlug: {
			type: String,
			required: true
		}
	},

	setup() {
		return { formatPrice };
	},

	data() {
		return {
			// Case data
			caseData: { ...this.initialCase },
			allItems: [],

			// Animation state
			isOpening: false,
			isProcessing: false,
			isSpinning: false,
			wonItem: null,

			// Slider state
			displayItems: [],
			sliderOffset: 0,
			animationInterval: null,

			// Configuration (can be overridden)
			itemWidth: ANIMATION_CONFIG.ITEM_WIDTH,
			itemGap: ANIMATION_CONFIG.ITEM_GAP
		};
	},
	methods: {
		async loadCaseDetails() {
			try {
				const response = await axios.get(`/api/cases/${this.caseSlug}`);
				this.caseData = response.data.data;
				this.processItems();
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
								structured_tags: item.structured_tags,
								float_value: item.float_value,
								float_min: item.float_min,
								float_max: item.float_max
							});
						});
					}
				});
			}

			this.allItems = this.shuffleArray([...items]);
			this.generateDisplayItems();
			this.startAutoScroll();
		},

		generateDisplayItems() {
			if (this.allItems.length === 0) return;

			const items = [];
			// Создаем длинную ленту из повторяющихся предметов для плавной прокрутки
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

		startAutoScroll() {
			if (this.animationInterval) {
				clearInterval(this.animationInterval);
			}

			// Плавная автопрокрутка когда не крутим рулетку
			this.animationInterval = setInterval(() => {
				if (!this.isSpinning) {
					this.sliderOffset -= ANIMATION_CONFIG.AUTO_SCROLL_SPEED;

					// Когда прокрутили один элемент, сбрасываем и переставляем элементы
					if (Math.abs(this.sliderOffset) >= (this.itemWidth + this.itemGap)) {
						this.sliderOffset = 0;
						this.displayItems.push(this.displayItems.shift());
					}
				}
			}, ANIMATION_CONFIG.ANIMATION_FRAME_RATE);
		},

		shuffleArray(array) {
			const shuffled = [...array];
			for (let i = shuffled.length - 1; i > 0; i--) {
				const j = Math.floor(Math.random() * (i + 1));
				[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
			}
			return shuffled;
		},

		async openCase() {
			this.isProcessing = true;

			// Тестовая версия - выбираем случайный предмет из доступных
			const testWinningItem = this.allItems[Math.floor(Math.random() * this.allItems.length)];

			// Запускаем анимацию с тестовым выигранным предметом
			this.isProcessing = false;
			this.startSliderAnimation(testWinningItem);

			/* TODO: Заменить на реальный API запрос
			try {
				const response = await axios.post(`/api/cases/${this.caseSlug}/open`);
				const winningItem = response.data.data;
				this.isProcessing = false;
				this.startSliderAnimation(winningItem);
			} catch (error) {
				this.isProcessing = false;
			}
			*/
		},

		startSliderAnimation(winningItem) {
			this.isSpinning = true;
			this.isOpening = true;

			// Останавливаем обычную прокрутку
			if (this.animationInterval) {
				clearInterval(this.animationInterval);
			}

			// Находим подходящую позицию для остановки на выигранном предмете
			const stopItemIndex = this.findWinningStopPosition(winningItem.id);

			// Получаем размеры контейнера для точного расчета
			const containerElement = this.$el?.querySelector('.case-roulette-container');
			const containerWidth = containerElement ? containerElement.offsetWidth : window.innerWidth;
			const containerCenter = containerWidth / 2;

			// Вычисляем финальную позицию выигранного предмета
			const trackElement = this.$el?.querySelector('.roulette-track');
			const firstCard = trackElement?.querySelector('.roulette-item');

			let realItemWidth = this.itemWidth;
			let realItemGap = this.itemGap;

			if (firstCard) {
				const style = window.getComputedStyle(firstCard);
				const marginLeft = parseInt(style.marginLeft) || 0;
				const marginRight = parseInt(style.marginRight) || 0;
				const elementWidth = firstCard.offsetWidth;

				realItemWidth = elementWidth;
				realItemGap = marginLeft + marginRight;
			}

			// Вычисляем финальную позицию
			const cardLeftEdge = stopItemIndex * (realItemWidth + realItemGap);
			const cardCenterInTrack = cardLeftEdge + (realItemGap / 2) + (realItemWidth / 2);
			const finalTargetOffset = containerCenter - cardCenterInTrack;

			// Расстояние которое нужно пройти
			const totalDistance = Math.abs(finalTargetOffset - this.sliderOffset);

			// Позиция за 5-6 карточек до финала (начинаем замедляться)
			const slowDownDistance = ANIMATION_CONFIG.DECELERATION_CARDS * (realItemWidth + realItemGap);
			const slowDownPoint = finalTargetOffset + slowDownDistance;

			let currentSpeed = ANIMATION_CONFIG.MAX_SPEED;
			const minSpeed = ANIMATION_CONFIG.MIN_SPEED;

			const spinInterval = setInterval(() => {
				// Движение только слева направо (уменьшаем offset)
				this.sliderOffset -= currentSpeed;

				// Проверяем, дошли ли до точки замедления
				if (this.sliderOffset <= slowDownPoint) {
					// Вычисляем оставшееся расстояние до цели
					const remainingDistance = Math.abs(this.sliderOffset - finalTargetOffset);
					const slowDownDistance = Math.abs(slowDownPoint - finalTargetOffset);

					if (remainingDistance > 0) {
						// Более плавное замедление с квадратичной кривой
						const progress = remainingDistance / slowDownDistance;
						const easedProgress = progress * progress; // Квадратичная кривая для плавности
						currentSpeed = Math.max(minSpeed, easedProgress * (ANIMATION_CONFIG.MAX_SPEED - minSpeed) + minSpeed);
					}
				}

				// Останавливаемся когда достигли цели
				if (this.sliderOffset <= finalTargetOffset) {
					clearInterval(spinInterval);
					this.sliderOffset = finalTargetOffset;

					// Завершаем анимацию
					setTimeout(() => {
						this.isSpinning = false;
						this.isOpening = false;

						// Устанавливаем выигранный предмет
						if (this.displayItems[stopItemIndex]) {
							this.wonItem = this.displayItems[stopItemIndex];
						}

						// Делаем все карточки прозрачными кроме выигранной
						const trackElement = this.$el?.querySelector('.roulette-track');
						const cardItems = trackElement?.querySelectorAll('.roulette-item');

						if (cardItems) {
							cardItems.forEach((card, index) => {
								if (index !== stopItemIndex) {
									card.style.transition = `opacity ${ANIMATION_CONFIG.OPACITY_ANIMATION_DURATION} ease`;
									card.style.opacity = ANIMATION_CONFIG.FINAL_OPACITY;
								}
							});
						}
					}, ANIMATION_CONFIG.COMPLETION_DELAY);
				}
			}, ANIMATION_CONFIG.ANIMATION_FRAME_RATE);
		},

		findWinningStopPosition(winningItemId) {
			// Вычисляем текущую позицию в пикселях от начала карусели
			const currentScrolled = Math.abs(this.sliderOffset);
			const itemFullWidth = this.itemWidth + this.itemGap;

			// Сколько карточек уже прокрутилось
			const scrolledCards = Math.floor(currentScrolled / itemFullWidth);

			// Минимальное количество карточек которые должны прокрутиться
			const minScrollCards = Math.max(ANIMATION_CONFIG.MIN_SCROLL_CARDS, scrolledCards + ANIMATION_CONFIG.MIN_SCROLL_CARDS);

			// Ищем ближайшую карточку с нужным ID после минимального количества прокрутки
			for (let i = minScrollCards; i < this.displayItems.length; i++) {
				if (this.displayItems[i].id === winningItemId) {
					return i;
				}
			}

			// Если не найдено, ищем в начале массива (карусель циклическая)
			for (let i = 0; i < this.displayItems.length; i++) {
				if (this.displayItems[i].id === winningItemId) {
					// Добавляем полный цикл + минимальная прокрутка
					return i + this.displayItems.length + ANIMATION_CONFIG.MIN_SCROLL_CARDS;
				}
			}

			// Если предмет не найден вообще - создаем позицию
			return this.createWinningPosition(winningItemId, minScrollCards);
		},

		createWinningPosition(winningItemId, minPosition) {
			// Находим оригинальный предмет в allItems
			const originalItem = this.allItems.find(item => item.id === winningItemId);

			if (originalItem) {
				// Создаем позицию для выигранного предмета
				const targetIndex = minPosition + Math.floor(Math.random() * 10); // +0-9 карточек для случайности

				// Убеждаемся что индекс не выходит за границы
				if (targetIndex < this.displayItems.length) {
					this.displayItems[targetIndex] = {
						...originalItem,
						uniqId: `winning-${targetIndex}`,
						isWinner: false
					};
					return targetIndex;
				} else {
					// Добавляем в конец массива
					this.displayItems.push({
						...originalItem,
						uniqId: `winning-end`,
						isWinner: false
					});
					return this.displayItems.length - 1;
				}
			}

			// Fallback - останавливаемся на случайной позиции после минимума
			return minPosition + Math.floor(Math.random() * 5);
		},

		async confirmPurchase() {
			this.isProcessing = true;
			
			try {
				// Закрываем модальное окно подтверждения
				const modal = bootstrap.Modal.getInstance(document.getElementById('confirmPurchaseModal'));
				if (modal) {
					modal.hide();
				}
				
				// Отправляем запрос на покупку кейса
				const response = await axios.post(`/api/cases/purchase`, {
					case_id: this.caseData.id
				});
				
				if (response.data.success) {
					// Получили приз - запускаем анимацию
					const winningItem = response.data.prize;
					this.isProcessing = false;
					this.startSliderAnimation(winningItem);
				}
			} catch (error) {
				this.isProcessing = false;
				// Ошибки будут обрабатываться централизованно через систему нотификаций
			}
		},
		
		reloadPage() {
			// Перезагружаем страницу для получения актуального состояния кейса
			window.location.reload();
		},

		getItemImageUrl(item) {
			if (!item.image_url) {
				return '/images/item-placeholder.png';
			}

			if (item.image_url.includes('steamcommunity-a.akamaihd.net') || item.image_url.includes('community.steamstatic.com')) {
				return item.image_url;
			}

			return `https://community.steamstatic.com/economy/image/${item.image_url}`;
		},

		handleImageError(event) {
			event.target.src = '/images/item-placeholder.png';
		},

		getTierName(tierId) {
			const tier = this.caseData.tiers?.find(t => t.id === tierId);
			return tier ? tier.name : 'Неизвестно';
		},

		handleCurrencyChange() {
			this.loadCaseDetails();
		},

		getItemPosition(index) {
			// Получаем размеры контейнера
			const containerElement = this.$el?.querySelector('.case-roulette-container');
			if (!containerElement) return null;

			const containerWidth = containerElement.offsetWidth;
			const containerCenter = containerWidth / 2;

			// Вычисляем области
			const halfItemWidth = this.itemWidth / 2;
			const quarterItemWidth = this.itemWidth / 4;

			// Область окошка (шире чем одна карточка)
			const windowStart = containerCenter - this.itemWidth;
			const windowEnd = containerCenter + this.itemWidth;

			// Центральная область (чуть расширенная)
			const centerZone = halfItemWidth * 0.5;
			const centerStart = containerCenter - centerZone;
			const centerEnd = containerCenter + centerZone;

			// Вычисляем позицию элемента
			const elementPosition = (index * (this.itemWidth + this.itemGap)) + this.sliderOffset;
			const elementCenter = elementPosition + halfItemWidth;

			// Определяем состояние
			if (elementCenter >= centerStart && elementCenter <= centerEnd) {
				return 'center';
			} else if (elementCenter >= windowStart && elementCenter <= windowEnd) {
				// Определяем prev или next
				return elementCenter < containerCenter ? 'prev' : 'next';
			}

			return null; // Обычное состояние (размыто)
		},

		getRarityClass(item) {
			if (!item || !item.structured_tags) {
				return '';
			}

			const rarityTag = item.structured_tags.find(tag => tag.category_code === 'rarity');
			if (rarityTag) {
				return `rarity-${rarityTag.normalized_value}`;
			}

			return '';
		}
	},

	mounted() {
		// Обрабатываем уже загруженные данные
		this.processItems();
		window.addEventListener('currency-changed', this.handleCurrencyChange);
	},

	beforeUnmount() {
		window.removeEventListener('currency-changed', this.handleCurrencyChange);

		// Очищаем интервал анимации
		if (this.animationInterval) {
			clearInterval(this.animationInterval);
		}
	}
}
</script>