<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Настройки профиля</h3>
		</div>

		<!-- Настройки безопасности -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0">
					<i class="ri-shield-line me-2"></i>
					Безопасность
				</h5>
			</div>
			<div class="card-body">
				<div class="row g-4">
					<div class="col-md-6">
						<div class="setting-item">
							<div class="d-flex align-items-center mb-2">
								<i class="ri-key-line me-2"></i>
								<strong>Пароль аккаунта</strong>
							</div>
							<p class="text-muted mb-3">Смена пароля для входа в аккаунт</p>
							<button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#change-password">
								Изменить пароль
							</button>
						</div>
					</div>
					<div class="col-md-6">
						<div class="setting-item">
							<div class="d-flex align-items-center mb-2">
								<i class="ri-lock-line me-2"></i>
								<strong>Платежный пароль</strong>
							</div>
							<p class="text-muted mb-3">Дополнительная защита для финансовых операций</p>
							<button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#payment-password">
								{{ client.has_payment_password ? 'Изменить' : 'Установить' }}
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Настройки уведомлений -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0">
					<i class="ri-notification-line me-2"></i>
					Уведомления
				</h5>
			</div>
			<div class="card-body">
				<div class="row g-4">
					<div class="col-md-6">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="emailNotifications" checked>
							<label class="form-check-label" for="emailNotifications">
								<strong>Email уведомления</strong>
							</label>
							<p class="text-muted small mt-1">Получать уведомления о продажах и покупках на email</p>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="telegramNotifications" :checked="client.is_verified">
							<label class="form-check-label" for="telegramNotifications">
								<strong>Telegram уведомления</strong>
							</label>
							<p class="text-muted small mt-1">Получать уведомления в Telegram</p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Настройки торговли -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0">
					<i class="ri-exchange-line me-2"></i>
					Торговля
				</h5>
			</div>
			<div class="card-body">
				<div class="row g-4">
					<div class="col-md-6">
						<div class="setting-item">
							<div class="d-flex align-items-center mb-2">
								<i class="ri-time-line me-2"></i>
								<strong>Автоматическое принятие трейдов</strong>
							</div>
							<p class="text-muted mb-3">Автоматически принимать трейды при продаже боту</p>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="autoAcceptTrades" checked>
								<label class="form-check-label" for="autoAcceptTrades">
									Включено
								</label>
							</div>
						</div>
					</div>
					<div class="col-md-6">
						<div class="setting-item">
							<div class="d-flex align-items-center mb-2">
								<i class="ri-timer-line me-2"></i>
								<strong>Таймаут трейдов</strong>
							</div>
							<p class="text-muted mb-3">Время ожидания принятия трейда</p>
							<select class="form-select">
								<option value="5">5 минут</option>
								<option value="10">10 минут</option>
								<option value="15">15 минут</option>
								<option value="30">30 минут</option>
							</select>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Настройки приватности -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0">
					<i class="ri-eye-line me-2"></i>
					Приватность
				</h5>
			</div>
			<div class="card-body">
				<div class="row g-4">
					<div class="col-md-6">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="publicProfile">
							<label class="form-check-label" for="publicProfile">
								<strong>Публичный профиль</strong>
							</label>
							<p class="text-muted small mt-1">Показывать статистику торговли другим пользователям</p>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="showInventory">
							<label class="form-check-label" for="showInventory">
								<strong>Показывать инвентарь</strong>
							</label>
							<p class="text-muted small mt-1">Разрешить другим видеть ваш инвентарь</p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Опасная зона -->
		<div class="card border-danger">
			<div class="card-header bg-danger text-white">
				<h5 class="mb-0">
					<i class="ri-error-warning-line me-2"></i>
					Опасная зона
				</h5>
			</div>
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<strong>Удаление аккаунта</strong>
						<p class="text-muted mb-0">Навсегда удалить ваш аккаунт и все связанные данные</p>
					</div>
					<button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-account">
						Удалить аккаунт
					</button>
				</div>
			</div>
		</div>

		<!-- Модальные окна -->
		
		<!-- Смена пароля -->
		<div class="modal fade" id="change-password" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="changePasswordLabel">
							<i class="ri-key-line me-2"></i>Изменить пароль
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body text-center">
						<div class="py-4">
							<i class="ri-settings-3-line display-4 text-muted mb-3"></i>
							<h4>В разработке</h4>
							<p class="text-muted">Функция смены пароля находится в разработке.</p>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Платежный пароль -->
		<div class="modal fade" id="payment-password" tabindex="-1" aria-labelledby="paymentPasswordLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="paymentPasswordLabel">
							<i class="ri-lock-line me-2"></i>{{ client.has_payment_password ? 'Изменить' : 'Установить' }} платежный пароль
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body text-center">
						<div class="py-4">
							<i class="ri-settings-3-line display-4 text-muted mb-3"></i>
							<h4>В разработке</h4>
							<p class="text-muted">Функция платежного пароля находится в разработке.</p>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Удаление аккаунта -->
		<div class="modal fade" id="delete-account" tabindex="-1" aria-labelledby="deleteAccountLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header bg-danger text-white">
						<h5 class="modal-title" id="deleteAccountLabel">
							<i class="ri-error-warning-line me-2"></i>Удаление аккаунта
						</h5>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div class="alert alert-danger">
							<i class="ri-error-warning-line me-2"></i>
							<strong>Внимание!</strong> Это действие необратимо.
						</div>
						<p>При удалении аккаунта будут безвозвратно удалены:</p>
						<ul>
							<li>Вся информация профиля</li>
							<li>История торговли</li>
							<li>Активные листинги</li>
							<li>Накопленная статистика</li>
						</ul>
						<p class="text-muted">Средства с баланса необходимо вывести заранее.</p>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
						<button type="button" class="btn btn-danger" disabled>
							В разработке
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'ProfileSettings',
	props: {
		client: {
			type: Object,
			required: true
		}
	}
}
</script>

<style scoped>
.setting-item {
	padding: 1rem;
	border: 1px solid #e9ecef;
	border-radius: 0.5rem;
	background-color: #f8f9fa;
}
</style>