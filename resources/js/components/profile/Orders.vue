<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Мои заказы</h3>
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
		<div v-else-if="orders.length > 0" class="orders-list">
			<div v-for="order in orders" :key="order.id" class="order-item mb-4 p-3 border rounded">
				<div class="row align-items-center">
					<div class="col-md-8">
						<div class="order-header d-flex align-items-center gap-3 mb-2">
							<h5 class="mb-0">{{ order.order_number }}</h5>
							<span class="badge" :class="getStatusBadgeClass(order.status)">
								{{ getStatusText(order.status) }}
							</span>
							<span class="badge" :class="getPaymentStatusBadgeClass(order.payment_status)">
								{{ getPaymentStatusText(order.payment_status) }}
							</span>
						</div>
						
						<div class="order-details">
							<p class="text-muted mb-1">
								<i class="ri-calendar-line me-1"></i>
								{{ formatDate(order.created_at) }}
							</p>
							<p class="text-muted mb-1" v-if="order.paid_at">
								<i class="ri-money-dollar-circle-line me-1"></i>
								Оплачен: {{ formatDate(order.paid_at) }}
							</p>
							<p class="mb-0" v-if="order.notes">
								<i class="ri-message-3-line me-1"></i>
								{{ order.notes }}
							</p>
						</div>
					</div>
					
					<div class="col-md-4 text-end">
						<div class="order-amount mb-2">
							<strong class="fs-5 text-primary">{{ formatPrice(order.total_amount) }} ₽</strong>
						</div>
						<div class="order-actions">
							<button class="btn btn-sm theme-outline" @click="viewOrderDetails(order)">
								<i class="ri-eye-line me-1"></i>Подробности
							</button>
						</div>
					</div>
				</div>

				<!-- Order items preview -->
				<div v-if="order.cart_snapshot && order.cart_snapshot.length > 0" class="order-items mt-3 pt-3 border-top">
					<h6 class="mb-2">Товары ({{ order.cart_snapshot.length }})</h6>
					<div class="row g-2">
						<div v-for="(item, index) in order.cart_snapshot.slice(0, 4)" :key="index" class="col-md-3">
							<div class="item-preview d-flex align-items-center gap-2 p-2 bg-light rounded">
								<img :src="item.item?.image_url || '/images/skin_no_image.svg'" 
								     :alt="item.item?.name" 
								     class="img-fluid rounded" 
								     style="width: 40px; height: 30px; object-fit: cover;">
								<div class="item-info">
									<small class="d-block fw-medium">{{ item.item?.name || 'Unknown' }}</small>
									<small class="text-muted">{{ formatPrice(item.price) }} ₽</small>
								</div>
							</div>
						</div>
						<div v-if="order.cart_snapshot.length > 4" class="col-md-3">
							<div class="more-items d-flex align-items-center justify-content-center p-2 bg-light rounded text-center">
								<small class="text-muted">+{{ order.cart_snapshot.length - 4 }} еще</small>
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
		<div v-else class="text-center py-5">
			<i class="ri-shopping-cart-line display-4 text-muted mb-3"></i>
			<h4>У вас пока нет заказов</h4>
			<p class="text-muted mb-4">Начните делать покупки в нашем маркетплейсе</p>
			<a href="/marketplace" class="btn theme-btn">
				<i class="ri-store-line me-1"></i>Перейти в маркетплейс
			</a>
		</div>

		<!-- Order Details Modal -->
		<div v-if="selectedOrder" class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
			<div class="modal-dialog modal-lg modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">
							<i class="ri-file-list-3-line me-2"></i>
							Заказ {{ selectedOrder.order_number }}
						</h5>
						<button type="button" class="btn-close" @click="selectedOrder = null"></button>
					</div>
					<div class="modal-body">
						<div class="row">
							<div class="col-md-6">
								<h6>Информация о заказе</h6>
								<table class="table table-sm">
									<tr>
										<td><strong>Номер заказа:</strong></td>
										<td>{{ selectedOrder.order_number }}</td>
									</tr>
									<tr>
										<td><strong>Статус:</strong></td>
										<td>
											<span class="badge" :class="getStatusBadgeClass(selectedOrder.status)">
												{{ getStatusText(selectedOrder.status) }}
											</span>
										</td>
									</tr>
									<tr>
										<td><strong>Оплата:</strong></td>
										<td>
											<span class="badge" :class="getPaymentStatusBadgeClass(selectedOrder.payment_status)">
												{{ getPaymentStatusText(selectedOrder.payment_status) }}
											</span>
										</td>
									</tr>
									<tr>
										<td><strong>Создан:</strong></td>
										<td>{{ formatDate(selectedOrder.created_at) }}</td>
									</tr>
									<tr v-if="selectedOrder.paid_at">
										<td><strong>Оплачен:</strong></td>
										<td>{{ formatDate(selectedOrder.paid_at) }}</td>
									</tr>
									<tr>
										<td><strong>Сумма:</strong></td>
										<td><strong class="text-primary">{{ formatPrice(selectedOrder.total_amount) }} ₽</strong></td>
									</tr>
								</table>
							</div>
							<div class="col-md-6">
								<h6>Способ оплаты</h6>
								<p class="text-muted">{{ selectedOrder.payment_method || 'Не указан' }}</p>
								
								<h6 v-if="selectedOrder.notes">Комментарий</h6>
								<p v-if="selectedOrder.notes" class="text-muted">{{ selectedOrder.notes }}</p>
							</div>
						</div>
						
						<div class="mt-4" v-if="selectedOrder.cart_snapshot && selectedOrder.cart_snapshot.length > 0">
							<h6>Товары в заказе</h6>
							<div class="table-responsive">
								<table class="table table-sm">
									<thead>
										<tr>
											<th>Товар</th>
											<th>Продавец</th>
											<th class="text-end">Цена</th>
										</tr>
									</thead>
									<tbody>
										<tr v-for="(item, index) in selectedOrder.cart_snapshot" :key="index">
											<td>
												<div class="d-flex align-items-center gap-2">
													<img :src="item.item?.image_url || '/images/skin_no_image.svg'" 
													     :alt="item.item?.name" 
													     class="img-fluid rounded" 
													     style="width: 30px; height: 22px; object-fit: cover;">
													<div>
														<div class="fw-medium">{{ item.item?.name || 'Unknown' }}</div>
														<div class="small text-muted">
															<span v-if="item.wear_name" class="me-1">{{ item.wear_name }}</span>
															<span v-if="item.is_stattrak" class="badge bg-warning me-1">ST</span>
															<span v-if="item.is_souvenir" class="badge bg-info">SV</span>
														</div>
													</div>
												</div>
											</td>
											<td>{{ item.seller?.name || 'Unknown' }}</td>
											<td class="text-end">{{ formatPrice(item.price) }} ₽</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn theme-outline" @click="selectedOrder = null">
							Закрыть
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { formatPrice, formatDate, handleApiError } from '../../utils/helpers';
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
			selectedOrder: null
		}
	},
	async mounted() {
		await this.loadOrders();
	},
	methods: {
		formatPrice,
		formatDate,

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
				'pending': 'bg-warning',
				'paid': 'bg-info',
				'processing': 'bg-primary',
				'completed': 'bg-success',
				'cancelled': 'bg-secondary',
				'refunded': 'bg-danger'
			};
			return classes[status] || 'bg-secondary';
		},

		getStatusText(status) {
			const texts = {
				'pending': 'Ожидает оплаты',
				'paid': 'Оплачен',
				'processing': 'В обработке',
				'completed': 'Завершен',
				'cancelled': 'Отменен',
				'refunded': 'Возврат'
			};
			return texts[status] || status;
		},

		getPaymentStatusBadgeClass(paymentStatus) {
			const classes = {
				'pending': 'bg-warning',
				'paid': 'bg-success',
				'failed': 'bg-danger',
				'refunded': 'bg-info'
			};
			return classes[paymentStatus] || 'bg-secondary';
		},

		getPaymentStatusText(paymentStatus) {
			const texts = {
				'pending': 'Ожидает оплаты',
				'paid': 'Оплачено',
				'failed': 'Ошибка оплаты',
				'refunded': 'Возврат'
			};
			return texts[paymentStatus] || paymentStatus;
		},

		viewOrderDetails(order) {
			this.selectedOrder = order;
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
		}
	}
}
</script>