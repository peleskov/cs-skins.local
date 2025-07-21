<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Информация профиля</h3>
		</div>
		<ul class="profile-details-list">
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-user-3-fill"></i>
						<span>Имя :</span>
					</div>
					<h6>{{ client.name }}</h6>
				</div>
			</li>
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-mail-fill"></i>
						<span>Email :</span>
					</div>
					<h6>
						{{ client.email || 'Не указан' }}
						<span v-if="client.email && client.email_verified_at"
							class="badge bg-success-subtle ms-2">
							Подтвержден
						</span>
						<span v-else-if="client.email && !client.email_verified_at"
							class="badge bg-warning ms-2">
							Не подтвержден
						</span>
					</h6>
				</div>
				<div class="d-flex gap-2">
					<a href="#email" class="btn theme-outline" data-bs-toggle="modal">
						{{ client.email ? 'Изменить' : 'Добавить' }}
					</a>
					<div v-if="client.email && !client.email_verified_at" class="d-flex flex-column gap-1">
						<button class="btn theme-outline" ref="resendEmailBtn" @click="resendVerification"
							:disabled="!canResendVerification">
							<span class="btn-text">
								<span v-if="canResendVerification">Отправить письмо</span>
								<span v-else>Отправить повторно через {{ timeUntilCanResend }}</span>
							</span>
						</button>
					</div>
				</div>
			</li>
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-gamepad-line"></i>
						<span>Steam ID :</span>
					</div>
					<h6>{{ client.steam_id }}</h6>
				</div>
			</li>
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-exchange-line"></i>
						<span>Trade URL :</span>
					</div>
					<h6>
						<span v-if="client.steam_trade_url">
							<span class="trade-url-text" :data-url="client.steam_trade_url"
								style="cursor: pointer;" title="Нажмите для копирования"
								@click="copyTradeUrl">
								{{ limitString(client.steam_trade_url, 50) }}
								<i class="ri-file-copy-line ms-1"></i>
							</span>
							<span class="badge bg-success-subtle ms-2">Активен</span>
						</span>
						<span v-else class="badge bg-warning ms-2">Не указан</span>
					</h6>
				</div>
				<a href="#trade-url" class="btn theme-outline mt-0" data-bs-toggle="modal">
					{{ client.steam_trade_url ? 'Изменить' : 'Добавить' }}
				</a>
			</li>
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-wallet-3-line"></i>
						<span>Баланс :</span>
					</div>
					<h6>{{ formatNumber(client.balance, 2) }} ₽</h6>
				</div>
				<a href="#balance-refill" class="btn theme-outline mt-0"
					data-bs-toggle="modal">Пополнить</a>
			</li>
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-telegram-fill"></i>
						<span>Верификация :</span>
					</div>
					<h6>
						<template v-if="client.is_verified && client.telegram_id">
							{{ client.telegram_username ? '@' + client.telegram_username : 'Telegram User'
							}}
							<small>({{ client.telegram_id }})</small>
							<span class="badge bg-success-subtle ms-2">Верифицирован</span>
						</template>
						<span v-else-if="client.is_verified">
							Пройдена
							<span class="badge bg-success-subtle ms-2">Верифицирован</span>
						</span>
						<span v-else class="badge bg-warning ms-2">
							Не пройдена
						</span>
					</h6>
				</div>
				<a v-if="client.is_verified && client.telegram_id" href="#telegram-unlink"
					class="btn theme-outline mt-0" data-bs-toggle="modal">
					Отвязать Telegram
				</a>
				<div v-else-if="!client.is_verified && !client.telegram_id"
					id="telegram-login-widget-inline"></div>
				<span v-else-if="client.telegram_id && !client.is_verified" class="badge bg-success-subtle">
					Telegram подключен
				</span>
			</li>
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-calendar-line"></i>
						<span>Дата регистрации :</span>
					</div>
					<h6>{{ formatDate(client.created_at) }}</h6>
				</div>
			</li>
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-computer-line"></i>
						<span>Токен расширения :</span>
					</div>
					<h6>
						<span v-if="extensionToken" class="token-text" 
							:data-token="extensionToken"
							style="cursor: pointer; font-family: monospace; font-size: 0.9em;"
							title="Нажмите для копирования"
							@click="copyExtensionToken">
							{{ limitString(extensionToken, 20) }}
							<i class="ri-file-copy-line ms-1"></i>
						</span>
						<span v-else class="badge bg-warning ms-2">Не сгенерирован</span>
					</h6>
				</div>
				<div class="d-flex gap-2">
					<button v-if="!extensionToken" class="btn theme-outline mt-0" @click="generateExtensionToken">
						Сгенерировать
					</button>
					<button v-else class="btn theme-outline mt-0" data-bs-toggle="modal" data-bs-target="#regenerate-token">
						Перегенерировать
					</button>
				</div>
			</li>
		</ul>

		<!-- Email Modal -->
		<div class="modal address-details-modal fade" id="email" tabindex="-1" aria-labelledby="emailModalLabel"
			aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<form action="/profile/update-email" method="POST">
						<input type="hidden" name="_token" :value="csrfToken">
						<div class="modal-header">
							<h1 class="modal-title fs-5" id="emailModalLabel">{{ client.email ? 'Изменить Email' :
								'Добавить Email' }}</h1>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="email-input" class="form-label">Email адрес</label>
								<input type="email" class="form-control" id="email-input" name="email"
									:value="client.email" placeholder="example@mail.com" required>
								<small class="text-muted">На этот адрес будут приходить важные уведомления</small>
								<small v-if="client.email" class="text-info d-block mt-2">
									<i class="ri-information-line"></i> При изменении email потребуется повторная
									верификация
								</small>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
							<button type="submit" class="btn theme-btn mt-0">Сохранить</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- Trade URL Modal -->
		<div class="modal address-details-modal fade" id="trade-url" tabindex="-1" aria-labelledby="tradeUrlModalLabel"
			aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<form @submit.prevent="updateTradeUrl" id="trade-url-form">
						<input type="hidden" name="_token" :value="csrfToken">
						<div class="modal-header">
							<h1 class="modal-title fs-5" id="tradeUrlModalLabel">{{ client.steam_trade_url ? 'Изменить Trade URL' : 'Добавить Trade URL' }}</h1>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="trade-url-input" class="form-label">Steam Trade URL</label>
								<input type="url" class="form-control" id="trade-url-input" name="trade_url"
									:value="client.steam_trade_url"
									placeholder="https://steamcommunity.com/tradeoffer/new/?partner=123456&token=abcdef"
									required>
								<small class="text-muted">
									Найдите Trade URL в настройках Steam: Настройки → Конфиденциальность → Торговые
									предложения
								</small>
								<div id="trade-url-validation" class="mt-2"></div>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
							<button type="submit" class="btn theme-btn mt-0" id="save-trade-url-btn">Сохранить</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- Balance Modal -->
		<div class="modal address-details-modal fade" id="balance-refill" tabindex="-1"
			aria-labelledby="balanceRefillLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h1 class="modal-title fs-5" id="balanceRefillLabel">Пополнение баланса</h1>
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
						<button type="button" class="btn theme-btn mt-0" data-bs-dismiss="modal">Понятно</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Telegram Unlink Modal -->
		<div class="modal address-details-modal fade" id="telegram-unlink" tabindex="-1"
			aria-labelledby="telegramUnlinkLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h1 class="modal-title fs-5" id="telegramUnlinkLabel">Отвязать Telegram</h1>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p>Вы уверены, что хотите отвязать Telegram аккаунт?</p>
						<small class="text-info d-block mt-2">
							<i class="ri-information-line"></i> При отвязке Telegram статус верификации будет сброшен.
						</small>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
						<form action="/profile/telegram/unlink" method="POST" class="d-inline">
							<input type="hidden" name="_token" :value="csrfToken">
							<button type="submit" class="btn btn-danger mt-0">Отвязать</button>
						</form>
					</div>
				</div>
			</div>
		</div>

		<!-- Regenerate Extension Token Modal -->
		<div class="modal address-details-modal fade" id="regenerate-token" tabindex="-1"
			aria-labelledby="regenerateTokenLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h1 class="modal-title fs-5" id="regenerateTokenLabel">Перегенерировать токен</h1>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p>Вы уверены, что хотите перегенерировать токен расширения?</p>
						<small class="text-warning d-block mt-2">
							<i class="ri-alert-line"></i> <strong>Внимание!</strong> Старый токен перестанет работать, и расширение нужно будет переподключить с новым токеном.
						</small>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
						<button type="button" class="btn btn-danger mt-0" @click="confirmRegenerateToken">Перегенерировать</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { getApiHeaders, handleApiError } from '../../utils/helpers';

export default {
	name: 'ProfileInfo',
	props: {
		client: {
			type: Object,
			required: true
		},
		telegramBotName: {
			type: String,
			default: ''
		}
	},
	data() {
		return {
			timeUntilCanResend: '',
			canResendVerification: true,
			extensionToken: this.client.extension_token || null,
		}
	},
	computed: {
		csrfToken() {
			return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
		}
	},
	methods: {
		async updateTradeUrl() {
			const tradeUrlInput = document.getElementById('trade-url-input');
			const tradeUrl = tradeUrlInput.value;

			if (!tradeUrl) {
				window.toast.error('Введите Trade URL');
				return;
			}

			try {
				const response = await fetch('/profile/update-trade-url', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json'
					},
					body: JSON.stringify({
						trade_url: tradeUrl
					})
				});
				
				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }


				const data = await response.json();

				if (data.success) {
					// Эмитим событие для обновления client в родительском компоненте
					this.$emit('update-client', { steam_trade_url: tradeUrl });
					window.toast.success(data.message);
					
					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('trade-url'));
					if (modal) {
						modal.hide();
					}
				} else {
					window.toast.error(data.message || 'Ошибка при сохранении Trade URL');
				}
			} catch (error) {
				console.error('Trade URL update error:', error);
				window.toast.error(handleApiError(error));
			}
		},

		async resendVerification() {
			if (!this.canResendVerification) return;

			// Блокируем кнопку
			this.canResendVerification = false;
			const btnText = this.$refs.resendEmailBtn?.querySelector('.btn-text');
			const originalText = btnText?.textContent;
			if (btnText) btnText.textContent = 'Отправка...';

			try {
				const response = await fetch('/email/resend', {
					method: 'POST',
					headers: getApiHeaders()
				});
				
				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }


				const data = await response.json();

				if (response.ok) {
					window.toast.success(data.message || 'Письмо отправлено');
					this.startResendTimer(60); // 1 минута
				} else {
					window.toast.error(data.message || 'Не удалось отправить письмо');
					this.canResendVerification = true;
					if (btnText) btnText.textContent = originalText;
				}
			} catch (error) {
				console.error('Resend verification error:', error);
				window.toast.error(handleApiError(error));
				this.canResendVerification = true;
				if (btnText) btnText.textContent = originalText;
			}
		},

		startResendTimer(seconds) {
			this.canResendVerification = false;
			let remainingSeconds = seconds;

			const formatTime = (sec) => {
				if (sec <= 0) return '';
				const minutes = Math.floor(sec / 60);
				const secs = sec % 60;
				if (minutes > 0) {
					return `${minutes} мин ${secs} сек`;
				}
				return `${secs} сек`;
			};

			const timer = setInterval(() => {
				if (remainingSeconds <= 0) {
					this.canResendVerification = true;
					this.timeUntilCanResend = '';
					clearInterval(timer);
				} else {
					this.timeUntilCanResend = formatTime(remainingSeconds);
					remainingSeconds--;
				}
			}, 1000);
		},

		formatNumber(number, decimals = 2) {
			return Number(number).toFixed(decimals);
		},

		limitString(str, limit) {
			if (!str) return '';
			return str.length > limit ? str.substring(0, limit) + '...' : str;
		},

		async copyTradeUrl(event) {
			const url = event.currentTarget.dataset.url;

			try {
				await navigator.clipboard.writeText(url);
				window.toast.success('Trade URL скопирован в буфер обмена');

				// Временно меняем иконку
				const icon = event.currentTarget.querySelector('i');
				const originalClass = icon.className;
				icon.className = 'ri-check-line ms-1 text-success';

				setTimeout(() => {
					icon.className = originalClass;
				}, 2000);

			} catch (err) {
				window.toast.error(handleApiError(error));
				console.error('Failed to copy: ', err);
			}
		},

		formatDate(dateString) {
			if (!dateString) return '';
			const date = new Date(dateString);
			return date.toLocaleDateString('ru-RU');
		},

		loadTelegramWidget() {
			this.$nextTick(() => {
				const widgetContainer = document.getElementById('telegram-login-widget-inline');
				if (widgetContainer && !widgetContainer.hasChildNodes()) {
					console.log('Loading Telegram widget...');

					// Создаем скрипт для Telegram виджета
					const script = document.createElement('script');
					script.async = true;
					script.src = 'https://telegram.org/js/telegram-widget.js?22';
					script.setAttribute('data-telegram-login', this.telegramBotName || window.telegramBotName || 'cs_skins_bot');
					script.setAttribute('data-size', 'medium');
					script.setAttribute('data-userpic', 'false');
					script.setAttribute('data-auth-url', window.location.origin + '/profile/telegram/verify');
					script.setAttribute('data-request-access', 'write');

					widgetContainer.appendChild(script);
				}
			});
		},

		async onTelegramAuth(user) {
			try {
				const response = await fetch('/profile/telegram/verify', {
					method: 'POST',
					headers: getApiHeaders(),
					body: JSON.stringify({
						id: user.id,
						first_name: user.first_name,
						last_name: user.last_name,
						username: user.username,
						photo_url: user.photo_url,
						auth_date: user.auth_date,
						hash: user.hash
					})
				});

				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }

				const data = await response.json();

				if (data.success) {
					window.toast.success('Telegram верификация успешно завершена!');

					// Эмитим событие для обновления client в родительском компоненте
					this.$emit('update-client', {
						telegram_id: user.id,
						telegram_username: user.username,
						is_verified: true
					});

					// Перезагружаем страницу через 1.5 секунды
					setTimeout(() => {
						window.location.reload();
					}, 1500);
				} else {
					window.toast.error(data.message || 'Ошибка при верификации');
				}
			} catch (error) {
				console.error('Error:', error);
				window.toast.error(handleApiError(error));
			}
		}
	},

	mounted() {
		// Загружаем Telegram виджет если пользователь не верифицирован
		if (!this.client.is_verified && !this.client.telegram_id) {
			this.loadTelegramWidget();
		}

		// Устанавливаем глобальную функцию для Telegram callback
		window.onTelegramAuth = this.onTelegramAuth;
	},

	methods: {
		async updateTradeUrl() {
			const tradeUrlInput = document.getElementById('trade-url-input');
			const tradeUrl = tradeUrlInput.value;

			if (!tradeUrl) {
				window.toast.error('Введите Trade URL');
				return;
			}

			try {
				const response = await fetch('/profile/update-trade-url', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json'
					},
					body: JSON.stringify({
						trade_url: tradeUrl
					})
				});
				
				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }


				const data = await response.json();

				if (data.success) {
					// Эмитим событие для обновления client в родительском компоненте
					this.$emit('update-client', { steam_trade_url: tradeUrl });
					window.toast.success(data.message);
					
					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('trade-url'));
					if (modal) {
						modal.hide();
					}
				} else {
					window.toast.error(data.message || 'Ошибка при сохранении Trade URL');
				}
			} catch (error) {
				console.error('Trade URL update error:', error);
				window.toast.error(handleApiError(error));
			}
		},

		async resendVerification() {
			if (!this.canResendVerification) return;

			// Блокируем кнопку
			this.canResendVerification = false;
			const btnText = this.$refs.resendEmailBtn?.querySelector('.btn-text');
			const originalText = btnText?.textContent;
			if (btnText) btnText.textContent = 'Отправка...';

			try {
				const response = await fetch('/email/resend', {
					method: 'POST',
					headers: getApiHeaders()
				});
				
				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }


				const data = await response.json();

				if (response.ok) {
					window.toast.success(data.message || 'Письмо отправлено');
					this.startResendTimer(60); // 1 минута
				} else {
					window.toast.error(data.message || 'Не удалось отправить письмо');
					this.canResendVerification = true;
					if (btnText) btnText.textContent = originalText;
				}
			} catch (error) {
				console.error('Resend verification error:', error);
				window.toast.error(handleApiError(error));
				this.canResendVerification = true;
				if (btnText) btnText.textContent = originalText;
			}
		},

		startResendTimer(seconds) {
			this.canResendVerification = false;
			let remainingSeconds = seconds;

			const formatTime = (sec) => {
				if (sec <= 0) return '';
				const minutes = Math.floor(sec / 60);
				const secs = sec % 60;
				if (minutes > 0) {
					return `${minutes} мин ${secs} сек`;
				}
				return `${secs} сек`;
			};

			const timer = setInterval(() => {
				if (remainingSeconds <= 0) {
					this.canResendVerification = true;
					this.timeUntilCanResend = '';
					clearInterval(timer);
				} else {
					this.timeUntilCanResend = formatTime(remainingSeconds);
					remainingSeconds--;
				}
			}, 1000);
		},

		formatNumber(number, decimals = 2) {
			return Number(number).toFixed(decimals);
		},

		limitString(str, limit) {
			if (!str) return '';
			return str.length > limit ? str.substring(0, limit) + '...' : str;
		},

		async copyTradeUrl(event) {
			const url = event.currentTarget.dataset.url;

			try {
				await navigator.clipboard.writeText(url);
				window.toast.success('Trade URL скопирован в буфер обмена');

				// Временно меняем иконку
				const icon = event.currentTarget.querySelector('i');
				const originalClass = icon.className;
				icon.className = 'ri-check-line ms-1 text-success';

				setTimeout(() => {
					icon.className = originalClass;
				}, 2000);

			} catch (err) {
				window.toast.error(handleApiError(error));
				console.error('Failed to copy: ', err);
			}
		},

		formatDate(dateString) {
			if (!dateString) return '';
			const date = new Date(dateString);
			return date.toLocaleDateString('ru-RU');
		},

		loadTelegramWidget() {
			this.$nextTick(() => {
				const widgetContainer = document.getElementById('telegram-login-widget-inline');
				if (widgetContainer && !widgetContainer.hasChildNodes()) {
					console.log('Loading Telegram widget...');

					// Создаем скрипт для Telegram виджета
					const script = document.createElement('script');
					script.async = true;
					script.src = 'https://telegram.org/js/telegram-widget.js?22';
					script.setAttribute('data-telegram-login', this.telegramBotName || window.telegramBotName || 'cs_skins_bot');
					script.setAttribute('data-size', 'medium');
					script.setAttribute('data-userpic', 'false');
					script.setAttribute('data-auth-url', window.location.origin + '/profile/telegram/verify');
					script.setAttribute('data-request-access', 'write');

					widgetContainer.appendChild(script);
				}
			});
		},

		async onTelegramAuth(user) {
			try {
				const response = await fetch('/profile/telegram/verify', {
					method: 'POST',
					headers: getApiHeaders(),
					body: JSON.stringify({
						id: user.id,
						first_name: user.first_name,
						last_name: user.last_name,
						username: user.username,
						photo_url: user.photo_url,
						auth_date: user.auth_date,
						hash: user.hash
					})
				});

				if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
  }

				const data = await response.json();

				if (data.success) {
					window.toast.success('Telegram верификация успешно завершена!');

					// Эмитим событие для обновления client в родительском компоненте
					this.$emit('update-client', {
						telegram_id: user.id,
						telegram_username: user.username,
						is_verified: true
					});

					// Перезагружаем страницу через 1.5 секунды
					setTimeout(() => {
						window.location.reload();
					}, 1500);
				} else {
					window.toast.error(data.message || 'Ошибка при верификации');
				}
			} catch (error) {
				console.error('Error:', error);
				window.toast.error(handleApiError(error));
			}
		},

		async generateExtensionToken() {
			try {
				const response = await fetch('/profile/extension-token/generate', {
					method: 'POST',
					headers: getApiHeaders()
				});

				const data = await response.json();

				if (data.success) {
					this.extensionToken = data.token;
					this.$emit('update-client', { extension_token: data.token });
					window.toast.success('Токен расширения сгенерирован');
				} else {
					window.toast.error(data.message || 'Ошибка генерации токена');
				}
			} catch (error) {
				console.error('Generate extension token error:', error);
				window.toast.error(handleApiError(error));
			}
		},

		async confirmRegenerateToken() {
			try {
				const response = await fetch('/profile/extension-token/regenerate', {
					method: 'POST',
					headers: getApiHeaders()
				});

				const data = await response.json();

				if (data.success) {
					this.extensionToken = data.token;
					this.$emit('update-client', { extension_token: data.token });
					window.toast.success('Токен расширения перегенерирован');
					
					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('regenerate-token'));
					if (modal) {
						modal.hide();
					}
				} else {
					window.toast.error(data.message || 'Ошибка регенерации токена');
				}
			} catch (error) {
				console.error('Regenerate extension token error:', error);
				window.toast.error(handleApiError(error));
			}
		},

		async copyExtensionToken(event) {
			try {
				const token = this.extensionToken;
				if (!token) {
					window.toast.error('Токен не найден');
					return;
				}

				await navigator.clipboard.writeText(token);
				window.toast.success('Токен расширения скопирован в буфер обмена');

				// Временно меняем иконку
				if (event && event.currentTarget) {
					const icon = event.currentTarget.querySelector('i');
					if (icon) {
						const originalClass = icon.className;
						icon.className = 'ri-check-line ms-1 text-success';

						setTimeout(() => {
							icon.className = originalClass;
						}, 2000);
					}
				}

			} catch (err) {
				window.toast.error('Не удалось скопировать токен');
				console.error('Failed to copy: ', err);
			}
		}
	}
}
</script>