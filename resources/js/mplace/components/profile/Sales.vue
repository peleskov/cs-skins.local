<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Продажи</h3>
		</div>

		<!-- Tabs Navigation -->
		<ul class="nav nav-tabs tab-style1 mb-4" id="ordersTab" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: activeTab === 'current' }" id="current-tab" data-bs-toggle="tab"
					data-bs-target="#current" type="button" role="tab">
					Текущие
					<span v-if="counts.current > 0" class="badge bg-body-secondary ms-1">{{ counts.current }}</span>
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: activeTab === 'completed' }" id="completed-tab"
					data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
					Успешные
					<span v-if="counts.completed > 0" class="badge bg-body-secondary ms-1">{{ counts.completed }}</span>
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: activeTab === 'cancelled' }" id="cancelled-tab"
					data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab">
					Отмененные
					<span v-if="counts.cancelled > 0" class="badge bg-body-secondary ms-1">{{ counts.cancelled }}</span>
				</button>
			</li>
		</ul>

		<!-- Loading state -->
		<div v-if="isLoading" class="text-center py-5">
			<div class="loader-gif">
				<div class="radar-ring"></div>
				<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
			</div>
			<p class="mt-3">Загружаем продажи...</p>
		</div>

		<div class="tab-content" id="ordersTabContent">
			<!-- Текущие заказы -->
			<div class="tab-pane fade show active" id="current" role="tabpanel" aria-labelledby="current-tab" tabindex="0">
				<!-- Orders list -->
				<div v-if="!isLoading && orders.length > 0" class="orders-list">
					<OrderItem
						v-for="order in orders"
						:key="order.id"
						:order="order"
						user-type="buyer"
						:show-cancel-button="canCancelOrder(order)"
						@cancel-order="cancelOrder"
					/>
				</div>
				<!-- Empty state -->
				<div v-else-if="!isLoading" class="text-center py-5">
					<div class="empty-state-icon mb-3">
						<i class="ri-shopping-cart-line" style="font-size: 4rem; color: #ccc;"></i>
					</div>
					<h4>{{ getEmptyStateText() }}</h4>
					<p class="text-muted mb-4">{{ getEmptyStateSubtext() }}</p>
					<a href="/marketplace" class="btn theme-btn">
						<i class="ri-store-line me-1"></i>Перейти в маркетплейс
					</a>
				</div>
			</div>

			<!-- Успешные заказы -->
			<div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab" tabindex="0">
				<!-- Orders list -->
				<div v-if="!isLoading && orders.length > 0" class="orders-list">
					<OrderItem
						v-for="order in orders"
						:key="order.id"
						:order="order"
						user-type="buyer"
						:show-cancel-button="canCancelOrder(order)"
						@cancel-order="cancelOrder"
					/>
				</div>
				<!-- Empty state -->
				<div v-else-if="!isLoading" class="text-center py-5">
					<div class="empty-state-icon mb-3">
						<i class="ri-shopping-cart-line" style="font-size: 4rem; color: #ccc;"></i>
					</div>
					<h4>{{ getEmptyStateText() }}</h4>
					<p class="text-muted mb-4">{{ getEmptyStateSubtext() }}</p>
					<a href="/marketplace" class="btn theme-btn">
						<i class="ri-store-line me-1"></i>Перейти в маркетплейс
					</a>
				</div>
			</div>

			<!-- Отмененные заказы -->
			<div class="tab-pane fade" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab" tabindex="0">
				<!-- Orders list -->
				<div v-if="!isLoading && orders.length > 0" class="orders-list">
					<OrderItem
						v-for="order in orders"
						:key="order.id"
						:order="order"
						user-type="buyer"
						:show-cancel-button="canCancelOrder(order)"
						@cancel-order="cancelOrder"
					/>
				</div>
				<!-- Empty state -->
				<div v-else-if="!isLoading" class="text-center py-5">
					<div class="empty-state-icon mb-3">
						<i class="ri-shopping-cart-line" style="font-size: 4rem; color: #ccc;"></i>
					</div>
					<h4>{{ getEmptyStateText() }}</h4>
					<p class="text-muted mb-4">{{ getEmptyStateSubtext() }}</p>
					<a href="/marketplace" class="btn theme-btn">
						<i class="ri-store-line me-1"></i>Перейти в маркетплейс
					</a>
				</div>
			</div>
		</div>

		<!-- Модальное окно подтверждения отмены заказа -->
		<div class="modal fade" id="confirmCancelModal" tabindex="-1" aria-labelledby="confirmCancelModalLabel"
			aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="confirmCancelModalLabel">
							<i class="ri-close-circle-line me-2 text-danger"></i>Отменить заказ
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div v-if="orderToCancel" class="mb-3">
							<div class="d-flex align-items-center justify-content-between">
								<div>
									<h6 class="mb-1">Заказ {{ orderToCancel.order_number }}</h6>
									<small class="text-muted">Продавец: {{ orderToCancel.seller?.name || 'Не указан'
									}}</small>
								</div>
								<div class="text-end">
									<strong class="text-primary" v-html="formatPrice(orderToCancel.total_amount)"></strong>
								</div>
							</div>
						</div>
						<p>Вы уверены, что хотите отменить этот заказ? Средства будут возвращены на ваш баланс.</p>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
						<button type="button" class="btn btn-danger" @click="confirmCancelOrder">
							<i class="ri-close-circle-line me-1"></i>Отменить заказ
						</button>
					</div>
				</div>
			</div>
		</div>

	</div>
</template>

<script>
import { formatPrice, handleApiError } from '../../../shared/utils/helpers';
import { orderAPI } from '../../../shared/utils/api';
import OrderItem from './OrderItem.vue';

// Константы для интервалов обновления
const UPDATE_INTERVAL = 10000; // 10 секунд для обновления статусов

export default {
	name: 'ProfileSales',
	components: {
		OrderItem
	},
	props: {
		client: {
			type: Object,
			required: true
		}
	},
	data() {
		return {
			orders: [],
			allOrders: [], // все заказы для фильтрации
			pagination: null,
			isLoading: false,
			activeTab: 'current',
			counts: {
				current: 0,
				completed: 0,
				cancelled: 0
			},
			statusUpdateInterval: null,
			cancellingOrderId: null,
			orderToCancel: null
		}
	},
	async mounted() {
		await this.loadOrders();
		this.startStatusUpdates();
		
		// Добавляем обработчик событий Bootstrap табов
		this.$nextTick(() => {
			const tabElements = document.querySelectorAll('#ordersTab button[data-bs-toggle="tab"]');
			tabElements.forEach(tab => {
				tab.addEventListener('shown.bs.tab', (event) => {
					const tabId = event.target.getAttribute('data-bs-target')?.substring(1);
					if (tabId) {
						this.activeTab = tabId;
						this.orders = this.filterOrdersByTab(this.allOrders || []);
					}
				});
			});
		});
	},
	beforeUnmount() {
		this.stopStatusUpdates();
	},
	methods: {
		formatPrice,

		async loadOrders(page = 1) {
			this.isLoading = true;
			try {
				const response = await orderAPI.getMySales(page);
				if (response.success) {
					this.allOrders = response.data.data;
					
					// Фильтруем заказы по активному табу
					this.orders = this.filterOrdersByTab(this.allOrders);
					
					this.pagination = {
						current_page: response.data.current_page,
						last_page: response.data.last_page,
						total: response.data.total
					};
					
					// Подсчитываем количество заказов по статусам
					this.updateCounts(this.allOrders);
				}
			} catch (error) {
				console.error('Error loading orders:', error);
				window.toast.error('Ошибка при загрузке заказов');
			} finally {
				this.isLoading = false;
			}
		},


		startStatusUpdates() {
			this.statusUpdateInterval = setInterval(() => {
				this.updateOrdersInBackground();
			}, UPDATE_INTERVAL);
		},

		filterOrdersByTab(orders) {
			let filtered;
			switch (this.activeTab) {
				case 'current':
					filtered = orders.filter(order => ['paid', 'processing'].includes(order.status));
					break;
				case 'completed':
					filtered = orders.filter(order => order.status === 'completed');
					break;
				case 'cancelled':
					filtered = orders.filter(order => order.status === 'cancelled');
					break;
				default:
					filtered = orders;
			}
			return filtered;
		},

		updateCounts(orders) {
			const current = orders.filter(order => ['paid', 'processing'].includes(order.status));
			const completed = orders.filter(order => order.status === 'completed');
			const cancelled = orders.filter(order => order.status === 'cancelled');
			
			this.counts.current = current.length;
			this.counts.completed = completed.length;
			this.counts.cancelled = cancelled.length;
		},

		async updateOrdersInBackground() {
			// Всегда проверяем обновления, если есть хотя бы один заказ
			if (this.allOrders.length === 0) {
				return; // Только если совсем нет заказов
			}

			try {
				const response = await orderAPI.getMySales(this.pagination?.current_page || 1);
				if (response.success) {
					const newAllOrders = response.data.data;
					
					// Проверяем, изменились ли заказы
					const hasChanges = this.hasOrderChanges(this.allOrders, newAllOrders);
					
					if (hasChanges) {
						this.allOrders = newAllOrders;
						this.orders = this.filterOrdersByTab(this.allOrders);
						this.updateCounts(this.allOrders);
					}
					
					this.pagination = {
						current_page: response.data.current_page,
						last_page: response.data.last_page,
						total: response.data.total
					};
				}
			} catch (error) {
				console.error('Background status update failed:', error);
			}
		},

		stopStatusUpdates() {
			if (this.statusUpdateInterval) {
				clearInterval(this.statusUpdateInterval);
				this.statusUpdateInterval = null;
			}
		},

		canCancelOrder(order) {
			return ['paid', 'processing'].includes(order.status) && !this.cancellingOrderId;
		},

		cancelOrder(order) {
			this.orderToCancel = order;
			const modal = new bootstrap.Modal(document.getElementById('confirmCancelModal'));
			modal.show();
		},

		async confirmCancelOrder() {
			if (!this.orderToCancel) return;

			this.cancellingOrderId = this.orderToCancel.id;

			try {
				const response = await orderAPI.cancelOrder(this.orderToCancel.id);

				if (response.success) {
					window.toast.success(response.message || 'Заказ успешно отменен');
					// Обновляем список заказов
					await this.loadOrders(this.pagination?.current_page || 1);
				} else {
					window.toast.error(response.message || 'Ошибка при отмене заказа');
				}
			} catch (error) {
				console.error('Cancel order error:', error);
				window.toast.error(error.response?.data?.message || 'Ошибка при отмене заказа');
			} finally {
				// Закрываем модальное окно
				const modal = bootstrap.Modal.getInstance(document.getElementById('confirmCancelModal'));
				if (modal) {
					modal.hide();
				}
				this.cancellingOrderId = null;
				this.orderToCancel = null;
			}
		},

		hasOrderChanges(oldOrders, newOrders) {
			if (!oldOrders || !newOrders) return true;
			if (oldOrders.length !== newOrders.length) return true;
			
			// Проверяем изменения в заказах
			for (let newOrder of newOrders) {
				const oldOrder = oldOrders.find(o => o.id === newOrder.id);
				if (!oldOrder) return true;
				
				// Проверяем изменения статуса
				if (oldOrder.status !== newOrder.status) {
					return true;
				}
				
				// Проверяем изменения в истории статусов трейда
				const oldHistoryLength = oldOrder.trade_status_history?.length || 0;
				const newHistoryLength = newOrder.trade_status_history?.length || 0;
				if (oldHistoryLength !== newHistoryLength) {
					return true;
				}
			}
			return false;
		},

		getEmptyStateText() {
			const texts = {
				'current': 'Нет текущих заказов',
				'completed': 'Нет успешных заказов',
				'cancelled': 'Нет отмененных заказов'
			};
			return texts[this.activeTab] || 'Нет заказов';
		},

		getEmptyStateSubtext() {
			const texts = {
				'current': 'Заказы в обработке отображаются здесь',
				'completed': 'Успешно завершенные покупки будут отображаться здесь',
				'cancelled': 'Отмененные и проблемные заказы появятся в этом списке'
			};
			return texts[this.activeTab] || '';
		}
	}
}
</script>