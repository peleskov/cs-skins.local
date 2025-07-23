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

				<!-- Order Items list (продажи по скинам) -->
				<div v-if="!isLoading && currentOrders.length > 0 && activeTab === tabId" class="sales-list">
					<div v-for="orderItem in currentOrders" :key="orderItem.id" class="order-item mb-2">
						<!-- Item Details -->
						<div class="order-items">
							<div class="item-card d-flex align-items-center p-3 border rounded mb-2 gap-3">
								<!-- Item Image -->
								<div class="item-image me-3">
									<img :src="orderItem.item_image_url" :alt="orderItem.item_name" class="img-fluid"
										style="width: 60px; height: 60px; object-fit: cover;">
								</div>

								<!-- Item Details -->
								<div class="item-details flex-grow-1">
									<div class="d-flex align-items-center gap-3">
										<h6 class="mb-1">{{ orderItem.item_name }}</h6>
										<span class="badge" :class="getStatusBadgeClass(orderItem.status)">
											{{ getStatusText(orderItem.status) }}
										</span>
									</div>
									<div>Цена: <strong>{{ formatPrice(orderItem.price) }} ₽</strong></div>
									<!-- Buyer Info -->
									<div class="buyer-info mb-1">
										<small class="text-muted">Покупатель:</small>
										<strong class="ms-2">{{ orderItem.buyer_name }}</strong>
										<small class="ms-2 text-muted">Заказ: {{ orderItem.order?.order_number || 'N/A'
										}}</small>
									</div>
									<div class="item-meta text-muted small">
										<span v-if="orderItem.reserved_until && orderItem.status === 'reserved'">
											⏰ До окончания резерва: <span class="fw-bold text-warning">{{ getTimeRemaining(orderItem.reserved_until) }}</span>
										</span>
									</div>
								</div>

								<button v-if="activeTab === 'new'" class="btn theme-outline btn-sm"
									@click="sendTrade(orderItem)">
									<i class="ri-arrow-right-line me-1"></i>
									Отправить трейд
								</button>
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
import { getTimeRemaining } from '../../utils/helpers';

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
				this.currentOrders = data.order_items || [];
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
				'reserved': 'bg-warning',
				'trade_sent': 'bg-info',
				'completed': 'bg-success',
				'cancelled': 'bg-danger'
			};
			return classes[status] || 'bg-secondary';
		},

		getStatusText(status) {
			const texts = {
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

		formatDate(date) {
			return new Date(date).toLocaleDateString('ru-RU', {
				year: 'numeric',
				month: 'short',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			});
		},

		formatPrice(price) {
			return Number(price).toLocaleString('ru-RU');
		},

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
				const hasReservedItems = this.currentOrders.some(item => item.status === 'reserved');
				
				if (hasReservedItems || this.activeTab === 'new') {
					try {
						// Тихо обновляем данные без показа лоадера
						const response = await axios.get(`/profile/sales?tab=${this.activeTab}`);
						const data = response.data;
						
						// Сохраняем старые данные для сравнения
						const oldOrders = [...this.currentOrders];
						const oldCounts = {...this.counts};
						
						this.currentOrders = data.order_items || [];
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
