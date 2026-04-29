<template>
	<div class="order-item mb-4 card">
		<div class="card-header">
			<!-- Order Header -->
			<div class="d-flex align-items-center justify-content-between">
				<div class="d-flex align-items-center gap-3">
					<div class="d-flex flex-column gap-2">
						<div class="col d-flex flex-column flex-lg-row align-items-lg-center gap-2">
							<div class="d-flex justify-content-between">
								<div>
									<div class="mb-1">
										<button class="btn btn-link p-0 text-decoration-none fw-bold"
											data-bs-toggle="collapse" :data-bs-target="`#order-${order.id}`"
											:aria-expanded="false" :aria-controls="`order-${order.id}`">
											<i class="ri-arrow-right-s-line me-1"></i>
											Заказ {{ order.order_number }}
										</button>
									</div>
									<span v-if="order.reserved_until && order.status === 'processing'"
										class="badge bg-warning text-dark ms-2">
										⏰ Резерв: {{ getTimeRemaining(order.reserved_until) }}
									</span>
									<small class="text-muted ms-2">
										{{ userLabel }}: <span class="fw-medium">{{ getUserName() }}</span>
									</small>
								</div>
								<div class="order-meta text-end d-lg-none">
									<div class="order-amount mb-1">
										<strong class="fs-5 text-primary"
											v-html="formatPrice(order.total_amount)"></strong>
									</div>
									<small class="text-muted">{{ formatDate(order.created_at) }}</small>
								</div>
							</div>
						</div>
						<!-- Progress bar -->
						<div class="col border-top pt-2 mt-2">
							<div class="d-flex flex-wrap overflow-hidden align-items-start progress-bar-container">
								<div v-for="step in getProgressSteps(order)" :key="step.id" class="progress-step"
									:class="step.cssClass">
									<i class="step-ico"></i>
									{{ step.title }}
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="order-meta d-none d-lg-block text-end">
					<div class="order-amount mb-1">
						<strong class="fs-5 text-primary" v-html="formatPrice(order.total_amount)"></strong>
					</div>
					<small class="text-muted">{{ formatDate(order.created_at) }}</small>
				</div>
			</div>
		</div>

		<!-- Collapsible Order Items -->
		<div :id="`order-${order.id}`" class="collapse">
			<div class="card-body">
				<!-- Notes section -->
				<div v-if="order.notes || order.system_remarks" class="notes-section mb-3">
					<div v-if="order.system_remarks" class="bg-light text-muted p-2 mb-2 rounded">
						<small>{{ order.system_remarks }}</small>
					</div>
					<div v-if="order.notes" class="p-2">
						<p><strong><small>Примечание:</small></strong></p>
						<p><small>{{ order.notes }}</small></p>
					</div>
				</div>

				<!-- Order items -->
				<div v-if="order.cart_snapshot && order.cart_snapshot.length > 0" class="order-items">
					<div v-for="item in order.cart_snapshot" :key="item.listing_id"
						class="item-card d-flex align-items-center p-3 border rounded mb-2">
						<!-- Item Image -->
						<div class="item-image me-3">
							<img :src="item.item.image_url" :alt="item.item.name" class="img-fluid rounded"
								style="width: 60px; height: 60px; object-fit: cover;">
						</div>
						<!-- Item Details -->
						<div class="item-details flex-grow-1">
							<h6 class="mb-1">
								<a v-if="item.listing_id" :href="'/marketplace/' + item.listing_id"
									class="text-decoration-none">
									{{ item.item.name }}
								</a>
								<span v-else>{{ item.item.name }}</span>
							</h6>
						</div>
						<!-- Item Price -->
						<div class="item-price text-end">
							<strong class="fs-6 text-success" v-html="formatPrice(item.price)"></strong>
						</div>
					</div>
				</div>

				<!-- Actions section -->
				<div v-if="showCancelButton && canCancelOrder(order)"
					class="d-flex justify-content-end mt-3 pt-3 border-top">
					<button @click="$emit('cancel-order', order)" class="btn btn-danger btn-sm">
						<i class="ri-close-circle-line"></i> Отменить заказ
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { formatPrice, formatDate, getTimeRemaining } from '../../../shared/utils/helpers';

// Маппинг статусов трейдов на читаемые названия
const TRADE_STATUS_TITLES = {
	'Paid': 'Оплачен',
	'CreatedSteam': 'Создан в Steam',
	'CreatedNeedsConfirmation': 'Подтверждение продавца',
	'Active': 'Подтверждение покупателя',
	'Accepted': 'Завершен в Steam',
	'Declined': 'Отклонен в Steam',
	'Canceled': 'Отменен в Steam',
	'Expired': 'Истек в Steam',
	'CanceledBySecondFactor': 'Отменен из-за 2FA'
};

export default {
	name: 'OrderItem',
	props: {
		order: {
			type: Object,
			required: true
		},
		userType: {
			type: String,
			default: 'seller', // 'seller' или 'buyer'
			validator: value => ['seller', 'buyer'].includes(value)
		},
		showCancelButton: {
			type: Boolean,
			default: true
		}
	},
	emits: ['cancel-order'],
	data() {
		return {
			timerInterval: null
		}
	},
	computed: {
		userLabel() {
			return this.userType === 'seller' ? 'Продавец' : 'Покупатель';
		}
	},
	watch: {
		order: {
			deep: true,
			handler(newOrder, oldOrder) {
				// Обрабатываем изменения статуса
				if (oldOrder && newOrder.status !== oldOrder.status) {
					// Если статус изменился на processing и есть резерв - запускаем таймер
					if (newOrder.status === 'processing' && newOrder.reserved_until) {
						this.startTimer();
					}
					// Если статус больше не processing - останавливаем таймер
					else if (newOrder.status !== 'processing') {
						this.stopTimer();
					}
				}
				// При изменении заказа обновляем отображение
				this.$forceUpdate();
			}
		}
	},
	mounted() {
		// Запускаем таймер только если есть резерв
		if (this.order.reserved_until && this.order.status === 'processing') {
			this.startTimer();
		}
	},
	beforeUnmount() {
		this.stopTimer();
	},
	methods: {
		formatPrice,
		formatDate,
		getTimeRemaining,

		startTimer() {
			// Останавливаем предыдущий таймер если был
			this.stopTimer();

			// Запускаем только если есть активный резерв
			if (this.order.reserved_until && this.order.status === 'processing') {
				// Обновляем таймер каждую секунду
				this.timerInterval = setInterval(() => {
					// Проверяем, не истек ли резерв
					const remaining = this.getTimeRemaining(this.order.reserved_until);
					this.$forceUpdate(); // Обновляем отображение таймера

					if (remaining === 'Истек') {
						this.stopTimer(); // Останавливаем после обновления
					}
				}, 1000);
			}
		},

		stopTimer() {
			if (this.timerInterval) {
				clearInterval(this.timerInterval);
				this.timerInterval = null;
			}
		},

		getUserName() {
			const user = this.userType === 'seller' ? this.order.seller : this.order.buyer;
			return user?.name || 'Не указан';
		},

		canCancelOrder(order) {
			return ['paid', 'processing'].includes(order.status);
		},

		getProgressSteps(order) {
			const steps = [];
			let stepId = 0;

			// Шаг 1: Всегда "Оплачен"
			steps.push({
				id: ++stepId,
				title: TRADE_STATUS_TITLES['Paid'],
				cssClass: ''
			});

			// Шаг 2: "Создан в Steam" если есть история трейда или заказ в процессе
			if (order.status === 'processing' ||
				(order.trade_status_history && order.trade_status_history.length > 0)) {
				steps.push({
					id: ++stepId,
					title: TRADE_STATUS_TITLES['CreatedSteam'],
					cssClass: ''
				});
			}

			// Шаги из истории трейда
			if (order.trade_status_history && order.trade_status_history.length > 0) {
				order.trade_status_history.forEach(history => {
					// Пропускаем CreatedSteam если он есть в истории - мы его уже добавили выше
					if (history.status === 'CreatedSteam') return;

					const title = TRADE_STATUS_TITLES[history.status] || history.status;
					// Все статусы из истории трейда - не финальные

					steps.push({
						id: ++stepId,
						title: title,
						cssClass: ''
					});
				});
			}

			// Добавляем финальный статус заказа
			if (order.status === 'cancelled') {
				steps.push({
					id: ++stepId,
					title: 'Заказ отменен',
					cssClass: 'completed cancelled'
				});
			} else if (order.status === 'completed') {
				steps.push({
					id: ++stepId,
					title: 'Заказ выполнен',
					cssClass: 'completed'
				});
			}

			return steps;
		}
	}
}
</script>