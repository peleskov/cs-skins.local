<template>
	<div id="Balance" class="change-profile-content position-relative">
		<a href="/profile#profile" class="btn-to-profile d-lg-none"><i class="m-ico m-ico-back"></i>Назад</a>
		<div class="title">
			<div class="loader-line d-none d-lg-block"></div>
			<h3 class="mb-4 mb-lg-0">Управление балансом</h3>
		</div>

		<!-- Мобильная версия балансов -->
		<div class="d-lg-none m-balance-block">
			<div class="row g-3 mb-3">
				<div class="col-6">
					<div class="m-balance-card m-balance-card--main">
						<div class="m-balance-card__head">
							<i class="m-ico m-ico-balance-main"></i>
							<span class="m-balance-card__label">Основной баланс</span>
						</div>
						<div class="m-balance-card__value" v-html="formatPrice(client.balance)"></div>
					</div>
				</div>
				<div class="col-6">
					<div class="m-balance-card m-balance-card--bonus">
						<div class="m-balance-card__head">
							<i class="m-ico m-ico-balance-bonus"></i>
							<span class="m-balance-card__label">Бонусный баланс</span>
						</div>
						<div class="m-balance-card__value" v-html="formatPrice(client.bonus_balance || 0)"></div>
					</div>
				</div>
			</div>
			<button class="btn m-balance-btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#balance-refill"
				:disabled="availablePaymentMethods.length === 0">
				<i class="ri-add-circle-line me-2"></i>Пополнить
			</button>
			<div class="row g-2">
				<div class="col-6">
					<button class="btn m-balance-btn-outline w-100" data-bs-toggle="modal"
						data-bs-target="#balance-withdraw">
						<i class="m-ico m-ico-balance-withdraw me-2"></i>Вывести
					</button>
				</div>
				<div class="col-6">
					<button class="btn m-balance-btn-outline w-100" data-bs-toggle="modal"
						data-bs-target="#promocode-activate">
						<i class="m-ico m-ico-balance-promo me-2"></i>Промокод
					</button>
				</div>
			</div>
		</div>

		<!-- Балансы (десктоп) -->
		<div class="row g-4 mb-4 d-none d-lg-flex">
			<!-- Основной баланс -->
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body text-center">
						<i class="ri-wallet-3-line display-4 text-primary mb-3"></i>
						<h5 class="card-title">Основной баланс</h5>
						<h2 class="text-primary" v-html="formatPrice(client.balance)"></h2>
						<div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
							<button class="btn theme-btn ps-2" data-bs-toggle="modal" data-bs-target="#balance-refill"
								:disabled="availablePaymentMethods.length === 0">
								<i class="ri-add-line me-2"></i>
								{{ availablePaymentMethods.length > 0 ? 'Пополнить' : 'Недоступно' }}
							</button>
							<button class="btn theme-outline ps-2" data-bs-toggle="modal"
								data-bs-target="#balance-withdraw">
								<i class="ri-bank-card-line me-2"></i>Вывести
							</button>
							<button class="btn theme-outline ps-2" data-bs-toggle="modal"
								data-bs-target="#promocode-activate">
								<i class="ri-coupon-line me-2"></i>Промокод
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Бонусный баланс -->
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body text-center">
						<i class="ri-gift-line display-4 text-success mb-3"></i>
						<h5 class="card-title">Бонусный баланс</h5>
						<h2 class="text-success" v-html="formatPrice(client.bonus_balance || 0)"></h2>
						<p class="text-muted small mt-3 mb-0">
							<i class="ri-information-line me-1"></i>
							Бонусы можно использовать только для открытия кейсов
						</p>
					</div>
				</div>
			</div>
		</div>

		<!-- Средства в холде -->
		<div class="row g-4 mb-4" v-if="heldBalance.seller > 0 || heldBalance.buyer > 0">
			<div class="col-12">
				<div class="card h-100">
					<div class="card-body text-center">
						<i class="ri-time-line display-4 text-warning mb-3"></i>
						<h5 class="card-title">Средства на удержании</h5>

						<!-- Холд как продавец -->
						<div v-if="heldBalance.seller > 0" class="mb-2">
							<h3 class="text-warning mb-0" v-html="formatPrice(heldBalance.seller)"></h3>
						</div>

						<!-- Холд как покупатель -->
						<div v-if="heldBalance.buyer > 0">
							<h4 class="text-info mb-0" v-html="formatPrice(heldBalance.buyer)"></h4>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- История операций с табами -->
		<ul class="nav nav-tabs tab-style1 mb-4" role="tablist">
			<li class="flex-fill flex-lg-grow-0 flex-lg-shrink-0 nav-item" role="presentation">
				<button class="nav-link d-flex align-items-center justify-content-center"
					:class="{ active: historyTab === 'transactions' }" type="button" role="tab"
					@click="historyTab = 'transactions'">
					<i class="ri-history-line me-2 d-none d-lg-inline"></i>Операции
				</button>
			</li>
			<li class="flex-fill flex-lg-grow-0 flex-lg-shrink-0 nav-item" role="presentation">
				<button class="nav-link d-flex align-items-center justify-content-center"
					:class="{ active: historyTab === 'bonus' }" type="button" role="tab"
					@click="historyTab = 'bonus'; loadBonusTransactions()">
					<i class="ri-gift-line me-2 d-none d-lg-inline"></i>Бонусы
				</button>
			</li>
			<li class="flex-fill flex-lg-grow-0 flex-lg-shrink-0 nav-item" role="presentation">
				<button class="nav-link d-flex align-items-center justify-content-center"
					:class="{ active: historyTab === 'held' }" type="button" role="tab" @click="historyTab = 'held'">
					<i class="ri-time-line me-2 d-none d-lg-inline"></i>Удержание
					<span v-if="heldOrders.seller.length + heldOrders.buyer.length > 0"
						class="badge bg-warning text-dark ms-1">
						{{ heldOrders.seller.length + heldOrders.buyer.length }}
					</span>
				</button>
			</li>
		</ul>

		<div class="tab-content">
			<!-- Таб: Транзакции -->
			<div class="tab-pane fade" :class="{ 'show active': historyTab === 'transactions' }"
				v-if="historyTab === 'transactions'">
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

				<!-- Мобильный список транзакций -->
				<template v-else>
				<div class="d-lg-none m-tx-list">
					<div v-for="transaction in transactions" :key="`m-${transaction.id}`" class="m-tx-item">
						<div class="m-tx-icon">
							<i :class="getTransactionIcon(transaction.type)"></i>
						</div>
						<div class="m-tx-body">
							<div class="m-tx-title">{{ getTransactionTypeText(transaction.type) }}</div>
							<div v-if="transaction.description" class="m-tx-desc">{{ transaction.description }}</div>
							<div class="m-tx-date">{{ formatDate(transaction.created_at) }}</div>
						</div>
						<div class="m-tx-meta">
							<div class="m-tx-amount" :class="getTransactionColor(transaction.type)">
								<strong
									v-html="getTransactionSign(transaction.type) + formatPrice(Math.abs(transaction.amount))"></strong>
							</div>
							<span class="badge bg-warning"
								v-if="['pending', 'on_hold'].includes(transaction.status)">…</span>
							<span class="badge bg-danger" v-else-if="transaction.status === 'failed'">!</span>
						</div>
					</div>
				</div>

				<!-- Transactions List (десктоп) -->
				<div class="table-responsive d-none d-lg-block">
					<table class="table table-hover">
						<thead>
							<tr class="text-muted">
								<th>Тип</th>
								<th>Описание</th>
								<th>Сумма</th>
								<th>Дата</th>
								<th>Статус</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="transaction in transactions" :key="transaction.id" class="text-muted">
								<td>
									<i :class="getTransactionIcon(transaction.type)"></i>&nbsp;<span>{{
										getTransactionTypeText(transaction.type) }}</span>
								</td>
								<td>{{ transaction.description || '—' }}</td>
								<td :class="getTransactionColor(transaction.type)">
									<strong
										v-html="getTransactionSign(transaction.type) + formatPrice(Math.abs(transaction.amount))">
									</strong>
								</td>
								<td class="text-muted">{{ formatDate(transaction.created_at) }}</td>
								<td>
									<span class="badge bg-success"
										v-if="transaction.status === 'completed'">Завершено</span>
									<span class="badge bg-warning" v-else-if="transaction.status === 'pending'">В
										обработке</span>
									<span class="badge bg-warning" v-else-if="transaction.status === 'on_hold'">На
										удержании</span>
									<span class="badge bg-danger"
										v-else-if="transaction.status === 'failed'">Ошибка</span>
									<span class="badge bg-secondary" v-else>{{ transaction.status }}</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				</template>
			</div>

			<!-- Таб: На удержании -->
			<div class="tab-pane fade" :class="{ 'show active': historyTab === 'held' }" v-if="historyTab === 'held'">
				<!-- Loading State -->
				<div v-if="isLoadingHeldBalance" class="text-center py-4">
					<div class="spinner-border" role="status">
						<span class="visually-hidden">Загрузка...</span>
					</div>
					<p class="text-muted mt-2 mb-0">Загружаем данные об удержании...</p>
				</div>

				<!-- Empty State -->
				<div v-else-if="heldOrders.seller.length === 0 && heldOrders.buyer.length === 0"
					class="text-center py-4">
					<i class="ri-checkbox-circle-line display-4 text-success mb-3"></i>
					<h6>Нет средств на удержании</h6>
					<p class="text-muted mb-0">Все ваши средства доступны</p>
				</div>

				<!-- Held Orders (мобиль) -->
				<template v-else>
				<div class="d-lg-none m-tx-list">
					<div v-for="order in heldOrders.seller" :key="`m-s-${order.id}`" class="m-tx-item">
						<div class="m-tx-icon"><i class="ri-money-dollar-circle-line"></i></div>
						<div class="m-tx-body">
							<div class="m-tx-title">Продажа · #{{ order.order_number }}</div>
							<div class="m-tx-desc">{{ order.buyer_name || '—' }}</div>
							<div class="m-tx-date">До {{ formatSettlementDate(order.settlement_date) }}</div>
						</div>
						<div class="m-tx-meta">
							<div class="m-tx-amount text-success">
								<strong v-html="'+&nbsp;' + formatPrice(order.total_amount)"></strong>
							</div>
						</div>
					</div>
					<div v-for="order in heldOrders.buyer" :key="`m-b-${order.id}`" class="m-tx-item">
						<div class="m-tx-icon"><i class="ri-shopping-cart-line"></i></div>
						<div class="m-tx-body">
							<div class="m-tx-title">Покупка · #{{ order.order_number }}</div>
							<div class="m-tx-desc">{{ order.seller_name || '—' }}</div>
							<div class="m-tx-date">До {{ formatSettlementDate(order.settlement_date) }}</div>
						</div>
						<div class="m-tx-meta">
							<div class="m-tx-amount text-danger">
								<strong v-html="'−&nbsp;' + formatPrice(order.total_amount)"></strong>
							</div>
						</div>
					</div>
				</div>

				<!-- Held Orders List (десктоп) -->
				<div class="table-responsive d-none d-lg-block">
					<table class="table table-hover">
						<thead>
							<tr class="text-muted">
								<th>Тип</th>
								<th>Описание</th>
								<th>Сумма</th>
								<th>Дата</th>
								<th>Разблокировка</th>
							</tr>
						</thead>
						<tbody>
							<!-- Продажи на удержании -->
							<tr v-for="order in heldOrders.seller" :key="'s-' + order.id" class="text-muted">
								<td>
									<i class="ri-money-dollar-circle-line"></i>&nbsp;<span>Продажа</span>
								</td>
								<td>Заказ #{{ order.order_number }} · {{ order.buyer_name || '—' }}</td>
								<td class="text-success">
									<strong v-html="'+&nbsp;' + formatPrice(order.total_amount)"></strong>
								</td>
								<td class="text-muted">{{ formatDate(order.created_at) }}</td>
								<td>
									<span class="badge bg-warning text-dark">
										<i class="ri-time-line me-1"></i>
										{{ formatSettlementDate(order.settlement_date) }}
									</span>
								</td>
							</tr>
							<!-- Покупки на удержании -->
							<tr v-for="order in heldOrders.buyer" :key="'b-' + order.id" class="text-muted">
								<td>
									<i class="ri-shopping-cart-line"></i>&nbsp;<span>Покупка</span>
								</td>
								<td>Заказ #{{ order.order_number }} · {{ order.seller_name || '—' }}</td>
								<td class="text-danger">
									<strong v-html="'−&nbsp;' + formatPrice(order.total_amount)"></strong>
								</td>
								<td class="text-muted">{{ formatDate(order.created_at) }}</td>
								<td>
									<span class="badge bg-warning text-dark">
										<i class="ri-time-line me-1"></i>
										{{ formatSettlementDate(order.settlement_date) }}
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				</template>
			</div>

			<!-- Таб: История бонусов -->
			<div class="tab-pane fade" :class="{ 'show active': historyTab === 'bonus' }" v-if="historyTab === 'bonus'">
				<!-- Loading State -->
				<div v-if="isLoadingBonusTransactions" class="text-center py-4">
					<div class="spinner-border" role="status">
						<span class="visually-hidden">Загрузка...</span>
					</div>
					<p class="text-muted mt-2 mb-0">Загружаем историю бонусов...</p>
				</div>

				<!-- Empty State -->
				<div v-else-if="bonusTransactions.length === 0" class="text-center py-4">
					<i class="ri-gift-line display-4 text-muted mb-3"></i>
					<h6>История бонусов пуста</h6>
					<p class="text-muted mb-0">Здесь будут отображаться все операции с бонусным балансом</p>
				</div>

				<!-- Bonus Transactions (мобиль) -->
				<template v-else>
				<div class="d-lg-none m-tx-list">
					<div v-for="tx in bonusTransactions" :key="`m-${tx.id}`" class="m-tx-item">
						<div class="m-tx-icon">
							<i :class="tx.type === 'credit' ? 'ri-add-circle-line' : 'ri-indeterminate-circle-line'"></i>
						</div>
						<div class="m-tx-body">
							<div class="m-tx-title">{{ tx.type === 'credit' ? 'Начисление' : 'Списание' }}</div>
							<div v-if="tx.description" class="m-tx-desc">{{ tx.description }}</div>
							<div class="m-tx-date">{{ formatDate(tx.created_at) }}</div>
						</div>
						<div class="m-tx-meta">
							<div class="m-tx-amount" :class="tx.type === 'credit' ? 'text-success' : 'text-danger'">
								<strong
									v-html="(tx.type === 'credit' ? '+&nbsp;' : '−&nbsp;') + formatPrice(Math.abs(tx.amount))"></strong>
							</div>
						</div>
					</div>
				</div>

				<!-- Bonus Transactions List (десктоп) -->
				<div class="table-responsive d-none d-lg-block">
					<table class="table table-hover">
						<thead>
							<tr class="text-muted">
								<th>Тип</th>
								<th>Описание</th>
								<th>Сумма</th>
								<th>Дата</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="tx in bonusTransactions" :key="tx.id" class="text-muted">
								<td>
									<i
										:class="tx.type === 'credit' ? 'ri-add-circle-line text-success' : 'ri-indeterminate-circle-line text-danger'"></i>
									<span class="ms-1">{{ tx.type === 'credit' ? 'Начисление' : 'Списание' }}</span>
								</td>
								<td>{{ tx.description || '—' }}</td>
								<td :class="tx.type === 'credit' ? 'text-success' : 'text-danger'">
									<strong
										v-html="(tx.type === 'credit' ? '+&nbsp;' : '−&nbsp;') + formatPrice(Math.abs(tx.amount))"></strong>
								</td>
								<td class="text-muted">{{ formatDate(tx.created_at) }}</td>
							</tr>
						</tbody>
					</table>
				</div>
				</template>
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
										<input type="number" class="form-control" id="depositAmount"
											v-model="depositForm.amount" :min="minimumDepositAmount"
											:max="maximumDepositAmount" step="1" placeholder="Введите сумму">
										<span class="input-group-text">₽</span>
									</div>
									<div class="form-text">
										Сумма от {{ minimumDepositAmount }} до {{ maximumDepositAmount }} ₽
									</div>
								</div>

								<!-- Промокод -->
								<div class="col-12">
									<label for="depositPromocode" class="form-label">Промокод</label>
									<div class="input-group">
										<input type="text" class="form-control" id="depositPromocode"
											v-model="depositForm.promocode"
											placeholder="Введите промокод (необязательно)"
											:disabled="depositForm.validatingPromocode">
										<span v-if="depositForm.validatingPromocode" class="input-group-text">
											<span class="spinner-border spinner-border-sm"></span>
										</span>
									</div>
									<div v-if="depositForm.promocodeResult" class="mt-2">
										<div v-if="depositForm.promocodeResult.valid" class="text-success small">
											<i class="ri-check-line me-1"></i>
											{{ depositForm.promocodeResult.message }}
											<span v-if="depositForm.promocodeResult.bonus_amount">
												(+{{ depositForm.promocodeResult.bonus_amount }} ₽ бонус)
											</span>
										</div>
										<div v-else class="text-danger small">
											<i class="ri-close-line me-1"></i>
											{{ depositForm.promocodeResult.message }}
										</div>
									</div>
									<div v-if="depositForm.promocodeResult?.valid && depositForm.promocodeResult?.bonus_amount"
										class="alert alert-info mt-2 mb-0 py-2 small">
										<i class="ri-information-line me-1"></i>
										Бонусы начисляются на отдельный бонусный баланс и могут быть использованы только
										для открытия кейсов.
									</div>
								</div>

								<!-- Способ оплаты -->
								<div class="col-12" v-if="availablePaymentMethods.length > 1">
									<label class="form-label">Способ оплаты</label>
									<div class="d-flex flex-column gap-2">
										<label v-for="method in availablePaymentMethods" :key="method.value"
											class="payment-method-option mb-0" style="cursor: pointer;">
											<input type="radio" :value="method.value" v-model="depositForm.payment_type"
												class="d-none">
											<div class="d-flex align-items-center p-3 border rounded"
												:class="depositForm.payment_type === method.value ? 'border-primary bg-primary bg-opacity-10' : 'border-secondary'">
												<i :class="method.icon" class="me-3" style="font-size: 1.5rem;"></i>
												<span>{{ method.label }}</span>
												<i v-if="depositForm.payment_type === method.value"
													class="ri-check-line ms-auto text-primary"></i>
											</div>
										</label>
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
							<button type="button" class="btn theme-btn flex-fill" @click="createPaymentForm"
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

		<!-- Модальное окно активации промокода -->
		<div class="modal fade" id="promocode-activate" tabindex="-1" aria-labelledby="promocodeActivateLabel"
			aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="promocodeActivateLabel">
							<i class="ri-coupon-line me-2"></i>Активация промокода
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div class="mb-3">
							<label for="promocodeInput" class="form-label">Промокод</label>
							<input type="text" class="form-control" id="promocodeInput" v-model="promocodeActivate.code"
								placeholder="Введите промокод" :disabled="promocodeActivate.loading"
								@keyup.enter="activatePromocode">
						</div>
						<div v-if="promocodeActivate.message" class="small"
							:class="promocodeActivate.success ? 'text-success' : 'text-danger'">
							<i :class="promocodeActivate.success ? 'ri-check-line' : 'ri-close-line'" class="me-1"></i>
							{{ promocodeActivate.message }}
						</div>
					</div>
					<div class="modal-footer d-flex justify-content-center align-items-center gap-2">
						<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
						<button type="button" class="btn theme-btn" @click="activatePromocode"
							:disabled="!promocodeActivate.code || promocodeActivate.loading">
							<span v-if="promocodeActivate.loading" class="spinner-border spinner-border-sm me-1"></span>
							Активировать
						</button>
					</div>
				</div>
			</div>
		</div>

	</div>
</template>

<script>
import axios from 'axios';
import { formatPrice } from '../../../shared/utils/helpers';

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
				card_payment_enabled: true,
				test_payment_enabled: false
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
				payment_type: 'card',
				promocode: '',
				promocodeResult: null,
				validatingPromocode: false,
				processing: false,
				error: null
			},

			// Данные о холде
			heldBalance: {
				seller: 0,
				buyer: 0
			},
			heldOrders: {
				seller: [],
				buyer: []
			},
			isLoadingHeldBalance: false,

			// Данные о бонусах
			bonusTransactions: [],
			isLoadingBonusTransactions: false,

			// Активный таб истории
			historyTab: 'transactions',

			// Таймер для debounce промокода
			promocodeDebounceTimer: null,

			// Активация промокода
			promocodeActivate: {
				code: '',
				loading: false,
				message: null,
				success: false
			}
		}
	},

	watch: {
		'depositForm.amount'() {
			this.debouncedValidatePromocode();
		},
		'depositForm.promocode'() {
			this.debouncedValidatePromocode();
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
		testPaymentEnabled() {
			return this.depositSettings.test_payment_enabled;
		},
		isValidAmount() {
			const amount = parseFloat(this.depositForm.amount);
			return amount >= this.minimumDepositAmount && amount <= this.maximumDepositAmount;
		},
		availablePaymentMethods() {
			const methods = [];
			if (this.cardPaymentEnabled) {
				methods.push({ value: 'card', label: 'Банковская карта', icon: 'ri-bank-card-line' });
			}
			if (this.testPaymentEnabled) {
				methods.push({ value: 'test', label: 'Тестовый платеж', icon: 'ri-bug-line' });
			}
			return methods;
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

		async loadHeldBalance() {
			if (this.isLoadingHeldBalance) return;

			this.isLoadingHeldBalance = true;
			try {
				const response = await axios.get('/api/profile/held-balance');
				if (response.data.success) {
					this.heldBalance.seller = response.data.data.seller_held_balance || 0;
					this.heldBalance.buyer = response.data.data.buyer_held_balance || 0;
					this.heldOrders.seller = response.data.data.seller_held_orders || [];
					this.heldOrders.buyer = response.data.data.buyer_held_orders || [];
				}
			} catch (error) {
				console.error('Failed to load held balance:', error);
			} finally {
				this.isLoadingHeldBalance = false;
			}
		},

		async loadBonusTransactions() {
			if (this.isLoadingBonusTransactions) return;

			this.isLoadingBonusTransactions = true;
			try {
				const response = await axios.get('/api/profile/bonus-transactions');
				this.bonusTransactions = response.data.data || [];
			} catch (error) {
				console.error('Failed to load bonus transactions:', error);
				window.toast?.error('Не удалось загрузить историю бонусов');
			} finally {
				this.isLoadingBonusTransactions = false;
			}
		},

		formatSettlementDate(dateString) {
			if (!dateString) return '—';
			const date = new Date(dateString);
			const now = new Date();
			const diff = date - now;

			if (diff <= 0) return 'Скоро';

			const days = Math.floor(diff / (1000 * 60 * 60 * 24));
			const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

			if (days > 0) {
				return `${days} д. ${hours} ч.`;
			}
			if (hours > 0) {
				const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
				return `${hours} ч. ${minutes} мин.`;
			}

			const minutes = Math.floor(diff / (1000 * 60));
			return `${minutes} мин.`;
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
				'auction_refund': 'Возврат со ставки',
				'promocode': 'Промокод',
				'case_purchase': 'Открытие кейса',
				'virtual_item_sale': 'Продажа предмета',
				'upgrade_bet': 'Апгрейд'
			};
			return types[type] || type;
		},

		getTransactionSign(type) {
			// Операции, которые уменьшают баланс (-)
			const negativeTypes = ['purchase', 'withdrawal', 'fee', 'auction_bid', 'case_purchase', 'upgrade_bet'];
			// Операции, которые увеличивают баланс (+)
			const positiveTypes = ['sale', 'deposit', 'refund', 'auction_refund', 'promocode', 'virtual_item_sale'];

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
				'deposit': 'ri-add-circle-line',
				'promocode': 'ri-coupon-line',
				'case_purchase': 'ri-box-3-line',
				'virtual_item_sale': 'ri-hand-coin-line',
				'upgrade_bet': 'ri-arrow-up-circle-line'
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
				'deposit': 'text-success',
				'promocode': 'text-success',
				'case_purchase': 'text-danger',
				'virtual_item_sale': 'text-success',
				'upgrade_bet': 'text-danger'
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
				const successUrl = `${baseUrl}/profile?payment=success#balance`;
				const failUrl = `${baseUrl}/profile?payment=failed#balance`;

				const requestData = {
					amount: parseFloat(this.depositForm.amount),
					payment_type: this.depositForm.payment_type,
					success_url: successUrl,
					fail_url: failUrl
				};

				// Добавляем промокод если он валиден
				if (this.depositForm.promocode && this.depositForm.promocodeResult?.valid) {
					requestData.promocode = this.depositForm.promocode;
				}

				const response = await axios.post('/api/deposit/payment-form', requestData);

				if (response.data.success) {
					// Закрываем модальное окно
					this.closeModal();

					// Тестовый платеж завершается сразу
					if (response.data.status === 'completed') {
						this.onPaymentSuccess(response.data.amount);
						window.toast?.success(response.data.message || 'Платеж успешно завершен!');
					} else {
						// Обычный платеж - редирект на платежную форму
						this.submitPaymentForm(response.data.payment_form_url, successUrl, failUrl);

						// Начинаем периодическую проверку статуса платежа
						this.startPaymentStatusCheck(response.data.payment_id);
					}
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
			// Debug: проверяем параметры
			console.log('Payment form submission:', {
				paymentFormUrl,
				successUrl,
				failUrl
			});

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
			// Очищаем таймер debounce
			if (this.promocodeDebounceTimer) {
				clearTimeout(this.promocodeDebounceTimer);
			}

			this.depositForm.amount = '';
			this.depositForm.payment_type = 'card';
			this.depositForm.promocode = '';
			this.depositForm.promocodeResult = null;
			this.depositForm.validatingPromocode = false;
			this.depositForm.error = null;
			this.depositForm.processing = false;
		},

		debouncedValidatePromocode() {
			// Очищаем предыдущий таймер
			if (this.promocodeDebounceTimer) {
				clearTimeout(this.promocodeDebounceTimer);
			}

			// Если нет промокода - очищаем результат
			if (!this.depositForm.promocode) {
				this.depositForm.promocodeResult = null;
				return;
			}

			// Запускаем проверку с задержкой 500мс
			this.promocodeDebounceTimer = setTimeout(() => {
				this.validatePromocode();
			}, 500);
		},

		async validatePromocode() {
			// Если нет промокода или суммы - не валидируем
			if (!this.depositForm.promocode) {
				this.depositForm.promocodeResult = null;
				return;
			}

			if (!this.depositForm.amount || !this.isValidAmount) {
				this.depositForm.promocodeResult = {
					valid: false,
					message: 'Введите корректную сумму пополнения'
				};
				return;
			}

			this.depositForm.validatingPromocode = true;

			try {
				const response = await axios.post('/api/deposit/validate-promocode', {
					code: this.depositForm.promocode,
					amount: parseFloat(this.depositForm.amount)
				});

				this.depositForm.promocodeResult = {
					valid: response.data.valid,
					message: response.data.message,
					bonus_amount: response.data.bonus_amount
				};
			} catch (error) {
				this.depositForm.promocodeResult = {
					valid: false,
					message: error.response?.data?.message || 'Ошибка проверки промокода'
				};
			} finally {
				this.depositForm.validatingPromocode = false;
			}
		},

		async activatePromocode() {
			if (!this.promocodeActivate.code || this.promocodeActivate.loading) return;

			this.promocodeActivate.loading = true;
			this.promocodeActivate.message = null;

			try {
				const response = await axios.post('/api/deposit/activate-promocode', {
					code: this.promocodeActivate.code
				});

				if (response.data.success) {
					this.promocodeActivate.success = true;
					this.promocodeActivate.message = `Промокод активирован! Зачислено ${response.data.amount} ₽`;
					this.promocodeActivate.code = '';

					// Обновляем баланс
					this.client.balance = response.data.balance;
					window.dispatchEvent(new CustomEvent('balance-updated', {
						detail: { main: response.data.balance }
					}));

					// Перезагружаем транзакции
					this.loadTransactions();
				}
			} catch (error) {
				this.promocodeActivate.success = false;
				this.promocodeActivate.message = error.response?.data?.message || 'Ошибка активации промокода';
			} finally {
				this.promocodeActivate.loading = false;
			}
		},

		checkPaymentStatus() {
			// Получаем URL параметры из search (до хэша)
			let urlParams = new URLSearchParams(window.location.search);
			let paymentStatus = urlParams.get('payment');

			// Если не найдено в search, ищем в hash (после хэша)
			if (!paymentStatus && window.location.hash) {
				const hashParts = window.location.hash.split('?');
				if (hashParts.length > 1) {
					// Есть параметры после хэша, например: #balance?payment=success
					const hashParams = new URLSearchParams(hashParts[1]);
					paymentStatus = hashParams.get('payment');
				}
			}

			if (paymentStatus === 'success') {
				// Добавляем задержку для корректного отображения тоста
				setTimeout(() => {
					window.toast?.success('Платеж успешно завершен! Баланс будет обновлен в течение нескольких минут.');
				}, 1500);

				// Обновляем баланс и транзакции
				this.loadTransactions();

				// Очищаем URL параметры
				this.clearPaymentParams();

			} else if (paymentStatus === 'failed') {
				// Показываем уведомление о неуспешной оплате с задержкой
				setTimeout(() => {
					window.toast?.error('Платеж не был завершен. Попробуйте еще раз позже.');
				}, 1500);

				// Очищаем URL параметры
				this.clearPaymentParams();
			}
		},

		clearPaymentParams() {
			// Удаляем параметр payment из URL без перезагрузки страницы
			const url = new URL(window.location);

			// Удаляем из search параметров (до хэша)
			url.searchParams.delete('payment');

			// Удаляем из hash параметров (после хэша)
			if (url.hash) {
				const hashParts = url.hash.split('?');
				if (hashParts.length > 1) {
					const hashParams = new URLSearchParams(hashParts[1]);
					hashParams.delete('payment');

					// Собираем hash обратно
					if (hashParams.toString()) {
						url.hash = hashParts[0] + '?' + hashParams.toString();
					} else {
						url.hash = hashParts[0];
					}
				}
			}

			window.history.replaceState({}, '', url);
		}

	},

	async mounted() {
		// Проверяем статус платежа
		this.checkPaymentStatus();

		// Слушаем события изменения валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);

		// Загружаем данные
		await Promise.all([
			this.loadTransactions(),
			this.loadSalesStats(),
			this.loadHeldBalance()
		]);
	},

	beforeUnmount() {
		// Удаляем слушатель при уничтожении компонента
		window.removeEventListener('currency-changed', this.handleCurrencyChange);

		// Очищаем таймер debounce
		if (this.promocodeDebounceTimer) {
			clearTimeout(this.promocodeDebounceTimer);
		}
	}
}
</script>