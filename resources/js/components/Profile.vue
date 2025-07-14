<template>
	<div class="profile-container">
		<div class="row g-3">
			<!-- Profile Sidebar -->
			<div class="col-lg-3">
				<div class="profile-sidebar sticky-top">
					<div class="profile-cover">
						<img class="img-fluid profile-pic" :src="client.steam_avatar || '/images/icons/p5.png'"
							alt="profile">
					</div>
					<div class="profile-name">
						<h5 class="user-name">{{ client.name }}</h5>
						<h6>{{ client.email || 'Email не указан' }}</h6>
					</div>
					<ul class="profile-list">
						<li :class="{ active: activeTab === 'profile' }">
							<i class="ri-user-3-line"></i>
							<a href="#" @click.prevent="setActiveTab('profile')">Профиль</a>
						</li>
						<li :class="{ active: activeTab === 'trading' }">
							<i class="ri-shopping-bag-3-line"></i>
							<a href="#" @click.prevent="setActiveTab('trading')">Торговля</a>
						</li>
						<li :class="{ active: activeTab === 'inventory' }">
							<i class="ri-treasure-map-line"></i>
							<a href="#" @click.prevent="setActiveTab('inventory')">Инвентарь</a>
						</li>
						<li :class="{ active: activeTab === 'auctions' }">
							<i class="ri-store-2-line"></i>
							<a href="#" @click.prevent="setActiveTab('auctions')">Мои аукционы</a>
						</li>
						<li :class="{ active: activeTab === 'balance' }">
							<i class="ri-bank-card-line"></i>
							<a href="#" @click.prevent="setActiveTab('balance')">Баланс</a>
						</li>
						<li :class="{ active: activeTab === 'settings' }">
							<i class="ri-settings-3-line"></i>
							<a href="#" @click.prevent="setActiveTab('settings')">Настройки</a>
						</li>
						<li>
							<i class="ri-logout-box-r-line"></i>
							<a href="#" @click.prevent="logout">Выйти</a>
						</li>
					</ul>
				</div>
			</div>

			<!-- Profile Content -->
			<div class="col-lg-9">
				<!-- Profile Info Tab -->
				<div v-if="activeTab === 'profile'" class="change-profile-content">
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
					</ul>
				</div>

				<!-- Inventory Tab -->
				<div v-else-if="activeTab === 'inventory'" class="change-profile-content">
					<div class="title">
						<div class="loader-line"></div>
						<div class="d-flex justify-content-between align-items-center">
							<h3>Steam Инвентарь</h3>
							<button v-if="inventoryData" class="btn theme-outline btn-sm" @click="syncInventory"
								:disabled="isSyncing || syncCooldownRemaining > 0">
								<i :class="['ri-refresh-line', 'me-1', { 'ri-spin': isSyncing }]"></i>
								<span v-if="isSyncing">Обновление...</span>
								<span v-else-if="syncCooldownRemaining > 0">Обновить через {{
									formatCooldownTime(syncCooldownRemaining) }}</span>
								<span v-else>Обновить инвентарь</span>
							</button>
						</div>
					</div>
					<div v-if="isSyncing" class="text-center py-5">
						<div class="loader-gif">
							<div class="radar-ring"></div>
							<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
						</div>
						<p class="mt-3">Обновляем инвентарь...</p>
					</div>
					<inventory-grid v-else-if="inventoryData" ref="inventoryGrid" :initial-items="inventoryData.items"
						:initial-stats="inventoryData.stats" :initial-has-trade-url="inventoryData.has_trade_url"
						@inventory-updated="handleInventoryUpdate" />
					<div v-else class="text-center py-5">
						<div class="loader-gif">
							<div class="radar-ring"></div>
							<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
						</div>
						<p class="mt-3">Загружаем инвентарь...</p>
					</div>
				</div>

				<!-- Other Tabs Placeholder -->
				<div v-else class="text-center py-5">
					<h4>{{ getTabTitle(activeTab) }}</h4>
					<p class="text-muted">Эта функция будет реализована позже</p>
				</div>
			</div>
		</div>

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
								<small v-if="client.email" class="text-warning d-block mt-2">
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
						<div class="alert alert-warning">
							<i class="ri-warning-line me-2"></i>
							При отвязке Telegram статус верификации будет сброшен.
						</div>
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

	</div>
</template>

<script>
import InventoryGrid from './InventoryGrid.vue';
import { useToast } from "vue-toastification";

export default {
	name: 'Profile',
	components: {
		InventoryGrid
	},
	props: {
		initialClient: {
			type: Object,
			required: true
		},
		telegramBotName: {
			type: String,
			default: ''
		}
	},
	setup() {
		const toast = useToast();
		return { toast };
	},

	data() {
		// Получаем начальную вкладку
		const getInitialTab = () => {
			// Проверяем, что мы в браузере
			if (typeof window === 'undefined') {
				return 'profile';
			}

			// Проверяем hash в URL
			const hash = window.location.hash.substring(1);
			if (hash && ['profile', 'trading', 'inventory', 'auctions', 'balance', 'settings'].includes(hash)) {
				return hash;
			}

			// Проверяем localStorage
			try {
				const savedTab = localStorage.getItem('profile-active-tab');
				if (savedTab && ['profile', 'trading', 'inventory', 'auctions', 'balance', 'settings'].includes(savedTab)) {
					return savedTab;
				}
			} catch (e) {
				// localStorage недоступен
			}

			return 'profile';
		};

		return {
			client: { ...this.initialClient },
			activeTab: getInitialTab(),
			inventoryData: null,
			isSyncing: false,
			syncCooldownRemaining: 0,
			cooldownTimer: null,
			timeUntilCanResend: '',
			canResendVerification: true,
		}
	},
	computed: {
		csrfToken() {
			return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
		}
	},
	methods: {
		setActiveTab(tab) {
			this.activeTab = tab;

			// Сохраняем активную вкладку в localStorage
			try {
				localStorage.setItem('profile-active-tab', tab);
			} catch (e) {
				// localStorage недоступен
			}

			// Обновляем hash в URL
			if (typeof window !== 'undefined') {
				window.history.replaceState(null, null, '#' + tab);
			}

			// Загружаем данные для инвентаря при переходе на вкладку
			if (tab === 'inventory' && !this.inventoryData) {
				this.loadInventoryData();
			}
		},


		async loadInventoryData() {
			try {
				const response = await fetch('/inventory', {
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					}
				});

				const data = await response.json();

				if (data.success) {
					this.inventoryData = data.data;
					// Обновляем информацию о hasTradeUrl в компоненте инвентаря
					if (this.$refs.inventoryGrid) {
						this.$refs.inventoryGrid.hasTradeUrl = data.data.has_trade_url;
					}

					// Проверяем, нужно ли запустить кулдаун для синхронизации
					if (data.data.stats && data.data.stats.last_sync) {
						const lastSyncTime = new Date(data.data.stats.last_sync);
						const now = new Date();
						const timeDiff = now - lastSyncTime;
						const cooldownTime = 2 * 60 * 1000; // 2 минуты в мс

						if (timeDiff < cooldownTime) {
							const remainingSeconds = Math.ceil((cooldownTime - timeDiff) / 1000);
							this.startSyncCooldown(remainingSeconds);
						}
					}

					// Если инвентарь пустой, показываем сообщение
					if (data.data.items.length === 0) {
						this.toast.info('Ваш Steam инвентарь пуст или приватный. Убедитесь, что инвентарь публичный в настройках Steam. После изменения настроек инвентаря в Steam попробуйте еще раз через 10-15 минут.', {
							timeout: 10000
						});
					}
				} else {
					// Если API вернул ошибку, устанавливаем пустой инвентарь
					this.inventoryData = { items: [], stats: {} };
					this.toast.error(data.message || 'Не удалось загрузить инвентарь');
				}
			} catch (error) {
				console.error('Error loading inventory:', error);
				// В любом случае устанавливаем пустые данные чтобы убрать лоадер
				this.inventoryData = { items: [], stats: {} };
				this.toast.error('Не удалось загрузить инвентарь. Убедитесь, что ваш Steam инвентарь публичный и попробуйте ещё раз. После изменения настроек инвентаря в Steam попробуйте еще раз через 10-15 минут.', {
					timeout: 10000
				});
			}
		},



		async unlinkTelegram() {
			if (!confirm('Вы уверены, что хотите отвязать Telegram аккаунт?\n\nПри отвязке Telegram статус верификации будет сброшен.')) {
				return;
			}

			try {
				const response = await fetch('/profile/telegram/unlink', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					}
				});

				if (response.ok) {
					this.client.telegram_id = null;
					this.client.telegram_username = null;
					this.client.is_verified = false;
					this.toast.success('Telegram аккаунт отвязан. Статус верификации сброшен.');

					// Перезагружаем Telegram виджет
					this.loadTelegramWidget();
				} else {
					const data = await response.json();
					this.toast.error(data.message || 'Не удалось отвязать Telegram');
				}
			} catch (error) {
				console.error('Telegram unlink error:', error);
				this.toast.error('Произошла ошибка при отвязке Telegram');
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
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					}
				});

				const data = await response.json();

				if (response.ok) {
					this.toast.success(data.message || 'Письмо отправлено');
					this.startResendTimer(60); // 1 минута
				} else {
					this.toast.error(data.message || 'Не удалось отправить письмо');
					this.canResendVerification = true;
					if (btnText) btnText.textContent = originalText;
				}
			} catch (error) {
				console.error('Resend verification error:', error);
				this.toast.error('Произошла ошибка при отправке письма');
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

		logout() {
			if (confirm('Вы уверены, что хотите выйти?')) {
				window.location.href = '/auth/logout';
			}
		},

		getTabTitle(tab) {
			const titles = {
				trading: 'Торговля',
				auctions: 'Мои аукционы',
				balance: 'Баланс',
				settings: 'Настройки'
			};
			return titles[tab] || 'Раздел';
		},


		async handleInventoryUpdate() {
			// Перезагружаем данные инвентаря после синхронизации
			await this.loadInventoryData();
		},

		async syncInventory() {
			this.isSyncing = true;

			try {
				const response = await fetch('/inventory/sync', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					}
				});

				const data = await response.json();

				if (data.success) {
					this.toast.success(`Инвентарь обновлен! Загружено предметов: ${data.data.items_count}`);

					// Обновляем данные без перезагрузки страницы
					await this.loadInventoryData();

					// Запускаем кулдаун на 2 минуты
					this.startSyncCooldown(120); // 2 минуты = 120 секунд
				} else {
					this.toast.error(data.message);

					// Если есть информация о кулдауне, запускаем его
					if (data.data && data.data.cooldown_remaining) {
						this.startSyncCooldown(data.data.cooldown_remaining);
					}
				}
			} catch (error) {
				console.error('Sync error:', error);
				this.toast.error('Произошла ошибка при обновлении инвентаря');
			} finally {
				this.isSyncing = false;
			}
		},

		startSyncCooldown(seconds) {
			this.syncCooldownRemaining = seconds;

			// Очищаем предыдущий таймер если он есть
			if (this.cooldownTimer) {
				clearInterval(this.cooldownTimer);
			}

			this.cooldownTimer = setInterval(() => {
				this.syncCooldownRemaining--;

				if (this.syncCooldownRemaining <= 0) {
					clearInterval(this.cooldownTimer);
					this.cooldownTimer = null;
				}
			}, 1000);
		},

		formatCooldownTime(seconds) {
			const minutes = Math.floor(seconds / 60);
			const remainingSeconds = seconds % 60;

			if (minutes > 0) {
				return `${minutes} мин ${remainingSeconds} сек`;
			}
			return `${remainingSeconds} сек`;
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
				this.toast.success('Trade URL скопирован в буфер обмена');

				// Временно меняем иконку
				const icon = event.currentTarget.querySelector('i');
				const originalClass = icon.className;
				icon.className = 'ri-check-line ms-1 text-success';

				setTimeout(() => {
					icon.className = originalClass;
				}, 2000);

			} catch (err) {
				this.toast.error('Не удалось скопировать ссылку');
				console.error('Failed to copy: ', err);
			}
		},

		formatDate(dateString) {
			if (!dateString) return '';
			const date = new Date(dateString);
			return date.toLocaleDateString('ru-RU');
		},

		async updateTradeUrl() {
			const tradeUrlInput = document.getElementById('trade-url-input');
			const tradeUrl = tradeUrlInput.value;

			if (!tradeUrl) {
				this.toast.error('Введите Trade URL');
				return;
			}

			try {
				const response = await fetch('/profile/update-trade-url', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': this.csrfToken
					},
					body: JSON.stringify({
						trade_url: tradeUrl
					})
				});

				const data = await response.json();

				if (data.success) {
					this.client.steam_trade_url = tradeUrl;
					this.toast.success(data.message);
					
					// Закрываем модальное окно
					const modal = bootstrap.Modal.getInstance(document.getElementById('trade-url'));
					if (modal) {
						modal.hide();
					}
				} else {
					this.toast.error(data.message || 'Ошибка при сохранении Trade URL');
				}
			} catch (error) {
				console.error('Trade URL update error:', error);
				this.toast.error('Произошла ошибка при сохранении Trade URL');
			}
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
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
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

				const data = await response.json();

				if (data.success) {
					this.toast.success('Telegram верификация успешно завершена!');

					// Обновляем данные клиента
					this.client.telegram_id = user.id;
					this.client.telegram_username = user.username;
					this.client.is_verified = true;

					// Перезагружаем страницу через 1.5 секунды
					setTimeout(() => {
						window.location.reload();
					}, 1500);
				} else {
					this.toast.error(data.message || 'Ошибка при верификации');
				}
			} catch (error) {
				console.error('Error:', error);
				this.toast.error('Произошла ошибка при верификации');
			}
		},

		handleHashChange() {
			// Обрабатываем изменение хэша в URL
			const hash = window.location.hash.substring(1);
			if (hash && ['profile', 'trading', 'inventory', 'auctions', 'balance', 'settings'].includes(hash)) {
				this.setActiveTab(hash);
			}
		}
	},

	mounted() {
		// Загружаем данные для инвентаря если это активная вкладка при загрузке
		if (this.activeTab === 'inventory' && !this.inventoryData) {
			this.loadInventoryData();
		}

		// Загружаем Telegram виджет если пользователь не верифицирован
		if (!this.client.is_verified && !this.client.telegram_id) {
			this.loadTelegramWidget();
		}

		// Слушаем изменения хэша в URL
		window.addEventListener('hashchange', this.handleHashChange);

		// Устанавливаем глобальную функцию для Telegram callback
		window.onTelegramAuth = this.onTelegramAuth;
	},

	beforeUnmount() {
		// Убираем слушатель при уничтожении компонента
		window.removeEventListener('hashchange', this.handleHashChange);

		// Очищаем таймер кулдауна
		if (this.cooldownTimer) {
			clearInterval(this.cooldownTimer);
		}
	}
}
</script>