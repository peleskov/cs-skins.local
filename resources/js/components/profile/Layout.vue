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
						<li :class="{ active: activeTab === 'favorites' }">
							<i class="ri-heart-line"></i>
							<a href="#" @click.prevent="setActiveTab('favorites')">Избранное</a>
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
				<ProfileInfo v-if="activeTab === 'profile'" 
					:client="client" 
					:telegramBotName="telegramBotName"
					@update-client="updateClient" />

				<!-- Trading Tab -->
				<ProfileTrading v-else-if="activeTab === 'trading'" 
					:client="client" />

				<!-- Inventory Tab -->
				<ProfileInventory v-else-if="activeTab === 'inventory'" 
					:client="client" 
					@switch-to-trading="setActiveTab('trading')" />

				<!-- Favorites Tab -->
				<ProfileFavorites v-else-if="activeTab === 'favorites'" 
					:client="client" />

				<!-- Auctions Tab -->
				<ProfileAuctions v-else-if="activeTab === 'auctions'" 
					:client="client" />

				<!-- Balance Tab -->
				<ProfileBalance v-else-if="activeTab === 'balance'" 
					:client="client" />

				<!-- Settings Tab -->
				<ProfileSettings v-else-if="activeTab === 'settings'" 
					:client="client" />

				<!-- Other Tabs Placeholder -->
				<div v-else class="text-center py-5">
					<h4>{{ getTabTitle(activeTab) }}</h4>
					<p class="text-muted">Эта функция будет реализована позже</p>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import ProfileInventory from './Inventory.vue';
import ProfileInfo from './Info.vue';
import ProfileTrading from './Trading.vue';
import ProfileFavorites from './Favorites.vue';
import ProfileAuctions from './Auctions.vue';
import ProfileBalance from './Balance.vue';
import ProfileSettings from './Settings.vue';

export default {
	name: 'ProfileLayout',
	components: {
		ProfileInventory,
		ProfileInfo,
		ProfileTrading,
		ProfileFavorites,
		ProfileAuctions,
		ProfileBalance,
		ProfileSettings
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

	data() {
		// Получаем начальную вкладку
		const getInitialTab = () => {
			// Проверяем, что мы в браузере
			if (typeof window === 'undefined') {
				return 'profile';
			}

			// Проверяем hash в URL
			const hash = window.location.hash.substring(1);
			if (hash && ['profile', 'trading', 'inventory', 'favorites', 'auctions', 'balance', 'settings'].includes(hash)) {
				return hash;
			}

			// Проверяем localStorage
			try {
				const savedTab = localStorage.getItem('profile-active-tab');
				if (savedTab && ['profile', 'trading', 'inventory', 'favorites', 'auctions', 'balance', 'settings'].includes(savedTab)) {
					return savedTab;
				}
			} catch (e) {
				// localStorage недоступен
			}

			return 'profile';
		};

		return {
			client: { ...this.initialClient },
			activeTab: getInitialTab()
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

		},

		updateClient(updatedClient) {
			this.client = { ...this.client, ...updatedClient };
		},

		logout() {
			if (confirm('Вы уверены, что хотите выйти?')) {
				window.location.href = '/auth/logout';
			}
		},

		getTabTitle(tab) {
			const titles = {
				trading: 'Торговля',
				favorites: 'Избранное',
				auctions: 'Мои аукционы',
				balance: 'Баланс',
				settings: 'Настройки'
			};
			return titles[tab] || 'Раздел';
		},

		handleHashChange() {
			// Обрабатываем изменение хэша в URL
			const hash = window.location.hash.substring(1);
			if (hash && ['profile', 'trading', 'inventory', 'favorites', 'auctions', 'balance', 'settings'].includes(hash)) {
				this.setActiveTab(hash);
			}
		}
	},

	mounted() {
		// Слушаем изменения хэша в URL
		window.addEventListener('hashchange', this.handleHashChange);
	},

	beforeUnmount() {
		// Убираем слушатель при уничтожении компонента
		window.removeEventListener('hashchange', this.handleHashChange);
	}
}
</script>