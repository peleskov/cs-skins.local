<template>
	<div v-if="auction && auction.status === 'active'" class="auction-section my-4 p-4 border rounded-4">
		<div class="d-flex align-items-center justify-content-between mb-3">
			<h5 class="mb-0">
				<i class="ri-auction-line me-2 text-primary"></i>Активный аукцион
			</h5>
		</div>

		<!-- Информация об аукционе -->
		<div class="auction-info mb-4">
			<div class="row g-3">
				<div class="col-6 col-md-3">
					<small class="text-muted d-block">Текущая цена</small>
					<strong class="h6 mb-0" v-html="formatPrice(auction.current_price || 0)"></strong>
				</div>
				<div class="col-6 col-md-3">
					<small class="text-muted d-block">Минимальная ставка</small>
					<strong class="h6 mb-0" v-html="formatPrice(getMinimumBid())"></strong>
				</div>
				<div class="col-6 col-md-3">
					<small class="text-muted d-block">Количество ставок</small>
					<strong class="h6 mb-0">{{ auction.bid_count }}</strong>
				</div>
				<div class="col-6 col-md-3">
					<small class="text-muted d-block">До окончания</small>
					<strong class="h6 mb-0" :class="getTimeLeftClass()">{{ getTimeLeft() }}</strong>
				</div>
			</div>
		</div>

		<!-- Форма для ставки -->
		<div v-if="canMakeBid()" class="bid-form mb-3">
			<div class="row g-2">
				<div class="col">
					<div class="input-group">
						<span class="input-group-text">₽</span>
						<input 
							type="number" 
							class="form-control" 
							v-model="bidAmount" 
							:min="getMinimumBid()"
							:step="auction.min_bid_increment"
							:placeholder="`От ${formatPrice(getMinimumBid(), 'RUB', true)} ₽`"
							:disabled="placingBid">
					</div>
				</div>
				<div class="col-auto">
					<button 
						class="btn theme-btn"
						@click="placeBid"
						:disabled="!isValidBid() || placingBid">
						<i class="ri-hammer-line me-1"></i>
						{{ placingBid ? 'Размещение...' : 'Сделать ставку' }}
					</button>
				</div>
			</div>
			<small v-if="bidAmount && !isValidBid()" class="text-danger">
				Минимальная ставка: <span v-html="formatPrice(getMinimumBid())"></span>
			</small>
		</div>

		<!-- Сообщение если нельзя делать ставку -->
		<div v-else class="alert alert-info mb-3">
			<i class="ri-information-line me-2"></i>
			{{ getBidRestrictionMessage() }}
		</div>

		<!-- Последние ставки -->
		<div v-if="recentBids.length > 0" class="recent-bids">
			<p class="small text-muted mb-2">Последние ставки</p>
			<div class="bid-history" style="max-height: 200px; overflow-y: auto;">
				<div v-for="bid in recentBids" :key="bid.id" class="d-flex justify-content-between align-items-center py-2 border-bottom">
					<div>
						<small>{{ bid.bidder.name }}</small>
						<span class="badge bid-time">{{ formatDate(bid.placed_at) }}</span>
					</div>
					<small v-html="formatPrice(bid.amount)"></small>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { formatPrice } from '../../shared/utils/helpers';
import axios from 'axios';
import { createEcho } from '../../shared/echo';

export default {
	name: 'AuctionDetails',
	setup() {
		return { formatPrice };
	},
	props: {
		listingId: {
			type: [Number, String],
			required: true
		}
	},
	data() {
		return {
			auction: null,
			loading: false,
			bidAmount: '',
			placingBid: false,
			recentBids: [],
			timeLeft: null,
			countdownInterval: null,
			currentUser: null,
			echo: null,
			channel: null
		};
	},
	async mounted() {
		await this.loadAuctionData();
		this.startCountdown();
		this.initializeWebSocket();
		this.emitAuctionState();
	},
	beforeUnmount() {
		if (this.countdownInterval) {
			clearInterval(this.countdownInterval);
		}
		if (this.channel && this.echo) {
			this.echo.leaveChannel('auctions.all');
		}
	},
	methods: {
		async loadAuctionData() {
			this.loading = true;
			try {
				// Ищем аукцион по listing_id
				const response = await axios.get(`/api/auctions?listing_id=${this.listingId}`);
				if (response.data.data && response.data.data.length > 0) {
					this.auction = response.data.data[0];
					// Устанавливаем минимальную ставку по умолчанию
					this.bidAmount = this.getMinimumBid();
					await this.loadRecentBids();
				}
			} catch (error) {
				console.error('Error loading auction data:', error);
			} finally {
				this.loading = false;
			}
		},

		async loadRecentBids() {
			if (!this.auction) return;
			
			try {
				const response = await axios.get(`/api/auctions/${this.auction.id}/bids`);
				this.recentBids = response.data.data || [];
			} catch (error) {
				console.error('Error loading bids:', error);
			}
		},

		async loadCurrentUser() {
			try {
				// Пробуем получить из глобальной переменной
				if (window.currentUser) {
					this.currentUser = window.currentUser;
					return;
				}
				
				// Если нет, то проверяем через API
				const response = await axios.get('/api/user');
				this.currentUser = response.data;
			} catch (error) {
				// Пользователь не авторизован
				this.currentUser = null;
			}
		},

		async placeBid() {
			if (!this.isValidBid() || this.placingBid) return;

			this.placingBid = true;
			try {
				const response = await axios.post(`/api/auctions/${this.auction.id}/bid`, {
					amount: parseFloat(this.bidAmount)
				});

				if (response.data.success) {
					// Обновляем данные аукциона
					this.auction = response.data.auction;
					await this.loadRecentBids();
					// Устанавливаем новую минимальную ставку
					this.bidAmount = this.getMinimumBid();
					window.toast.success('Ставка успешно размещена!');
				}
			} catch (error) {
				// Обрабатываем ошибки авторизации как в quickBuy
				if (error.response?.status === 401) {
					setTimeout(() => {
						window.location.href = '/auth/steam';
					}, 2000);
					return;
				}
			} finally {
				this.placingBid = false;
			}
		},

		startCountdown() {
			if (!this.auction || !this.auction.ends_at) return;

			this.updateTimeLeft();
			this.countdownInterval = setInterval(() => {
				this.updateTimeLeft();
				if (this.timeLeft && this.timeLeft <= 0) {
					clearInterval(this.countdownInterval);
					this.loadAuctionData(); // Перезагружаем данные когда аукцион закончился
				}
			}, 1000);
		},

		updateTimeLeft() {
			if (!this.auction || !this.auction.ends_at) {
				this.timeLeft = null;
				return;
			}

			const now = new Date().getTime();
			const endTime = new Date(this.auction.ends_at).getTime();
			this.timeLeft = Math.max(0, endTime - now);

			// Уведомляем родительский компонент об изменении времени
			this.emitAuctionState();
		},

		getTimeLeft() {
			if (!this.timeLeft) return 'Завершен';
			
			const totalSeconds = Math.floor(this.timeLeft / 1000);
			const days = Math.floor(totalSeconds / 86400);
			const hours = Math.floor((totalSeconds % 86400) / 3600);
			const minutes = Math.floor((totalSeconds % 3600) / 60);
			const seconds = totalSeconds % 60;

			if (days > 0) {
				return `${days}д ${hours}ч ${minutes}м ${seconds}с`;
			} else if (hours > 0) {
				return `${hours}ч ${minutes}м ${seconds}с`;
			} else if (minutes > 0) {
				return `${minutes}м ${seconds}с`;
			} else {
				return `${seconds}с`;
			}
		},

		getTimeLeftClass() {
			if (!this.timeLeft) return 'text-muted';
			
			const totalSeconds = Math.floor(this.timeLeft / 1000);
			if (totalSeconds < 300) return 'text-danger'; // Менее 5 минут
			if (totalSeconds < 3600) return 'text-warning'; // Менее часа
			return 'text-success';
		},

		getMinimumBid() {
			if (!this.auction) return 0;
			const currentPrice = parseFloat(this.auction.current_price) || 0;
			const minIncrement = parseFloat(this.auction.min_bid_increment) || 1;
			return currentPrice + minIncrement;
		},

		isValidBid() {
			const amount = parseFloat(this.bidAmount);
			return !isNaN(amount) && amount >= this.getMinimumBid();
		},

		canMakeBid() {
			if (!this.auction) return false;
			if (this.timeLeft <= 0) return false;
			
			// Проверяем авторизацию пользователя
			const userElement = document.querySelector('#header-app');
			const userData = userElement?.dataset?.user;
			if (!userData || userData === 'null') return false;
			
			// Проверяем, не является ли пользователь владельцем аукциона
			try {
				const user = JSON.parse(userData);
				if (user.id && this.auction.seller_id === user.id) {
					return false; // Владелец не может делать ставки на свой аукцион
				}
			} catch (e) {
				// Если не удалось распарсить, просто проверяем авторизацию
			}
			
			return true;
		},

		getBidRestrictionMessage() {
			if (!this.auction) return '';
			if (this.timeLeft <= 0) return 'Аукцион завершен';
			
			// Проверяем авторизацию
			const userElement = document.querySelector('#header-app');
			const userData = userElement?.dataset?.user;
			if (!userData || userData === 'null') {
				return 'Авторизуйтесь чтобы участвовать в аукционе';
			}
			
			// Проверяем, не является ли пользователь владельцем
			try {
				const user = JSON.parse(userData);
				if (user.id && this.auction.seller_id === user.id) {
					return 'Это ваш аукцион';
				}
			} catch (e) {
				// Игнорируем ошибку парсинга
			}
			
			return '';
		},

		getStatusText() {
			if (!this.auction) return '';
			if (this.timeLeft <= 0) return 'Завершен';
			return 'Активен';
		},

		formatDate(dateString) {
			return new Date(dateString).toLocaleDateString('ru-RU', {
				day: '2-digit',
				month: '2-digit',
				hour: '2-digit',
				minute: '2-digit'
			});
		},

		emitAuctionState() {
			if (this.auction && this.auction.status === 'active') {
				// Используем duration_hours аукциона, конвертируем в миллисекунды
				const totalDuration = (this.auction.duration_hours || 1) * 60 * 60 * 1000; // часы в мс
				const auctionData = {
					isActive: true,
					currentPrice: parseFloat(this.auction.current_price) || 0,
					timeLeft: this.timeLeft || 0,
					totalDuration: totalDuration
				};
				this.$emit('auction-updated', auctionData);
			} else {
				this.$emit('auction-updated', { isActive: false });
			}
		},

		initializeWebSocket() {
			if (!this.auction) return;

			// Создаем экземпляр Echo
			this.echo = createEcho();

			// Подписываемся на единый канал всех аукционов
			this.channel = this.echo.channel('auctions.all')
				.listen('.bid.placed', (e) => {
					// Проверяем, что событие относится к нашему аукциону
					if (e.auction.id !== this.auction.id) {
						return;
					}
					
					// Обновляем данные аукциона
					if (e.auction) {
						this.auction.current_price = e.auction.current_price;
						this.auction.bid_count = e.auction.bid_count;
						this.auction.last_bidder_id = e.auction.last_bidder_id;
						if (e.auction.ends_at) {
							this.auction.ends_at = e.auction.ends_at;
						}
					}
					
					// Добавляем новую ставку в начало списка
					if (e.bid) {
						this.recentBids.unshift(e.bid);
						// Ограничиваем список 10 последними ставками
						if (this.recentBids.length > 10) {
							this.recentBids.pop();
						}
					}
					
					// Обновляем минимальную ставку
					this.bidAmount = this.getMinimumBid();

					// Уведомляем родительский компонент об изменении аукциона
					this.emitAuctionState();

					// Показываем уведомление если ставка не наша
					if (this.currentUser && e.bid && e.bid.bidder.id !== this.currentUser.id) {
						window.toast.success(`${e.bid.bidder.name} сделал ставку ${this.formatPrice(e.bid.amount)}`);
					}
				});
			
			console.log(`Subscribed to auction.${this.auction.id} channel via Echo`);
		}
	}
};
</script>