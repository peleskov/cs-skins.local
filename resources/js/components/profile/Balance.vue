<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Управление балансом</h3>
		</div>

		<!-- Текущий баланс -->
		<div class="row g-4 mb-4">
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body text-center">
						<i class="ri-wallet-3-line display-4 text-primary mb-3"></i>
						<h5 class="card-title">Текущий баланс</h5>
						<h2 class="text-primary" v-html="formatPrice(client.balance)"></h2>
						<span class="badge bg-success me-2">
							<i class="ri-arrow-up-line me-1"></i>
							{{ formatNumber(salesStats.total_earned) }} ₽ заработано
						</span>
						<span class="badge bg-info">
							<i class="ri-shopping-bag-2-line me-1"></i>
							{{ salesStats.total_sales }} продаж
						</span>
						<div class="d-flex justify-content-center gap-3 mt-3">
							<button class="btn theme-btn" data-bs-toggle="modal"
								data-bs-target="#balance-refill">
								<i class="ri-add-line me-2"></i>Пополнить баланс
							</button>
							<button class="btn theme-outline" data-bs-toggle="modal"
								data-bs-target="#balance-withdraw">
								<i class="ri-bank-card-line me-2"></i>Вывести средства
							</button>

						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- История операций -->
		<div class="card">
			<div class="card-header">
				<h5 class="mb-0">
					<i class="ri-history-line me-2"></i>
					История операций
				</h5>
			</div>
			<div class="card-body">
				<!-- Loading State -->
				<div v-if="isLoadingTransactions" class="text-center py-4">
					<div class="spinner-border" role="status">
						<span class="visually-hidden">Загрузка...</span>
					</div>
					<p class="text-muted mt-2 mb-0">Загружаем историю операций...</p>
				</div>

				<!-- Empty State -->
				<div v-else-if="transactions.length === 0" class="text-center py-4">
					<i class="ri-file-list-line display-4 text-muted mb-3"></i>
					<h6>История операций пуста</h6>
					<p class="text-muted mb-0">Здесь будут отображаться все ваши финансовые операции</p>
				</div>

				<!-- Transactions List -->
				<div v-else class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th>Тип</th>
								<th>Описание</th>
								<th>Сумма</th>
								<th>Дата</th>
								<th>Статус</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="transaction in transactions" :key="transaction.id">
								<td>
									<i :class="getTransactionIcon(transaction.type)"></i>&nbsp;<span>{{ getTransactionTypeText(transaction.type) }}</span>
								</td>
								<td>{{ transaction.description || '—' }}</td>
								<td :class="getTransactionColor(transaction.type)">
									<strong v-html="getTransactionSign(transaction.type) + formatPrice(Math.abs(transaction.amount))">
									</strong>
								</td>
								<td class="text-muted">{{ formatDate(transaction.created_at) }}</td>
								<td>
									<span class="badge bg-success"
										v-if="transaction.status === 'completed'">Завершено</span>
									<span class="badge bg-warning" v-else-if="transaction.status === 'pending'">В
										обработке</span>
									<span class="badge bg-warning" v-else-if="transaction.status === 'on_hold'">На удержании</span>
									<span class="badge bg-danger"
										v-else-if="transaction.status === 'failed'">Ошибка</span>
									<span class="badge bg-secondary" v-else>{{ transaction.status }}</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Модальное окно пополнения -->
		<div class="modal fade" id="balance-refill" tabindex="-1" aria-labelledby="balanceRefillLabel"
			aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="balanceRefillLabel">
							<i class="ri-add-circle-line me-2"></i>Пополнение баланса
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body text-center">
						<div class="py-4">
							<i class="ri-settings-3-line display-4 text-muted mb-3"></i>
							<h4>В разработке</h4>
							<p class="text-muted">Функция пополнения баланса находится в разработке и будет доступна в
								ближайшее время.</p>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Понятно</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Модальное окно вывода -->
		<div class="modal fade" id="balance-withdraw" tabindex="-1" aria-labelledby="balanceWithdrawLabel"
			aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="balanceWithdrawLabel">
							<i class="ri-bank-card-line me-2"></i>Вывод средств
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body text-center">
						<div class="py-4">
							<i class="ri-settings-3-line display-4 text-muted mb-3"></i>
							<h4>В разработке</h4>
							<p class="text-muted">Функция вывода средств находится в разработке и будет доступна в
								ближайшее время.</p>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Понятно</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';
import { formatPrice } from '../../utils/helpers';

export default {
	name: 'ProfileBalance',
	props: {
		client: {
			type: Object,
			required: true
		}
	},
	data() {
		return {
			transactions: [],
			isLoadingTransactions: false,
			salesStats: {
				total_earned: 0,
				total_sales: 0
			},
			isLoadingStats: false,
			forceUpdate: 0 // Для принудительного обновления при смене валюты
		}
	},
	methods: {
		formatPrice,

		formatNumber(number, decimals = 2) {
			return Number(number).toFixed(decimals);
		},

		formatDate(dateString) {
			if (!dateString) return '';
			const date = new Date(dateString);
			return date.toLocaleDateString('ru-RU', {
				year: 'numeric',
				month: 'short',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			});
		},

		async loadTransactions() {
			if (this.isLoadingTransactions) return;

			this.isLoadingTransactions = true;
			try {
				const response = await axios.get('/api/profile/transactions');
				this.transactions = response.data.data || [];
			} catch (error) {
				console.error('Failed to load transactions:', error);
				window.toast?.error('Не удалось загрузить историю операций');
			} finally {
				this.isLoadingTransactions = false;
			}
		},

		async loadSalesStats() {
			if (this.isLoadingStats) return;

			this.isLoadingStats = true;
			try {
				const response = await axios.get('/api/profile/sales-stats');
				this.salesStats = response.data.data || this.salesStats;
			} catch (error) {
				console.error('Failed to load sales stats:', error);
				window.toast?.error('Не удалось загрузить статистику продаж');
			} finally {
				this.isLoadingStats = false;
			}
		},

		getTransactionTypeText(type) {
			const types = {
				'purchase': 'Покупка',
				'sale': 'Продажа',
				'fee': 'Комиссия',
				'refund': 'Возврат',
				'withdrawal': 'Списание',
				'deposit': 'Пополнение'
			};
			return types[type] || type;
		},

		getTransactionSign(type) {
			// Операции, которые уменьшают баланс (-)
			const negativeTypes = ['purchase', 'withdrawal', 'fee'];
			// Операции, которые увеличивают баланс (+)
			const positiveTypes = ['sale', 'deposit', 'refund'];

			if (negativeTypes.includes(type)) return '−&nbsp;';
			if (positiveTypes.includes(type)) return '+&nbsp;';
			return '';
		},

		getTransactionIcon(type) {
			const icons = {
				'purchase': 'ri-shopping-cart-line',
				'sale': 'ri-money-dollar-circle-line',
				'fee': 'ri-percent-line',
				'refund': 'ri-refund-line',
				'withdrawal': 'ri-bank-card-line',
				'deposit': 'ri-add-circle-line'
			};
			return icons[type] || 'ri-exchange-line';
		},

		getTransactionColor(type) {
			const colors = {
				'purchase': 'text-danger',
				'sale': 'text-success',
				'fee': 'text-danger',
				'refund': 'text-info',
				'withdrawal': 'text-danger',
				'deposit': 'text-success'
			};
			return colors[type] || 'text-muted';
		},

		handleCurrencyChange() {
			// Принудительно обновляем транзакции для пересчета цен
			this.transactions = [...this.transactions];
			this.salesStats = { ...this.salesStats };
			this.$forceUpdate();
		}
	},

	async mounted() {
		// Слушаем события изменения валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);

		await Promise.all([
			this.loadTransactions(),
			this.loadSalesStats()
		]);
	},

	beforeUnmount() {
		// Удаляем слушатель при уничтожении компонента
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
	}
}
</script>