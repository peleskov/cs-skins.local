<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Продажи</h3>
		</div>

		<!-- Tabs Navigation -->
		<ul class="nav nav-tabs tab-style1 mb-4" id="salesTab" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: activeTab === 'new' }" id="new-tab" data-bs-toggle="tab"
					data-bs-target="#new" type="button" role="tab" @click="setActiveTab('new')">
					Новые
					<span v-if="counts.new > 0" class="badge bg-body-secondary ms-1">{{ counts.new }}</span>
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: activeTab === 'pending' }" id="pending-tab"
					data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab"
					@click="setActiveTab('pending')">
					Ожидающие
					<span v-if="counts.pending > 0" class="badge bg-body-secondary ms-1">{{ counts.pending }}</span>
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: activeTab === 'completed' }" id="completed-tab"
					data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab"
					@click="setActiveTab('completed')">
					Завершенные
					<span v-if="counts.completed > 0" class="badge bg-body-secondary ms-1">{{ counts.completed }}</span>
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: activeTab === 'cancelled' }" id="cancelled-tab"
					data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab"
					@click="setActiveTab('cancelled')">
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

		<div class="tab-content" id="salesTabContent">
			<!-- Единый компонент для всех табов -->
			<div v-for="tabId in ['new', 'pending', 'completed', 'cancelled']" :key="tabId" class="tab-pane fade"
				:class="{ 'show active': activeTab === tabId }" :id="tabId" role="tabpanel"
				:aria-labelledby="`${tabId}-tab`" tabindex="0">

				<!-- Orders list (продажи по заказам) -->
				<div v-if="!isLoading && currentOrders.length > 0 && activeTab === tabId" class="sales-list">
					<div v-for="order in currentOrders" :key="order.id" class="order-item mb-4 card">
						<!-- Order Header -->
						<div class="card-header">
							<div class="d-flex align-items-center justify-content-between">
								<div class="d-flex align-items-center gap-3">
									<button class="btn btn-link p-0 text-decoration-none fw-bold"
										data-bs-toggle="collapse" :data-bs-target="`#order-${order.id}`"
										:aria-expanded="false" :aria-controls="`order-${order.id}`">
										<i class="ri-arrow-right-s-line me-1"></i>
										Заказ {{ order.order_number }}
									</button>
									<span class="badge" :class="getStatusBadgeClass(order.status)">
										{{ getStatusText(order.status) }}
									</span>
									<span v-if="order.reserved_until && order.status === 'processing'"
										class="badge bg-warning text-dark ms-2">
										⏰ Резерв: {{ getTimeRemaining(order.reserved_until) }}
									</span>
									<small class="text-muted ms-2">
										Покупатель: <span class="fw-medium">{{ order.buyer?.name || 'Не указан'
											}}</span>
									</small>
								</div>
								<div class="text-end">
									<strong class="fs-5 text-success">{{ formatPrice(order.total_amount) }} ₽</strong>
									<div><small class="text-muted">{{ formatDate(order.created_at) }}</small></div>
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
								<div v-for="item in order.cart_snapshot" :key="item.listing_id" class="order-items">
									<div class="item-card d-flex align-items-center p-3 border rounded mb-2 gap-3">
										<!-- Item Image -->
										<div class="item-image me-3">
											<img :src="item.item.image_url" :alt="item.item.name" class="img-fluid"
												style="width: 60px; height: 60px; object-fit: cover;">
										</div>

										<!-- Item Details -->
										<div class="item-details flex-grow-1">
											<h6 class="mb-1">{{ item.item.name }}</h6>
											<div>Цена: <strong>{{ formatPrice(item.price) }} ₽</strong></div>
										</div>

										<!-- Item Price -->
										<div class="item-price text-end">
											<strong class="fs-6 text-success">{{ formatPrice(item.price) }} ₽</strong>
										</div>
									</div>
								</div>

								<!-- Trade button for the whole order -->
								<div v-if="activeTab === 'new'" class="mt-3 text-center">
									<button class="btn theme-outline btn-sm" @click="sendTrade(order)">
										<i class="ri-arrow-right-line me-1"></i>
										Отправить трейд на весь заказ
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Empty state -->
				<div v-else-if="!isLoading && activeTab === tabId" class="text-center py-5">
					<div class="empty-state-icon mb-3">
						<i class="ri-shopping-bag-2-line" style="font-size: 4rem; color: #ccc;"></i>
					</div>
					<h4>{{ getEmptyStateText() }}</h4>
					<p class="text-muted">{{ getEmptyStateSubtext() }}</p>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';
import { getTimeRemaining, formatDate, formatPrice } from '../../utils/helpers';

export default {
	name: 'ProfileSales',
	props: {
		client: {
			type: Object,
			required: true
		}
	},
	data() {
		return {
			isLoading: true,
			activeTab: 'new',
			currentOrders: [],
			counts: {
				new: 0,
				pending: 0,
				completed: 0,
				cancelled: 0
			},
			timerInterval: null,
			statusUpdateInterval: null
		}
	},
	mounted() {
		this.loadSales();
		this.startTimer();
		this.startStatusUpdates();
	},
	beforeUnmount() {
		this.stopTimer();
		this.stopStatusUpdates();
	},
	methods: {
		getTimeRemaining,

		async loadSales() {
			this.isLoading = true;
			try {
				const response = await axios.get(`/profile/sales?tab=${this.activeTab}`);
				const data = response.data;
				this.currentOrders = data.orders || [];
				this.counts = data.counts || this.counts;
			} catch (error) {
				console.error('Error loading sales:', error);
			} finally {
				this.isLoading = false;
			}
		},

		setActiveTab(tab) {
			if (this.activeTab !== tab) {
				this.activeTab = tab;
				this.loadSales();
			}
		},

		getItemImage(item) {
			// Возвращаем изображение скина или плейсхолдер
			return item.image_url || '/images/skins/placeholder.png';
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
				'reserved': 'Ожидает трейда',
				'trade_sent': 'Трейд отправлен',
				'completed': 'Завершен',
				'cancelled': 'Отменен'
			};
			return texts[status] || status;
		},

		getEmptyStateText() {
			const texts = {
				'new': 'Нет новых заказов',
				'pending': 'Нет ожидающих трейдов',
				'completed': 'Нет завершенных продаж',
				'cancelled': 'Нет отмененных заказов'
			};
			return texts[this.activeTab] || 'Нет заказов';
		},

		getEmptyStateSubtext() {
			const texts = {
				'new': 'Новые заказы появятся здесь после оплаты покупателями',
				'pending': 'Отправленные трейды будут отображаться в этом разделе',
				'completed': 'Здесь будут показаны все успешно завершенные продажи',
				'cancelled': 'Отмененные и проблемные заказы появятся в этом списке'
			};
			return texts[this.activeTab] || '';
		},

		formatDate,
		formatPrice,

		async sendTrade(orderItem) {
			// TODO: Интеграция с расширением для отправки трейда
			console.log('Sending trade for order item:', orderItem.id);
			alert('Функция отправки трейда будет реализована с расширением');
		},


		startTimer() {
			this.timerInterval = setInterval(() => {
				this.$forceUpdate(); // Принуждаем Vue к перерисовке для обновления таймеров
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
				// Проверяем, есть ли заказы с резервированными товарами в активной вкладке
				const hasReservedItems = this.currentOrders.some(order => order.status === 'processing');

				if (hasReservedItems || this.activeTab === 'new') {
					try {
						// Тихо обновляем данные без показа лоадера
						const response = await axios.get(`/profile/sales?tab=${this.activeTab}`);
						const data = response.data;

						// Сохраняем старые данные для сравнения
						const oldOrders = [...this.currentOrders];
						const oldCounts = { ...this.counts };

						this.currentOrders = data.orders || [];
						this.counts = data.counts || this.counts;

						// Проверяем, изменились ли статусы товаров
						this.checkForStatusChanges(oldOrders, this.currentOrders);

						// Проверяем, изменились ли счетчики вкладок
						this.checkForCountChanges(oldCounts, this.counts);

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
		},

		checkForStatusChanges(oldOrders, newOrders) {
			// Проверяем изменения статусов и показываем уведомления
			for (const newItem of newOrders) {
				const oldItem = oldOrders.find(o => o.id === newItem.id);
				if (oldItem && oldItem.status !== newItem.status) {
					// Статус изменился
					if (newItem.status === 'cancelled' && oldItem.status === 'reserved') {
						window.toast.warning(`Резерв товара "${newItem.item_name}" истек и был отменен`);
					}
				}
			}
		},

		checkForCountChanges(oldCounts, newCounts) {
			// Проверяем изменения в счетчиках вкладок - товары автоматически переносятся
		}
	}
}
</script>
