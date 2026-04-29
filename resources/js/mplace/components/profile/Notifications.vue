<template>
	<div id="Notifications" class="setting-content position-relative">
		<a href="/profile#profile" class="btn-to-profile d-lg-none"><i class="m-ico m-ico-back"></i>Назад</a>
		<div class="title">
			<div class="loader-line d-none d-lg-block"></div>
			<h3 class="mb-4 mb-lg-0">Уведомления</h3>
		</div>

		<ul class="notification-setting">
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-toast"></i></span><h6>Всплывающие уведомления</h6>
					<input type="checkbox" :checked="isToastNotificationsEnabled" @change="toggleToastNotifications">
				</div>
			</li>
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-email"></i></span><h6>Email уведомления</h6>
					<input type="checkbox" :checked="isEmailNotificationsEnabled" @change="toggleEmailNotifications"
						:disabled="!client.email || !client.email_verified_at">
				</div>
			</li>
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-telegram"></i></span><h6>Telegram уведомления</h6>
					<input type="checkbox" :checked="isTelegramNotificationsEnabled" @change="toggleTelegramNotifications"
						:disabled="!client.telegram_id">
				</div>
			</li>
		</ul>

		<div class="title mt-5">
			<div class="loader-line d-none d-lg-block"></div>
			<h3 class="mb-4 mb-lg-0">Звуковые уведомления</h3>
		</div>

		<ul class="notification-setting">
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-sale"></i></span><h6>Продажа (передача предмета)</h6>
					<input type="checkbox" v-model="soundSettings.saleTransfer" @change="saveSoundSettings">
				</div>
			</li>
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-cart"></i></span><h6>Покупка (получение предмета)</h6>
					<input type="checkbox" v-model="soundSettings.purchaseReceive" @change="saveSoundSettings">
				</div>
			</li>
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-success"></i></span><h6>Успешная продажа/покупка</h6>
					<input type="checkbox" v-model="soundSettings.success" @change="saveSoundSettings">
				</div>
			</li>
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-fail"></i></span><h6>Неудачная покупка</h6>
					<input type="checkbox" v-model="soundSettings.failed" @change="saveSoundSettings">
				</div>
			</li>
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-auction"></i></span><h6>Продажа аукциона</h6>
					<input type="checkbox" v-model="soundSettings.auction" @change="saveSoundSettings">
				</div>
			</li>
			<li>
				<div class="notification">
					<span class="m-notif-ico d-lg-none"><i class="m-ico m-ico-notif-other"></i></span><h6>Другие уведомления</h6>
					<input type="checkbox" v-model="soundSettings.other" @change="saveSoundSettings">
				</div>
			</li>
		</ul>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'ProfileNotifications',
	props: {
		client: {
			type: Object,
			required: true
		}
	},
	emits: ['update-client'],
	data() {
		const defaultSoundSettings = {
			saleTransfer: true,
			purchaseReceive: true,
			success: false,
			failed: false,
			auction: true,
			other: false
		};

		let soundSettings = { ...defaultSoundSettings };
		try {
			const saved = localStorage.getItem('soundSettings');
			if (saved) {
				soundSettings = { ...defaultSoundSettings, ...JSON.parse(saved) };
			}
		} catch (e) {
			// ignore
		}

		return {
			soundSettings
		};
	},
	computed: {
		isEmailNotificationsEnabled() {
			return this.client.notification_settings &&
				Array.isArray(this.client.notification_settings) &&
				this.client.notification_settings.includes('email');
		},

		isTelegramNotificationsEnabled() {
			return this.client.notification_settings &&
				Array.isArray(this.client.notification_settings) &&
				this.client.notification_settings.includes('telegram');
		},

		isToastNotificationsEnabled() {
			return this.client.notification_settings &&
				Array.isArray(this.client.notification_settings) &&
				this.client.notification_settings.includes('toast');
		}
	},
	methods: {
		async toggleEmailNotifications() {
			await this.updateNotificationSettings('email');
		},

		async toggleTelegramNotifications() {
			await this.updateNotificationSettings('telegram');
		},

		async toggleToastNotifications() {
			await this.updateNotificationSettings('toast');
		},

		saveSoundSettings() {
			localStorage.setItem('soundSettings', JSON.stringify(this.soundSettings));
		},

		updateHeaderUserData(newSettings) {
			const headerElement = document.querySelector('#header-app');
			if (headerElement?.dataset?.user) {
				try {
					const user = JSON.parse(headerElement.dataset.user);
					user.notification_settings = newSettings;
					headerElement.dataset.user = JSON.stringify(user);
				} catch (e) {
					// ignore
				}
			}
		},

		async updateNotificationSettings(type) {
			try {
				const currentSettings = Array.isArray(this.client.notification_settings)
					? this.client.notification_settings
					: [];
				let newSettings;

				if (currentSettings.includes(type)) {
					newSettings = currentSettings.filter(setting => setting !== type);
				} else {
					newSettings = [...currentSettings, type];
				}

				const response = await axios.post('/profile/notification-settings', {
					notification_settings: newSettings
				});

				const data = response.data;
				if (data.success) {
					this.$emit('update-client', {
						notification_settings: newSettings
					});

					// Обновляем данные в header для корректной работы isToastEnabled()
					this.updateHeaderUserData(newSettings);

					const action = newSettings.includes(type) ? 'включены' : 'отключены';
					const channel = type === 'email' ? 'email' : type === 'telegram' ? 'Telegram' : 'всплывающие уведомления';
					const message = type === 'toast' ? `Всплывающие уведомления ${action}` : `Уведомления на ${channel} ${action}`;
					// force: true только для toast чтобы показать подтверждение
					window.toast.success(message, { force: type === 'toast' });
				}
			} catch (error) {
				console.error('Update notification settings error:', error);
				const errorMessage = error.response?.data?.message || 'Произошла ошибка при обновлении настроек уведомлений';
				window.toast.error(errorMessage);
			}
		}
	}
}
</script>
