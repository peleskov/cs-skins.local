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
						<h2 class="text-primary">{{ formatPrice(client.balance) }}</h2>
						<p class="text-muted mb-0">Доступно для покупок</p>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body text-center">
						<i class="ri-exchange-dollar-line display-4 text-success mb-3"></i>
						<h5 class="card-title">Заработано</h5>
						<h2 class="text-success">0.00 ₽</h2>
						<p class="text-muted mb-0">Общая сумма продаж</p>
					</div>
				</div>
			</div>
		</div>

		<!-- Операции с балансом -->
		<div class="row g-4 mb-4">
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body">
						<h5 class="card-title">
							<i class="ri-add-circle-line me-2 text-success"></i>
							Пополнение баланса
						</h5>
						<p class="text-muted">Пополните баланс для покупки предметов на маркетплейсе</p>
						<button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#balance-refill">
							<i class="ri-add-line me-2"></i>Пополнить баланс
						</button>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card h-100">
					<div class="card-body">
						<h5 class="card-title">
							<i class="ri-subtract-line me-2 text-warning"></i>
							Вывод средств
						</h5>
						<p class="text-muted">Выведите заработанные средства на банковскую карту</p>
						<button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#balance-withdraw">
							<i class="ri-bank-card-line me-2"></i>Вывести средства
						</button>
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
				<div class="text-center py-4">
					<i class="ri-file-list-line display-4 text-muted mb-3"></i>
					<h6>История операций пуста</h6>
					<p class="text-muted mb-0">Здесь будут отображаться все ваши финансовые операции</p>
				</div>
			</div>
		</div>

		<!-- Модальное окно пополнения -->
		<div class="modal fade" id="balance-refill" tabindex="-1" aria-labelledby="balanceRefillLabel" aria-hidden="true">
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
							<p class="text-muted">Функция пополнения баланса находится в разработке и будет доступна в ближайшее время.</p>
							<div class="alert alert-info">
								<strong>Планируемые способы пополнения:</strong>
								<ul class="list-unstyled mt-2 mb-0">
									<li>• Банковские карты</li>
									<li>• Электронные кошельки</li>
									<li>• Криптовалюты</li>
									<li>• Мобильные платежи</li>
								</ul>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Понятно</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Модальное окно вывода -->
		<div class="modal fade" id="balance-withdraw" tabindex="-1" aria-labelledby="balanceWithdrawLabel" aria-hidden="true">
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
							<p class="text-muted">Функция вывода средств находится в разработке и будет доступна в ближайшее время.</p>
							<div class="alert alert-warning">
								<strong>Условия вывода:</strong>
								<ul class="list-unstyled mt-2 mb-0">
									<li>• Минимальная сумма: 50 ₽</li>
									<li>• Комиссия: 3.5% + 50₽ (до 3334₽)</li>
									<li>• Комиссия: 5% (от 3334₽)</li>
									<li>• Требуется верификация</li>
								</ul>
							</div>
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
export default {
	name: 'ProfileBalance',
	props: {
		client: {
			type: Object,
			required: true
		}
	},
	methods: {
		formatNumber(number, decimals = 2) {
			return Number(number).toFixed(decimals);
		}
	}
}
</script>