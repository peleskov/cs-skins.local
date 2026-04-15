<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Информация профиля</h3>
		</div>

		<!-- Под-вкладки -->
		<ul class="nav nav-tabs tab-style1 mb-4 pe-4" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: subTab === 'main' }" type="button" role="tab"
					@click="subTab = 'main'">
					Основное
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: subTab === 'premium' }" type="button" role="tab"
					@click="subTab = 'premium'">
					Премиум
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" :class="{ active: subTab === 'history' }" type="button" role="tab"
					@click="subTab = 'history'">
					История заходов
				</button>
			</li>
			<li v-if="subTab === 'history'" class="nav-item ms-auto align-self-center" role="presentation">
				<VueDatePicker v-model="historyDateRange" range :enable-time-picker="false" auto-apply :locale="ruLocale" >
					<template #trigger>
						<button class="btn-calendar" type="button"></button>
					</template>
				</VueDatePicker>
			</li>
		</ul>

		<!-- Контент под-вкладки "Премиум" -->
		<ProfilePremium v-if="subTab === 'premium'" :client="client" @update-client="$emit('update-client', $event)" />

		<!-- Контент под-вкладки "История заходов" -->
		<ProfileLoginHistory v-if="subTab === 'history'" :client="client" :date-range="historyDateRange" />

		<!-- Контент под-вкладки "Основное" -->
		<ul v-show="subTab === 'main'" class="profile-details-list">
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
						<span>Верификация для получение уведомлений о заказах :</span>
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
				</div>
				<div v-if="!client.is_verified && !client.telegram_id" class="mt-2">
					<button class="btn theme-outline mt-0" @click="startTelegramVerification"
						:disabled="isGeneratingVerificationCode">
						<span v-if="isGeneratingVerificationCode">
							<span class="spinner-border spinner-border-sm me-1" role="status"></span>
							Генерируем код...
						</span>
						<span v-else>
							<i class="ri-telegram-fill me-1"></i>
							Подключить Telegram
						</span>
					</button>
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
						<span v-else class="badge bg-secondary text-bg-danger">Не сгенерирован</span>
					</h6>
					<div class="text-muted">
						Не передавайте третьим лицам! Используется для подключения браузерного расширения.
					</div>
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
									Найдите Trade URL в своем Steam: Мой профиль → Инвентарь → Предложения обмена → <a
										:href="`https://steamcommunity.com/profiles/${client.steam_id}/tradeoffers/privacy`"
										target="_blank">Кто может отправлять мне предложения обмена?</a>
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


		<!-- Telegram Verification Modal -->
		<div class="modal address-details-modal fade" id="telegram-verification" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h1 class="modal-title fs-5">Подключить Telegram</h1>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div v-if="verificationCode" class="text-center">
							<h5 class="mb-3">Ваш код верификации:</h5>
							<div class="d-flex justify-content-center align-items-center gap-2 mb-3">
								<code class="fs-2 user-select-all">{{ verificationCode }}</code>
								<button class="btn btn-sm" @click="copyVerificationCode">
									<i class="ri-file-copy-line"></i>
								</button>
							</div>
							<p class="mb-3">Время действия кода: <strong>{{ verificationCodeTimeRemaining }}</strong>
							</p>
							<div class="alert alert-info">
								<i class="ri-information-line"></i>
								<strong>Инструкция:</strong>
								<ol class="text-start mt-2 mb-0">
									<li>Перейдите к боту по кнопке ниже</li>
									<li>Нажмите кнопку "Начать" или "Start" в боте</li>
									<li>Бот запросит код верификации</li>
									<li>Отправьте код: <strong>{{ verificationCode }}</strong></li>
								</ol>
							</div>
							<a :href="telegramBotUrl" target="_blank" class="btn theme-btn mt-3">
								<i class="ri-telegram-fill me-1"></i>
								Перейти к боту
							</a>
						</div>
						<div v-else class="text-center">
							<span class="spinner-border" role="status"></span>
							<p class="mt-2">Генерируем код...</p>
						</div>
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
import { formatPrice, getTimeRemaining, copyToClipboard } from '../../../shared/utils/helpers';
import ProfilePremium from './Premium.vue';
import ProfileLoginHistory from './LoginHistory.vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

export default {
	name: 'ProfileInfo',
	components: {
		ProfilePremium,
		ProfileLoginHistory,
		VueDatePicker
	},
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
			subTab: 'main',
			historyDateRange: null,
			ruLocale: null,

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
			isGeneratingVerificationCode: false,
			verificationCode: null,
			verificationCodeExpiresAt: null,
			verificationCodeTimer: null,
			verificationCodeTimeRemaining: '',
			telegramBotUrl: ''
		}
	},
	computed: {
		csrfToken() {
			return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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

		// ==================== TELEGRAM METHODS ====================
		async startTelegramVerification() {
			if (this.isGeneratingVerificationCode) return;

			this.isGeneratingVerificationCode = true;
			this.verificationCode = null;

			try {
				const response = await axios.post('/profile/telegram/generate-code');
				const data = response.data;

				if (data.success) {
					this.verificationCode = data.code;
					// Не передаем код в URL, так как бот запросит его отдельно
					this.telegramBotUrl = `https://t.me/${this.telegramBotName}`;
					this.verificationCodeExpiresAt = new Date(Date.now() + data.expires_in * 1000);

					// Запускаем таймер обратного отсчета
					this.startVerificationCodeTimer();

					// Открываем модальное окно
					const modal = new bootstrap.Modal(document.getElementById('telegram-verification'));
					modal.show();

					// Проверяем статус каждые 5 секунд
					this.startVerificationStatusCheck();
				} else {
					window.toast.error(data.message || 'Не удалось сгенерировать код верификации');
				}
			} catch (error) {
				console.error('Generate verification code error:', error);
				window.toast.error('Ошибка при генерации кода');
			} finally {
				this.isGeneratingVerificationCode = false;
			}
		},

		startVerificationCodeTimer() {
			if (this.verificationCodeTimer) {
				clearInterval(this.verificationCodeTimer);
			}

			const updateTimer = () => {
				if (!this.verificationCodeExpiresAt) {
					clearInterval(this.verificationCodeTimer);
					return;
				}

				const now = new Date();
				const diff = this.verificationCodeExpiresAt - now;

				if (diff <= 0) {
					this.verificationCodeTimeRemaining = 'Истек';
					clearInterval(this.verificationCodeTimer);
					this.verificationCode = null;
				} else {
					const minutes = Math.floor(diff / 60000);
					const seconds = Math.floor((diff % 60000) / 1000);
					this.verificationCodeTimeRemaining = `${minutes}:${seconds.toString().padStart(2, '0')}`;
				}
			};

			updateTimer();
			this.verificationCodeTimer = setInterval(updateTimer, 1000);
		},

		startVerificationStatusCheck() {
			let checkCount = 0;
			const maxChecks = 120; // 10 минут / 5 секунд = 120 проверок

			const checkStatus = async () => {
				if (!this.verificationCode || this.client.is_verified) {
					return;
				}

				try {
					// Запрашиваем данные пользователя
					const response = await axios.get('/api/profile/me');

					if (response.data && response.data.is_verified && response.data.telegram_id) {
						// Обновляем данные клиента
						this.$emit('update-client', {
							telegram_id: response.data.telegram_id,
							telegram_username: response.data.telegram_username,
							is_verified: true
						});

						// Закрываем модальное окно
						const modal = bootstrap.Modal.getInstance(document.getElementById('telegram-verification'));
						if (modal) modal.hide();

						window.toast.success('Telegram успешно подключен!');

						// Перезагружаем страницу через 1.5 секунды
						setTimeout(() => {
							window.location.reload();
						}, 1500);
						return;
					}
				} catch (error) {
					// Игнорируем ошибки, продолжаем проверять
				}

				checkCount++;
				if (checkCount < maxChecks && this.verificationCode) {
					setTimeout(checkStatus, 5000); // Проверяем через 5 секунд
				}
			};

			// Начинаем проверку через 5 секунд
			setTimeout(checkStatus, 5000);
		},

		async copyVerificationCode() {
			if (!this.verificationCode) return;

			await copyToClipboard(
				this.verificationCode,
				'Код скопирован в буфер обмена',
				'Не удалось скопировать код'
			);
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

	async mounted() {
		const { ru } = await import('date-fns/locale/ru');
		this.ruLocale = ru;

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
		// Clear timers
		if (this.resendTimer) {
			clearInterval(this.resendTimer);
		}
		if (this.verificationCodeTimer) {
			clearInterval(this.verificationCodeTimer);
		}
	}
}
</script>