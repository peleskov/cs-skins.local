<template>
	<div class="change-profile-content">
		<div class="title  d-none d-lg-block">
			<div class="loader-line"></div>
			<h3>Информация профиля</h3>
		</div>

		<div class="mprf-avatar d-flex flex-column align-items-center justify-content-center d-lg-none mb-4">
			<div class="mprf-avatar-wrap mb-2">
				<img class="img-fluid profile-pic" :src="client.steam_avatar || '/images/icons/p5.png'" alt="profile"
					:style="client.avatar_border_color ? { 'background-color': client.avatar_border_color } : {}">
			</div>
			<p class="mprf-avatar-name">{{ client.name }}</p>
			<p v-if="client.is_premium && client.premium_expires_at" class="mprf-avatar-date">Премиум активно до {{
				formatDate(client.premium_expires_at) }}</p>
		</div>

		<!-- Под-вкладки -->
		<ul class="nav nav-tabs tab-style1 mb-4 pe-lg-4" role="tablist">
			<li class="nav-item flex-fill" role="presentation">
				<button class="nav-link" :class="{ active: subTab === 'main' }" type="button" role="tab"
					@click="subTab = 'main'">
					Основное
				</button>
			</li>
			<li class="nav-item flex-fill" role="presentation">
				<button class="nav-link" :class="{ active: subTab === 'premium' }" type="button" role="tab"
					@click="subTab = 'premium'">
					Премиум
				</button>
			</li>
			<li v-if="client.is_premium" class="nav-item flex-fill" role="presentation">
				<button class="nav-link" :class="{ active: subTab === 'history' }" type="button" role="tab"
					@click="subTab = 'history'">
					История заходов
				</button>
			</li>
			<li v-if="subTab === 'history'" class="nav-item ms-auto align-self-center" role="presentation">
				<VueDatePicker v-model="historyDateRange" range :enable-time-picker="false" auto-apply
					:locale="ruLocale">
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
			<!-- Mobile version -->
			<li class="m-balance align-items-start flex-column gap-2 d-lg-none">
				<div class="w-100 d-flex align-items-center justify-content-between">
					<p class="label mb-0">Баланс</p>
					<svg width="19" height="18" viewBox="0 0 19 18" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path
							d="M2 16V16V16V16V16V16V16V16V2V2V2V2V2V2V2V2C2 2 2 2.37083 2 3.1125C2 3.85417 2 4.81667 2 6V12C2 13.1833 2 14.1458 2 14.8875C2 15.6292 2 16 2 16V16M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V4.5H16V2V2V2H2V2V2V16V16V16H16V16V16V13.5H18V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2V18M10 14C9.45 14 8.97917 13.8042 8.5875 13.4125C8.19583 13.0208 8 12.55 8 12V6C8 5.45 8.19583 4.97917 8.5875 4.5875C8.97917 4.19583 9.45 4 10 4H17C17.55 4 18.0208 4.19583 18.4125 4.5875C18.8042 4.97917 19 5.45 19 6V12C19 12.55 18.8042 13.0208 18.4125 13.4125C18.0208 13.8042 17.55 14 17 14H10V14M17 12V12V12V6V6V6H10V6V6V12V12V12H17V12M13 10.5C13.4167 10.5 13.7708 10.3542 14.0625 10.0625C14.3542 9.77083 14.5 9.41667 14.5 9C14.5 8.58333 14.3542 8.22917 14.0625 7.9375C13.7708 7.64583 13.4167 7.5 13 7.5C12.5833 7.5 12.2292 7.64583 11.9375 7.9375C11.6458 8.22917 11.5 8.58333 11.5 9C11.5 9.41667 11.6458 9.77083 11.9375 10.0625C12.2292 10.3542 12.5833 10.5 13 10.5V10.5"
							fill="#954A00" />
					</svg>
				</div>
				<p class="balance" v-html="formatPrice(client.balance)"></p>
				<a href="/profile#balance" class="w-100 m-btn gap-1"><i class="m-ico m-ico-plus"></i>Пополнить</a>
			</li>
			<li class="align-items-start flex-column gap-2 d-lg-none">
				<div class="w-100 d-flex flex-column gap-3">
					<div class="w-100 d-flex gap-2 align-items-center justify-content-between">
						<div class="ico-wrap"><i class="m-ico m-ico-email"></i></div>
						<div class="flex-grow-1">
							<p class="label">Email</p>
							<p class="value">{{ client.email || 'Не указан' }}</p>
						</div>
						<div>
							<a v-if="canResendVerification" href="#email" class="m-btn-link" data-bs-toggle="modal">
								{{ client.email ? 'Изменить' : 'Добавить' }}
							</a>
							<div v-if="client.email && !client.email_verified_at" class="d-flex flex-column gap-1">
								<button class="m-btn-link" ref="resendEmailBtn" @click="resendVerification"
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
					</div>
					<div class="w-100 d-flex gap-2 align-items-center justify-content-between">
						<div class="ico-wrap"><i class="m-ico m-ico-email"></i></div>
						<div class="flex-grow-1">
							<p class="label">STEAM ID</p>
							<p class="value">{{ client.steam_id }}</p>
						</div>
					</div>
				</div>
			</li>
			<li class="align-items-start flex-column gap-3 d-lg-none">
				<div class="w-100 m-trade-url">
					<div class="w-100 d-flex align-items-center justify-content-between mb-2">
						<p class="label mb-0">TRADE URL</p>
						<a href="https://steamcommunity.com/my/tradeoffers/privacy" target="_blank" rel="noopener"
							class="m-link-ext">
							Где он?<i class="m-ico m-ico-ext"></i>
						</a>
					</div>
					<div class="m-trade-url-row">
						<div class="m-trade-url-input" @click="client.steam_trade_url ? copyTradeUrl($event) : null">
							<span v-if="client.steam_trade_url" class="trade-url-text"
								:data-url="client.steam_trade_url">
								{{ client.steam_trade_url }}
							</span>
							<span v-else class="placeholder">Не указан</span>
						</div>
						<a href="#trade-url" class="m-trade-url-btn" data-bs-toggle="modal">
							<i class="m-ico m-ico-link"></i>
						</a>
					</div>
				</div>
				<div class="w-100 m-trade-url">
					<div class="w-100 d-flex align-items-center justify-content-between mb-2">
						<p class="label mb-0">ТОКЕН РАСШИРЕНИЯ</p>
					</div>
					<div class="m-trade-url-row">
						<div class="m-trade-url-input" @click="extensionToken ? copyExtensionToken($event) : null">
							<span v-if="extensionToken" class="trade-url-text" :data-url="extensionToken">
								{{ extensionToken }}
							</span>
							<span v-else class="placeholder">Не сгенерирован</span>
						</div>
						<button v-if="!extensionToken" type="button" class="m-trade-url-btn border-0"
							@click="generateExtensionToken" :disabled="isGeneratingToken">
							<i class="ri-pencil-line"></i>
						</button>
						<button v-else type="button" class="m-trade-url-btn border-0" @click="showRegenerateConfirm">
							<i class="ri-pencil-line"></i>
						</button>
					</div>
					<p class="value mt-1 mb-0">Не передавайте третьим лицам! Используется для подключения браузерного расширения.</p>
				</div>
				<div class="w-100">
					<p class="label mb-1">ДАТА РЕГИСТРАЦИИ</p>
					<p class="m-date-value mb-0">{{ formatDate(client.created_at) }}</p>
				</div>
			</li>

			<!-- Name -->
			<li class="d-none d-lg-flex">
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-user-3-fill"></i>
						<span>Имя :</span>
					</div>
					<h6>{{ client.name }}</h6>
				</div>
			</li>

			<!-- Email -->
			<li class="d-none d-lg-flex">
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
			<li class="d-none d-lg-flex">
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-gamepad-line"></i>
						<span>Steam ID :</span>
					</div>
					<h6>{{ client.steam_id }}</h6>
				</div>
			</li>

			<!-- Trade URL -->
			<li class="d-none d-lg-flex">
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-exchange-line"></i>
						<span>Trade URL :</span>
					</div>
					<h6>
						<span v-if="client.steam_trade_url">
							<span class="trade-url-text" :data-url="client.steam_trade_url" style="cursor: pointer;"
								title="Нажмите для копирования" @click="copyTradeUrl">
								{{ client.steam_trade_url }}
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
			<li class="d-none d-lg-flex">
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
			<li class="d-none d-lg-flex">
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
			<li class="d-none d-lg-flex">
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-key-2-line"></i>
						<span>Токен расширения :</span>
					</div>
					<h6>
						<span v-if="extensionToken">
							<code class="token-text">{{ extensionToken }}</code>
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
			<li class="d-none d-lg-flex">
				<div class="profile-content">
					<div class="d-flex align-items-center gap-sm-2 gap-1">
						<i class="ri-calendar-check-line"></i>
						<span>Дата регистрации :</span>
					</div>
					<h6>{{ formatDate(client.created_at) }}</h6>
				</div>
			</li>
		</ul>
		<ul v-show="subTab === 'main'" class="m-profile-menu d-lg-none">
			<template v-for="(tab, key) in profileTabs" :key="key">
				<li v-if="key !== 'profile'" :class="{ active: activeTab === key }">
					<i class="m-ico" :class="`m-ico-menu-${key}`"></i>
					<a href="#" @click.prevent="$emit('set-tab', key)">{{ tab.title }}</a>
				</li>
			</template>
			<li>
				<i class="m-ico m-ico-menu-logout"></i>
				<a href="#" @click.prevent="$emit('logout')">Выйти</a>
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
		},
		profileTabs: {
			type: Object,
			default: () => ({})
		},
		activeTab: {
			type: String,
			default: 'profile'
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