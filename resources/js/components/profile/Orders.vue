<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Мои покупки</h3>
		</div>

		<!-- Loading state -->
		<div v-if="isLoading" class="text-center py-5">
			<div class="loader-gif">
				<div class="radar-ring"></div>
				<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
			</div>
			<p class="mt-3">Загружаем заказы...</p>
		</div>

		<!-- Orders list -->
		<div v-if="!isLoading && orders.length > 0" class="orders-list">
			<div v-for="order in orders" :key="order.id" class="order-item mb-4 card">
				<div class="card-header">
					<!-- Order Header -->
					<div class="d-flex align-items-center justify-content-between">
						<div class="d-flex align-items-center gap-3">
							<button 
								class="btn btn-link p-0 text-decoration-none fw-bold"
								data-bs-toggle="collapse" 
								:data-bs-target="`#order-${order.id}`" 
								:aria-expanded="false" 
								:aria-controls="`order-${order.id}`">
								<i class="ri-arrow-right-s-line me-1"></i>
								Заказ {{ order.order_number }}
							</button>
							<span class="badge" :class="getStatusBadgeClass(order.status)">
								{{ getStatusText(order.status) }}
							</span>
							<span v-if="order.reserved_until && order.status === 'processing'" class="badge bg-warning text-dark ms-2">
								⏰ Резерв: {{ getTimeRemaining(order.reserved_until) }}
							</span>
							<small class="text-muted ms-2">
								Продавец: <span class="fw-medium">{{ order.seller?.name || 'Не указан' }}</span>
							</small>
						</div>
						<div class="order-meta text-end">
							<div class="order-amount mb-1">
								<strong class="fs-5 text-primary">{{ formatPrice(order.total_amount) }} ₽</strong>
							</div>
							<small class="text-muted">{{ formatDate(order.created_at) }}</small>
						</div>
					</div>
				</div>

				<!-- Collapsible Order Items -->
				<div :id="`order-${order.id}`" class="collapse">
					<div class="card-body">
						<!-- Notes section at the top of collapse -->
						<div v-if="order.notes || order.system_remarks" class="notes-section mb-3">
							<div v-if="order.system_remarks" class="bg-light text-muted p-2 mb-2 rounded">
								<small>{{ order.system_remarks }}</small>
							</div>
							<div v-if="order.notes" class="p-2">
								<p><strong><small>Примечание:</small></strong></p>
								<p><small>{{ order.notes }}</small></p>
							</div>
						</div>
						<div v-if="order.cart_snapshot && order.cart_snapshot.length > 0" class="order-items">
							<div v-for="item in order.cart_snapshot" :key="item.listing_id" class="item-card d-flex align-items-center p-3 border rounded mb-2">
								<!-- Item Image -->
								<div class="item-image me-3">
									<img :src="item.item.image_url" 
										 :alt="item.item.name" 
										 class="img-fluid rounded"
										 style="width: 60px; height: 60px; object-fit: cover;">
								</div>

								<!-- Item Details -->
								<div class="item-details flex-grow-1">
									<h6 class="mb-1">{{ item.item.name }}</h6>
								</div>

								<!-- Item Price -->
								<div class="item-price text-end">
									<strong class="fs-6 text-success">{{ formatPrice(item.price) }} ₽</strong>
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>

			<!-- Pagination -->
			<div v-if="pagination && pagination.last_page > 1" class="d-flex justify-content-center mt-4">
				<nav>
					<ul class="pagination">
						<li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
							<button class="page-link" @click="loadOrders(1)" :disabled="pagination.current_page === 1">
								Первая
							</button>
						</li>
						<li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
							<button class="page-link" @click="loadOrders(pagination.current_page - 1)" 
							        :disabled="pagination.current_page === 1">
								Предыдущая
							</button>
						</li>
						
						<li v-for="page in getVisiblePages()" :key="page" class="page-item" 
						    :class="{ active: page === pagination.current_page }">
							<button class="page-link" @click="loadOrders(page)">{{ page }}</button>
						</li>
						
						<li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
							<button class="page-link" @click="loadOrders(pagination.current_page + 1)" 
							        :disabled="pagination.current_page === pagination.last_page">
								Следующая
							</button>
						</li>
						<li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
							<button class="page-link" @click="loadOrders(pagination.last_page)" 
							        :disabled="pagination.current_page === pagination.last_page">
								Последняя
							</button>
						</li>
					</ul>
				</nav>
			</div>
		</div>

		<!-- Empty state -->
		<div v-else-if="!isLoading" class="text-center py-5">
			<div class="empty-state-icon mb-3">
				<i class="ri-shopping-cart-line" style="font-size: 4rem; color: #ccc;"></i>
			</div>
			<h4>У вас пока нет заказов</h4>
			<p class="text-muted mb-4">Начните делать покупки в нашем маркетплейсе</p>
			<a href="/marketplace" class="btn theme-btn">
				<i class="ri-store-line me-1"></i>Перейти в маркетплейс
			</a>
		</div>

	</div>
</template>

<script>
import { formatPrice, formatDate, handleApiError, getTimeRemaining } from '../../utils/helpers';
import { orderAPI } from '../../utils/api';

export default {
	name: 'ProfileOrders',
	props: {
		client: {
			type: Object,
			required: true
		}
	},
	data() {
		return {
			orders: [],
			pagination: null,
			isLoading: false,
			timerInterval: null,
			statusUpdateInterval: null
		}
	},
	async mounted() {
		await this.loadOrders();
		this.startTimer();
		this.startStatusUpdates();
	},
	beforeUnmount() {
		this.stopTimer();
		this.stopStatusUpdates();
	},
	methods: {
		formatPrice,
		formatDate,
		getTimeRemaining,

		async loadOrders(page = 1) {
			this.isLoading = true;
			try {
				const response = await orderAPI.getMyOrders(page);
				if (response.success) {
					this.orders = response.data.data;
					this.pagination = {
						current_page: response.data.current_page,
						last_page: response.data.last_page,
						total: response.data.total
					};
				}
			} catch (error) {
				console.error('Error loading orders:', error);
				window.toast.error('Ошибка при загрузке заказов');
			} finally {
				this.isLoading = false;
			}
		},

		getStatusBadgeClass(status) {
			const classes = {
				'paid': 'bg-warning',
				'processing': 'bg-info',
				'reserved': 'bg-warning',
				'trade_sent': 'bg-info',
				'completed': 'bg-success',
				'cancelled': 'bg-danger',
				'failed': 'bg-danger',
				'refunded': 'bg-secondary'
			};
			return classes[status] || 'bg-secondary';
		},

		getStatusText(status) {
			const texts = {
				'paid': 'Оплачен',
				'processing': 'В обработке', 
				'completed': 'Завершен',
				'cancelled': 'Отменен',
				'failed': 'Ошибка',
				'refunded': 'Возврат'
			};
			return texts[status] || status;
		},

		getItemStatusBadgeClass(status) {
			const classes = {
				'reserved': 'bg-warning',
				'trade_sent': 'bg-info',
				'completed': 'bg-success',
				'cancelled': 'bg-danger'
			};
			return classes[status] || 'bg-secondary';
		},

		getItemStatusText(status) {
			const texts = {
				'reserved': 'Резерв',
				'trade_sent': 'Трейд отправлен',
				'completed': 'Получен',
				'cancelled': 'Отменен'
			};
			return texts[status] || status;
		},


		getVisiblePages() {
			if (!this.pagination) return [];
			
			const current = this.pagination.current_page;
			const last = this.pagination.last_page;
			const pages = [];
			
			// Показываем до 5 страниц
			let start = Math.max(1, current - 2);
			let end = Math.min(last, current + 2);
			
			// Корректируем если нужно показать 5 страниц
			if (end - start < 4) {
				if (start === 1) {
					end = Math.min(last, start + 4);
				} else {
					start = Math.max(1, end - 4);
				}
			}
			
			for (let i = start; i <= end; i++) {
				pages.push(i);
			}
			
			return pages;
		},

		startTimer() {
			this.timerInterval = setInterval(() => {
				this.$forceUpdate(); // Принудительно обновляем компонент для обновления таймеров
			}, 1000);
		},

		stopTimer() {
			if (this.timerInterval) {
				clearInterval(this.timerInterval);
				this.timerInterval = null;
			}
		},

		startStatusUpdates() {
			// Обновляем статусы каждые 10 секунд
			this.statusUpdateInterval = setInterval(async () => {
				// Проверяем, есть ли заказы в обработке
				const hasActiveOrders = this.orders.some(order => 
					['paid', 'processing', 'reserved'].includes(order.status)
				);
				
				if (hasActiveOrders || this.orders.length > 0) {
					try {
						// Тихо обновляем данные без показа лоадера
						const response = await orderAPI.getMyOrders(this.pagination?.current_page || 1);
						if (response.success) {
							this.orders = response.data.data;
							this.pagination = {
								current_page: response.data.current_page,
								last_page: response.data.last_page,
								total: response.data.total
							};
						}
					} catch (error) {
						console.error('Background status update failed:', error);
					}
				}
			}, 10000); // 10 секунд
		},

		stopStatusUpdates() {
			if (this.statusUpdateInterval) {
				clearInterval(this.statusUpdateInterval);
				this.statusUpdateInterval = null;
			}
		}
	}
}
</script>