<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Информация профиля</h3>
		</div>
		<ul class="profile-details-list">
			<!-- Name -->
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-user-3-fill"></i>
						<span>Имя :</span>
					</div>
					<h6>{{ client.name }}</h6>
				</div>
			</li>

			<!-- Email -->
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-mail-fill"></i>
						<span>Email :</span>
					</div>
					<h6>
						{{ client.email || 'Не указан' }}
						<span v-if="client.email && client.email_verified_at" class="badge bg-success-subtle ms-2">
							Подтвержден
						</span>
						<span v-else-if="client.email && !client.email_verified_at" class="badge bg-warning ms-2">
							Не подтвержден
						</span>
					</h6>
					<!-- Email Notifications Toggle -->
					<div v-if="client.email && client.email_verified_at" class="form-check form-switch mt-2">
						<input class="form-check-input"
							type="checkbox"
							role="switch"
							id="emailNotifications"
							:checked="isEmailNotificationsEnabled"
							@change="toggleEmailNotifications">
						<label class="form-check-label" for="emailNotifications">
							Отправлять уведомления на email
						</label>
					</div>
				</div>
				<div class="d-flex gap-2">
					<a v-if="canResendVerification" href="#email" class="btn theme-outline" data-bs-toggle="modal">
						{{ client.email ? 'Изменить' : 'Добавить' }}
					</a>
					<div v-if="client.email && !client.email_verified_at" class="d-flex flex-column gap-1">
						<button class="btn theme-outline" ref="resendEmailBtn" @click="resendVerification"
							:disabled="!canResendVerification || isResendingEmail">
							<span class="btn-text">
								<span v-if="isResendingEmail">
									<span class="spinner-border spinner-border-sm me-1" role="status"></span>
									Отправка...
								</span>
								<span v-else-if="canResendVerification">Отправить письмо</span>
								<span v-else>Отправить повторно через {{ timeUntilCanResend }}</span>
							</span>
						</button>
					</div>
				</div>
			</li>

			<!-- Steam ID -->
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-gamepad-line"></i>
						<span>Steam ID :</span>
					</div>
					<h6>{{ client.steam_id }}</h6>
				</div>
			</li>

			<!-- Trade URL -->
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-exchange-line"></i>
						<span>Trade URL :</span>
					</div>
					<h6>
						<span v-if="client.steam_trade_url">
							<span class="trade-url-text" :data-url="client.steam_trade_url" style="cursor: pointer;"
								title="Нажмите для копирования" @click="copyTradeUrl">
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

			<!-- Balance -->
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-wallet-3-line"></i>
						<span>Баланс :</span>
					</div>
					<h6 v-html="formatPrice(client.balance)"></h6>
				</div>
				<a href="/profile#balance" class="btn theme-outline mt-0">Пополнить</a>
			</li>

			<!-- Verification -->
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-telegram-fill"></i>
						<span>Верификация :</span>
					</div>
					<h6>
						<template v-if="client.is_verified && client.telegram_id">
							<span class="badge bg-success-subtle">Верифицирован</span>
							<small class="ms-2">через Telegram @{{ client.telegram_username || 'User' }}</small>
						</template>
						<template v-else>
							<span class="badge bg-warning">Не верифицирован</span>
						</template>
					</h6>
					<!-- Telegram Notifications Toggle -->
					<div v-if="client.telegram_id && client.is_verified" class="form-check form-switch mt-2">
						<input class="form-check-input"
							type="checkbox"
							role="switch"
							id="telegramNotifications"
							:checked="isTelegramNotificationsEnabled"
							@change="toggleTelegramNotifications">
						<label class="form-check-label" for="telegramNotifications">
							Отправлять уведомления в Telegram
						</label>
					</div>
				</div>
				<div class="d-flex flex-column align-items-end"
					v-if="showTelegramWidget || (!client.is_verified && !client.telegram_id)">
					<div id="telegram-login-widget-inline"></div>
					<small class="text-end text-muted d-block mt-2">
						Что бы выбрать другой аккаунт, <br> зайдите в Telegram → Настройки → Конфиденциальность →
						Авторизованные сайты
						<br> и удалите cs-skins
					</small>
				</div>

				<!-- Кнопки управления Telegram -->
				<div v-if="!showTelegramWidget && client.telegram_id && client.is_verified" class="mt-2">
					<a href="#telegram-unlink" class="btn theme-outline mt-0" data-bs-toggle="modal">Отвязать</a>
				</div>
			</li>

			<!-- Extension Token -->
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-key-2-line"></i>
						<span>Токен расширения :</span>
					</div>
					<h6>
						<span v-if="extensionToken">
							<code>{{ limitString(extensionToken, 20) }}</code>
							<button class="btn ms-2" @click="copyExtensionToken($event)">
								<i class="ri-file-copy-line"></i>
							</button>
						</span>
						<span v-else class="badge bg-secondary">Не сгенерирован</span>
					</h6>
				</div>
				<div class="d-flex gap-2">
					<button v-if="!extensionToken" class="btn theme-outline mt-0" @click="generateExtensionToken"
						:disabled="isGeneratingToken">
						<span v-if="isGeneratingToken">
							<span class="spinner-border spinner-border-sm me-1" role="status"></span>
							Генерируем...
						</span>
						<span v-else>Сгенерировать</span>
					</button>
					<button v-else class="btn theme-outline mt-0" @click="showRegenerateConfirm">
						<i class="ri-refresh-line"></i> Перегенерировать
					</button>
				</div>
			</li>

			<!-- Registration Date -->
			<li>
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-calendar-check-line"></i>
						<span>Дата регистрации :</span>
					</div>
					<h6>{{ formatDate(client.created_at) }}</h6>
				</div>
			</li>
		</ul>

		<!-- Email Modal -->
		<div class="modal address-details-modal fade" id="email" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<form @submit.prevent="updateEmail" id="email-form">
						<div class="modal-header">
							<h1 class="modal-title fs-5">
								{{ client.email ? 'Изменить Email' : 'Добавить Email' }}
							</h1>
							<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="email-input" class="form-label">Email адрес</label>
								<input type="email" class="form-control" id="email-input" v-model="emailForm.email"
									:placeholder="client.email || 'example@mail.com'" required>
								<small class="text-muted">На этот адрес будут приходить важные уведомления</small>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
							<button type="submit" class="btn theme-btn mt-0" :disabled="isUpdatingEmail">
								<span v-if="isUpdatingEmail">
									<span class="spinner-border spinner-border-sm me-1" role="status"></span>
									Сохраняем...
								</span>
								<span v-else>Сохранить</span>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- Trade URL Modal -->
		<div class="modal address-details-modal fade" id="trade-url" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<form @submit.prevent="updateTradeUrl" id="trade-url-form">
						<div class="modal-header">
							<h1 class="modal-title fs-5">
								{{ client.steam_trade_url ? 'Изменить Trade URL' : 'Добавить Trade URL' }}
							</h1>
							<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="trade-url-input" class="form-label">Steam Trade URL</label>
								<input type="url" class="form-control" id="trade-url-input" v-model="tradeUrlForm.url"
									placeholder="https://steamcommunity.com/tradeoffer/new/?partner=123456&token=abcdef"
									required>
								<small class="text-muted">
									Найдите Trade URL в настройках Steam: Настройки → Конфиденциальность → Торговые
									предложения
								</small>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
							<button type="submit" class="btn theme-btn mt-0" :disabled="isUpdatingTradeUrl">
								<span v-if="isUpdatingTradeUrl">
									<span class="spinner-border spinner-border-sm me-1" role="status"></span>
									Сохраняем...
								</span>
								<span v-else>Сохранить</span>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- Telegram Unlink Modal -->
		<div class="modal address-details-modal fade" id="telegram-unlink" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h1 class="modal-title fs-5">Отвязать Telegram</h1>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<p>Вы уверены, что хотите отвязать Telegram аккаунт?</p>
						<small class="text-warning">
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

		<!-- Regenerate Token Modal -->
		<div class="modal address-details-modal fade" id="regenerate-token" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h1 class="modal-title fs-5">Перегенерировать токен</h1>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<p>Вы уверены, что хотите перегенерировать токен расширения?</p>
						<small class="text-warning">
							<i class="ri-alert-line"></i> <strong>Внимание!</strong>
							Старый токен перестанет работать, и расширение нужно будет переподключить с новым токеном.
						</small>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
						<button type="button" class="btn btn-danger mt-0" @click="regenerateExtensionToken"
							:disabled="isRegeneratingToken">
							<span v-if="isRegeneratingToken">
								<span class="spinner-border spinner-border-sm me-1" role="status"></span>
								Генерируем...
							</span>
							<span v-else>Перегенерировать</span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';
import { formatPrice, getTimeRemaining, copyToClipboard } from '../../utils/helpers';

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
			// Email
			emailForm: {
				email: ''
			},
			isUpdatingEmail: false,
			isResendingEmail: false,
			canResendVerification: true,
			timeUntilCanResend: '',
			resendTimer: null,

			// Trade URL
			tradeUrlForm: {
				url: ''
			},
			isUpdatingTradeUrl: false,

			// Extension Token
			extensionToken: this.client.extension_token || null,
			isGeneratingToken: false,
			isRegeneratingToken: false,

			// Telegram
			telegramWidgetLoaded: false,
			showTelegramWidget: false
		}
	},
	computed: {
		csrfToken() {
			return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
		},

		// Notification settings
		isEmailNotificationsEnabled() {
			return this.client.notification_settings &&
				Array.isArray(this.client.notification_settings) &&
				this.client.notification_settings.includes('email');
		},

		isTelegramNotificationsEnabled() {
			return this.client.notification_settings &&
				Array.isArray(this.client.notification_settings) &&
				this.client.notification_settings.includes('telegram');
		}
	},
	methods: {
		// ==================== EMAIL METHODS ====================
		async updateEmail() {
			if (!this.emailForm.email || this.isUpdatingEmail) return;

			this.isUpdatingEmail = true;

			try {
				const response = await axios.post('/profile/update-email', {
					email: this.emailForm.email
				});
				const data = response.data;

				if (data.success) {
					this.$emit('update-client', {
						email: this.emailForm.email,
						email_verified_at: null,
						email_verification_sent_at: new Date().toISOString()
					});
					window.toast.success(data.message || 'Email успешно обновлен');

					// Запускаем таймер для кнопки повторной отправки
					this.startResendTimer(60);

					const modal = bootstrap.Modal.getInstance(document.getElementById('email'));
					if (modal) modal.hide();

					this.emailForm.email = '';
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Update email error:', error);
				window.toast.error('Произошла ошибка при обновлении Email');
			} finally {
				this.isUpdatingEmail = false;
			}
		},

		async resendVerification() {
			if (!this.canResendVerification || this.isResendingEmail) return;

			this.isResendingEmail = true;

			try {
				const response = await axios.post('/email/resend');
				const data = response.data;

				if (data.success) {
					window.toast.success(data.message || 'Письмо отправлено');
					this.startResendTimer(60); // 1 минута
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Resend verification error:', error);
				window.toast.error('Произошла ошибка при отправке письма');
			} finally {
				this.isResendingEmail = false;
			}
		},

		startResendTimer(seconds) {
			this.canResendVerification = false;
			let remainingSeconds = seconds;

			// Clear existing timer
			if (this.resendTimer) {
				clearInterval(this.resendTimer);
			}

			const updateTimer = () => {
				if (remainingSeconds <= 0) {
					this.canResendVerification = true;
					this.timeUntilCanResend = '';
					clearInterval(this.resendTimer);
					this.resendTimer = null;
				} else {
					this.timeUntilCanResend = getTimeRemaining(remainingSeconds);
					remainingSeconds--;
				}
			};

			updateTimer(); // Initial update
			this.resendTimer = setInterval(updateTimer, 1000);
		},

		// ==================== TRADE URL METHODS ====================
		async updateTradeUrl() {
			if (!this.tradeUrlForm.url || this.isUpdatingTradeUrl) return;

			this.isUpdatingTradeUrl = true;

			try {
				const response = await axios.post('/profile/update-trade-url', {
					trade_url: this.tradeUrlForm.url
				});
				const data = response.data;

				if (data.success) {
					this.$emit('update-client', { steam_trade_url: this.tradeUrlForm.url });
					window.toast.success(data.message || 'Trade URL успешно обновлен');

					const modal = bootstrap.Modal.getInstance(document.getElementById('trade-url'));
					if (modal) modal.hide();

					this.tradeUrlForm.url = '';
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Trade URL update error:', error);
				window.toast.error('Произошла ошибка при обновлении Trade URL');
			} finally {
				this.isUpdatingTradeUrl = false;
			}
		},

		async copyTradeUrl(event) {
			const url = this.client.steam_trade_url;
			
			if (!url) {
				window.toast.error('Trade URL не найден');
				return;
			}
			
			// Сохраняем ссылку на иконку до асинхронной операции
			const icon = event?.currentTarget?.querySelector('i');
			
			await copyToClipboard(
				url, 
				'Trade URL скопирован в буфер обмена',
				'Не удалось скопировать Trade URL',
				icon
			);
		},

		// ==================== EXTENSION TOKEN METHODS ====================
		async generateExtensionToken() {
			if (this.isGeneratingToken) return;

			this.isGeneratingToken = true;

			try {
				const response = await axios.post('/profile/extension-token/generate');
				const data = response.data;

				if (data.success) {
					this.extensionToken = data.token;
					window.toast.success(data.message || 'Токен успешно сгенерирован');
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Generate token error:', error);
				window.toast.error('Произошла ошибка при генерации токена');
			} finally {
				this.isGeneratingToken = false;
			}
		},

		showRegenerateConfirm() {
			const modal = new bootstrap.Modal(document.getElementById('regenerate-token'));
			modal.show();
		},

		async regenerateExtensionToken() {
			if (this.isRegeneratingToken) return;

			this.isRegeneratingToken = true;

			try {
				const response = await axios.post('/profile/extension-token/regenerate');
				const data = response.data;

				if (data.success) {
					this.extensionToken = data.token;
					window.toast.success(data.message || 'Токен успешно перегенерирован');

					const modal = bootstrap.Modal.getInstance(document.getElementById('regenerate-token'));
					if (modal) modal.hide();
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Regenerate token error:', error);
				window.toast.error('Произошла ошибка при регенерации токена');
			} finally {
				this.isRegeneratingToken = false;
			}
		},

		async copyExtensionToken(event) {
			if (!this.extensionToken) {
				window.toast.error('Токен не найден');
				return;
			}
			
			// Сохраняем ссылку на иконку до асинхронной операции
			const icon = event?.currentTarget?.querySelector('i');
			
			await copyToClipboard(
				this.extensionToken,
				'Токен скопирован в буфер обмена',
				'Не удалось скопировать токен',
				icon
			);
		},

		clearAndReloadTelegramWidget() {
			// Очищаем контейнер виджета
			const widgetContainer = document.getElementById('telegram-login-widget-inline');
			if (widgetContainer) {
				widgetContainer.innerHTML = '';
			}

			// Очищаем все возможные Telegram данные
			try {
				// Очищаем localStorage и sessionStorage
				['telegram-auth-data', 'tgAuthResult', 'tgAuthData'].forEach(key => {
					localStorage.removeItem(key);
					sessionStorage.removeItem(key);
				});

				// Очищаем cookies связанные с Telegram
				document.cookie.split(";").forEach(cookie => {
					const eqPos = cookie.indexOf("=");
					const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
					if (name.toLowerCase().includes('telegram') || name.toLowerCase().includes('tg')) {
						document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=.telegram.org";
						document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
					}
				});
			} catch (e) {
				console.log('Storage cleanup failed:', e);
			}

			// Сбрасываем флаги
			this.telegramWidgetLoaded = false;

			// Перезагружаем виджет с небольшой задержкой
			setTimeout(() => {
				this.loadTelegramWidget();
			}, 500);
		},

		loadTelegramWidget() {
			if (this.telegramWidgetLoaded) return;

			this.$nextTick(() => {
				const widgetContainer = document.getElementById('telegram-login-widget-inline');
				if (!widgetContainer || widgetContainer.hasChildNodes()) return;

				const script = document.createElement('script');
				script.async = true;
				script.src = 'https://telegram.org/js/telegram-widget.js?' + Date.now();
				script.setAttribute('data-telegram-login', this.telegramBotName || 'cs_skins_bot');
				script.setAttribute('data-size', 'medium');
				script.setAttribute('data-userpic', 'false');
				script.setAttribute('data-onauth', 'onTelegramAuth(user)');
				script.setAttribute('data-request-access', 'write');

				// Добавляем параметр для принудительного выхода из текущего аккаунта
				if (this.showTelegramWidget) {
					script.setAttribute('data-auth-url', window.location.origin + '/profile/telegram/logout-and-auth');
				}

				widgetContainer.appendChild(script);
				this.telegramWidgetLoaded = true;
			});
		},

		async onTelegramAuth(user) {
			try {
				const response = await axios.post('/profile/telegram/verify', user);
				const data = response.data;

				if (data.success) {
					this.$emit('update-client', {
						telegram_id: user.id,
						telegram_username: user.username,
						is_verified: true
					});

					window.toast.success(data.message || 'Telegram верификация успешно завершена!');

					// Reload page after 1.5 seconds
					setTimeout(() => {
						window.location.reload();
					}, 1500);
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Telegram auth error:', error);
				window.toast.error('Произошла ошибка при верификации');
			}
		},

		// ==================== NOTIFICATION METHODS ====================
		async toggleEmailNotifications() {
			await this.updateNotificationSettings('email');
		},

		async toggleTelegramNotifications() {
			await this.updateNotificationSettings('telegram');
		},

		async updateNotificationSettings(type) {
			try {
				const currentSettings = Array.isArray(this.client.notification_settings)
					? this.client.notification_settings
					: [];
				let newSettings;

				if (currentSettings.includes(type)) {
					// Remove notification type
					newSettings = currentSettings.filter(setting => setting !== type);
				} else {
					// Add notification type
					newSettings = [...currentSettings, type];
				}

				console.log('Sending notification settings:', { notification_settings: newSettings });

				const response = await axios.post('/profile/notification-settings', {
					notification_settings: newSettings
				});

				const data = response.data;
				if (data.success) {
					this.$emit('update-client', {
						notification_settings: newSettings
					});

					const action = newSettings.includes(type) ? 'включены' : 'отключены';
					const channel = type === 'email' ? 'email' : 'Telegram';
					window.toast.success(`Уведомления на ${channel} ${action}`);
				}
			} catch (error) {
				console.error('Update notification settings error:', error);
				console.error('Error response:', error.response?.data);

				// Показываем более детальную ошибку если есть
				const errorMessage = error.response?.data?.message || 'Произошла ошибка при обновлении настроек уведомлений';
				window.toast.error(errorMessage);
			}
		},

		// ==================== UTILITY METHODS ====================
		formatPrice,

		formatDate(dateString) {
			if (!dateString) return '';
			const date = new Date(dateString);
			return date.toLocaleDateString('ru-RU');
		},

		limitString(str, limit) {
			if (!str) return '';
			return str.length > limit ? str.substring(0, limit) + '...' : str;
		}
	},

	mounted() {
		// Load Telegram widget if needed
		if (!this.client.is_verified && !this.client.telegram_id) {
			this.loadTelegramWidget();
		}

		// Set global Telegram callback
		window.onTelegramAuth = this.onTelegramAuth;

		// Initialize email resend timer if needed
		if (this.client.email && !this.client.email_verified_at && this.client.email_verification_sent_at) {
			const sentAt = new Date(this.client.email_verification_sent_at);
			const now = new Date();
			const diffInSeconds = Math.floor((now - sentAt) / 1000);
			const remainingSeconds = 60 - diffInSeconds;

			if (remainingSeconds > 0) {
				this.startResendTimer(remainingSeconds);
			}
		}

		// Initialize form values
		this.emailForm.email = this.client.email || '';
		this.tradeUrlForm.url = this.client.steam_trade_url || '';
	},

	beforeUnmount() {
		// Clear timer
		if (this.resendTimer) {
			clearInterval(this.resendTimer);
		}

		// Remove global callback
		if (window.onTelegramAuth === this.onTelegramAuth) {
			window.onTelegramAuth = null;
		}
	}
}
</script>