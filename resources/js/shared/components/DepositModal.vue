<template>
	<div class="modal fade" :id="modalId" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">
						<i class="ri-add-circle-line me-2"></i>{{ title }}
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="!form.processing" class="payment-form">
						<div v-if="message" class="alert alert-warning mb-3">
							<i class="ri-information-line me-2"></i>{{ message }}
						</div>

						<div class="row g-3">
							<!-- Сумма -->
							<div class="col-12">
								<label class="form-label">Сумма пополнения</label>
								<div class="input-group">
									<input type="number" class="form-control" v-model="form.amount"
										:min="minAmount" :max="maxAmount" step="1" placeholder="Введите сумму">
									<span class="input-group-text">₽</span>
								</div>
								<div class="form-text">Сумма от {{ minAmount }} до {{ maxAmount }} ₽</div>
							</div>

							<!-- Быстрый выбор -->
							<div class="col-12">
								<div class="d-flex flex-wrap gap-2">
									<button v-for="amount in presetAmounts" :key="amount" type="button"
										class="btn btn-sm flex-fill"
										:class="String(form.amount) === String(amount) ? 'theme-btn' : 'theme-outline'"
										@click="form.amount = amount">
										{{ amount }} ₽
									</button>
								</div>
							</div>

							<!-- Промокод -->
							<div class="col-12">
								<label class="form-label">Промокод</label>
								<div class="input-group">
									<input type="text" class="form-control" v-model="form.promocode"
										placeholder="Введите промокод (необязательно)"
										:disabled="form.validatingPromocode">
									<span v-if="form.validatingPromocode" class="input-group-text">
										<span class="spinner-border spinner-border-sm"></span>
									</span>
								</div>
								<div v-if="form.promocodeResult" class="mt-2">
									<div v-if="form.promocodeResult.valid" class="text-success small">
										<i class="ri-check-line me-1"></i>{{ form.promocodeResult.message }}
										<span v-if="form.promocodeResult.bonus_amount">
											(+{{ form.promocodeResult.bonus_amount }} ₽ бонус)
										</span>
									</div>
									<div v-else class="text-danger small">
										<i class="ri-close-line me-1"></i>{{ form.promocodeResult.message }}
									</div>
								</div>
							</div>

							<!-- Способ оплаты -->
							<div class="col-12" v-if="paymentMethods.length > 1">
								<label class="form-label">Способ оплаты</label>
								<div class="d-flex flex-column gap-2">
									<label v-for="method in paymentMethods" :key="method.value"
										class="payment-method-option mb-0" style="cursor: pointer;">
										<input type="radio" :value="method.value" v-model="form.payment_type"
											class="d-none">
										<div class="d-flex align-items-center p-3 border rounded"
											:class="form.payment_type === method.value ? 'border-primary bg-primary bg-opacity-10' : 'border-secondary'">
											<i :class="method.icon" class="me-3" style="font-size: 1.5rem;"></i>
											<span>{{ method.label }}</span>
											<i v-if="form.payment_type === method.value"
												class="ri-check-line ms-auto text-primary"></i>
										</div>
									</label>
								</div>
							</div>
						</div>

						<div v-if="form.error" class="alert alert-danger mt-3">{{ form.error }}</div>
					</div>

					<div v-else class="text-center py-4">
						<div class="spinner-border text-primary mb-3" role="status"></div>
						<h5>Создание платежа</h5>
						<p class="text-muted">Пожалуйста, подождите...</p>
					</div>
				</div>
				<div class="modal-footer" v-if="!form.processing">
					<div class="w-100 d-flex gap-2">
						<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
						<button type="button" class="btn theme-btn flex-fill" @click="createPayment"
							:disabled="!isValidAmount">
							Пополнить {{ form.amount ? form.amount + ' ₽' : '' }}
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'DepositModal',
	props: {
		modalId: { type: String, default: 'deposit-modal' },
		depositSettings: {
			type: Object,
			default: () => ({
				minimum_amount: 100,
				maximum_amount: 50000,
				card_payment_enabled: true,
				test_payment_enabled: false
			})
		},
		title: { type: String, default: 'Пополнение баланса' },
		presetAmounts: { type: Array, default: () => [100, 500, 1000, 5000] }
	},
	emits: ['success'],
	data() {
		return {
			message: null,
			form: {
				amount: '',
				payment_type: 'card',
				promocode: '',
				promocodeResult: null,
				validatingPromocode: false,
				processing: false,
				error: null
			},
			promocodeDebounceTimer: null
		};
	},
	computed: {
		minAmount() { return this.depositSettings.minimum_amount ?? 100; },
		maxAmount() { return this.depositSettings.maximum_amount ?? 50000; },
		isValidAmount() {
			const a = parseFloat(this.form.amount);
			return a >= this.minAmount && a <= this.maxAmount;
		},
		paymentMethods() {
			const methods = [];
			if (this.depositSettings.card_payment_enabled) methods.push({ value: 'card', label: 'Банковская карта', icon: 'ri-bank-card-line' });
			if (this.depositSettings.test_payment_enabled) methods.push({ value: 'test', label: 'Тестовый платёж', icon: 'ri-bug-line' });
			return methods;
		}
	},
	watch: {
		'form.amount'() { this.debouncedValidatePromocode(); },
		'form.promocode'() { this.debouncedValidatePromocode(); }
	},
	methods: {
		open({ amount = null, message = null } = {}) {
			this.message = message;
			if (amount) {
				const a = Math.max(this.minAmount, Math.min(this.maxAmount, Math.ceil(amount)));
				this.form.amount = a;
			}
			const el = document.getElementById(this.modalId);
			if (el) {
				const modal = window.bootstrap.Modal.getOrCreateInstance(el);
				modal.show();
			}
		},
		close() {
			const el = document.getElementById(this.modalId);
			if (el) {
				const modal = window.bootstrap.Modal.getInstance(el);
				if (modal) modal.hide();
			}
			this.reset();
		},
		reset() {
			if (this.promocodeDebounceTimer) clearTimeout(this.promocodeDebounceTimer);
			this.form.amount = '';
			this.form.payment_type = 'card';
			this.form.promocode = '';
			this.form.promocodeResult = null;
			this.form.validatingPromocode = false;
			this.form.error = null;
			this.form.processing = false;
			this.message = null;
		},
		debouncedValidatePromocode() {
			if (this.promocodeDebounceTimer) clearTimeout(this.promocodeDebounceTimer);
			if (!this.form.promocode) { this.form.promocodeResult = null; return; }
			this.promocodeDebounceTimer = setTimeout(() => this.validatePromocode(), 500);
		},
		async validatePromocode() {
			if (!this.form.promocode) { this.form.promocodeResult = null; return; }
			if (!this.form.amount || !this.isValidAmount) {
				this.form.promocodeResult = { valid: false, message: 'Введите корректную сумму пополнения' };
				return;
			}
			this.form.validatingPromocode = true;
			try {
				const res = await axios.post('/api/deposit/validate-promocode', {
					code: this.form.promocode,
					amount: parseFloat(this.form.amount)
				});
				this.form.promocodeResult = {
					valid: res.data.valid,
					message: res.data.message,
					bonus_amount: res.data.bonus_amount
				};
			} catch (e) {
				this.form.promocodeResult = { valid: false, message: e.response?.data?.message || 'Ошибка проверки промокода' };
			} finally {
				this.form.validatingPromocode = false;
			}
		},
		async createPayment() {
			this.form.processing = true;
			this.form.error = null;

			try {
				const baseUrl = window.location.origin;
				const successUrl = `${baseUrl}${window.location.pathname}?payment=success${window.location.hash}`;
				const failUrl = `${baseUrl}${window.location.pathname}?payment=failed${window.location.hash}`;

				const requestData = {
					amount: parseFloat(this.form.amount),
					payment_type: this.form.payment_type,
					success_url: successUrl,
					fail_url: failUrl
				};
				if (this.form.promocode && this.form.promocodeResult?.valid) {
					requestData.promocode = this.form.promocode;
				}

				const res = await axios.post('/api/deposit/payment-form', requestData);
				if (res.data.success) {
					this.close();
					if (res.data.status === 'completed') {
						this.$emit('success', res.data.amount);
						window.toast?.success(res.data.message || 'Платеж успешно завершён');
					} else {
						this.submitPaymentForm(res.data.payment_form_url, successUrl, failUrl);
					}
				} else {
					this.form.error = res.data.message || 'Ошибка создания платежа';
				}
			} catch (e) {
				if (e.response?.status === 422) {
					this.form.error = e.response?.data?.message || 'Проверьте правильность введённых данных';
				} else {
					this.form.error = e.response?.data?.message || 'Не удалось создать платёж';
				}
			} finally {
				this.form.processing = false;
			}
		},
		submitPaymentForm(url, successUrl, failUrl) {
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = url;
			form.enctype = 'application/x-www-form-urlencoded';
			[['successUrl', successUrl], ['failUrl', failUrl]].forEach(([n, v]) => {
				const i = document.createElement('input');
				i.type = 'hidden'; i.name = n; i.value = v;
				form.appendChild(i);
			});
			document.body.appendChild(form);
			form.submit();
			document.body.removeChild(form);
		}
	}
};
</script>
