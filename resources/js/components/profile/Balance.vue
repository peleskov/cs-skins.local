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
						<div class="d-flex justify-content-center gap-3 mt-3">
							<button class="btn theme-btn"
								data-bs-toggle="modal"
								data-bs-target="#balance-refill"
								:disabled="!cardPaymentEnabled">
								<i class="ri-add-line me-2"></i>
								{{ cardPaymentEnabled ? 'Пополнить баланс' : 'Пополнение недоступно' }}
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
					<div class="modal-body">
						<!-- Форма ввода суммы -->
						<div v-if="!depositForm.processing" class="payment-form">
							<div class="mb-4 text-center">
								<i class="ri-bank-card-line display-4 theme-color mb-3"></i>
								<h5>Пополнение баланса</h5>
								<p class="text-muted">Укажите сумму для пополнения через платежную форму</p>
							</div>

							<div class="row g-3">
								<!-- Сумма пополнения -->
								<div class="col-12">
									<label for="depositAmount" class="form-label">Сумма пополнения</label>
									<div class="input-group">
										<input type="number"
											   class="form-control"
											   id="depositAmount"
											   v-model="depositForm.amount"
											   :min="minimumDepositAmount"
											   :max="maximumDepositAmount"
											   step="1"
											   placeholder="Введите сумму">
										<span class="input-group-text">₽</span>
									</div>
									<div class="form-text">
										Сумма от {{ minimumDepositAmount }} до {{ maximumDepositAmount }} ₽
									</div>
								</div>
							</div>

							<div v-if="depositForm.error" class="alert alert-danger mt-3">
								{{ depositForm.error }}
							</div>

							<div class="mt-4">
								<div class="card border-info">
									<div class="card-body">
										<div class="d-flex align-items-center">
											<i class="ri-shield-check-line text-info me-3" style="font-size: 2rem;"></i>
											<div>
												<h6 class="mb-1">Безопасная оплата</h6>
												<small class="text-muted">
													Оплата происходит через защищенную форму банка.
													Ваши данные полностью защищены.
												</small>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Процесс создания платежа -->
						<div v-else class="text-center py-4">
							<div class="spinner-border text-primary mb-3" role="status">
								<span class="visually-hidden">Создание платежа...</span>
							</div>
							<h5>Создание платежа</h5>
							<p class="text-muted">Пожалуйста, подождите...</p>
						</div>
					</div>
					<div class="modal-footer">
						<div v-if="!depositForm.processing" class="w-100 d-flex gap-2">
							<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
							<button type="button"
									class="btn theme-btn flex-fill"
									@click="createPaymentForm"
									:disabled="!isValidAmount">
								Перейти к оплате <span v-html="formatPrice(depositForm.amount)"></span>
							</button>
						</div>
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
		},
		depositSettings: {
			type: Object,
			default: () => ({
				minimum_amount: 100,
				maximum_amount: 50000,
				card_payment_enabled: true
			})
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
			forceUpdate: 0, // Для принудительного обновления при смене валюты

			// Данные для пополнения баланса
			depositForm: {
				amount: '',
				processing: false,
				error: null
			}
		}
	},

	computed: {
		minimumDepositAmount() {
			return this.depositSettings.minimum_amount;
		},
		maximumDepositAmount() {
			return this.depositSettings.maximum_amount;
		},
		cardPaymentEnabled() {
			return this.depositSettings.card_payment_enabled;
		},
		isValidAmount() {
			const amount = parseFloat(this.depositForm.amount);
			return amount >= this.minimumDepositAmount && amount <= this.maximumDepositAmount;
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
				'deposit': 'Пополнение',
				'auction_bid': 'Ставка на аукционе',
				'auction_refund': 'Возврат со ставки'
			};
			return types[type] || type;
		},

		getTransactionSign(type) {
			// Операции, которые уменьшают баланс (-)
			const negativeTypes = ['purchase', 'withdrawal', 'fee', 'auction_bid'];
			// Операции, которые увеличивают баланс (+)
			const positiveTypes = ['sale', 'deposit', 'refund', 'auction_refund'];

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
		},



		// Payment Form Methods
		async createPaymentForm() {
			this.depositForm.processing = true;
			this.depositForm.error = null;

			try {
				// Формируем URL для редиректов
				const baseUrl = window.location.origin;
				const successUrl = `${baseUrl}/profile#balance?payment=success`;
				const failUrl = `${baseUrl}/profile#balance?payment=failed`;

				const response = await axios.post('/api/deposit/payment-form', {
					amount: parseFloat(this.depositForm.amount),
					success_url: successUrl,
					fail_url: failUrl
				});

				if (response.data.success) {
					// Закрываем модальное окно
					this.closeModal();

					// Создаем POST-форму для отправки на платежную форму
					this.submitPaymentForm(response.data.payment_form_url, successUrl, failUrl);

					// Начинаем периодическую проверку статуса платежа
					this.startPaymentStatusCheck(response.data.payment_id);
				} else {
					this.depositForm.error = response.data.message || 'Ошибка создания платежа';
				}
			} catch (error) {
				console.error('Failed to create payment form:', error);

				// Ошибки валидации показываем в форме
				if (error.response?.status === 422) {
					this.depositForm.error = error.response?.data?.message || 'Проверьте правильность введенных данных';
				} else {
					// Остальные ошибки пробрасываем для глобального interceptor
					this.depositForm.error = null;
					throw error;
				}
			} finally {
				this.depositForm.processing = false;
			}
		},

		submitPaymentForm(paymentFormUrl, successUrl, failUrl) {
			// Создаем нативную HTML форму для POST запроса
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = paymentFormUrl;
			form.enctype = 'application/x-www-form-urlencoded';

			// Добавляем скрытые поля для редиректов
			const successInput = document.createElement('input');
			successInput.type = 'hidden';
			successInput.name = 'successUrl';
			successInput.value = successUrl;
			form.appendChild(successInput);

			const failInput = document.createElement('input');
			failInput.type = 'hidden';
			failInput.name = 'failUrl';
			failInput.value = failUrl;
			form.appendChild(failInput);

			// Добавляем форму в DOM и отправляем
			document.body.appendChild(form);
			form.submit();

			// Удаляем форму из DOM
			document.body.removeChild(form);
		},

		startPaymentStatusCheck(paymentId) {
			// Проверяем статус платежа каждые 10 секунд
			const checkInterval = setInterval(async () => {
				try {
					const response = await axios.get(`/api/deposit/status/${paymentId}`);

					if (response.data.success && response.data.payment) {
						const payment = response.data.payment;

						if (payment.status === 'paid') {
							// Платеж успешно завершен
							clearInterval(checkInterval);
							this.onPaymentSuccess(payment.amount);
							window.toast?.success('Платеж успешно завершен!');
						} else if (['failed', 'expired', 'cancelled'].includes(payment.status)) {
							// Платеж не удался
							clearInterval(checkInterval);
							window.toast?.error('Платеж не был завершен');
						}
					}
				} catch (error) {
					console.error('Failed to check payment status:', error);
					// Не останавливаем проверку при ошибках сети
				}
			}, 10000);

			// Останавливаем проверку через 15 минут
			setTimeout(() => {
				clearInterval(checkInterval);
			}, 15 * 60 * 1000);
		},

		onPaymentSuccess(amount) {
			// Обновляем баланс клиента
			this.client.balance = parseFloat(this.client.balance) + parseFloat(amount);

			// Перезагружаем транзакции
			this.loadTransactions();

			// Показываем toast (получаем число и форматируем для текста)
			const priceNumber = this.formatPrice(amount, 'RUB', true);
			const formattedPrice = priceNumber.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
			window.toast?.success(`Баланс успешно пополнен на ${formattedPrice}`);
		},

		closeModal() {
			// Закрываем модальное окно
			const modal = bootstrap.Modal.getInstance(document.getElementById('balance-refill'));
			if (modal) {
				modal.hide();
			}

			// Сбрасываем форму
			this.resetForm();
		},

		resetForm() {
			this.depositForm.amount = '';
			this.depositForm.error = null;
			this.depositForm.processing = false;
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