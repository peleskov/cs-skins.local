<template>
	<div class="mobile-header-wrap">
		<header
			class="mobile-header position-fixed top-0 start-0 end-0 d-flex align-items-center justify-content-between px-3 py-2"
			:class="{ 'is-hidden': isHidden }">
			<div class="d-flex align-items-center gap-2">
				<button
					class="mh-burger d-inline-flex align-items-center justify-content-center border-0 bg-transparent"
					type="button" @click="isOpen = true" aria-label="menu">
					<i class="m-ico m-ico-burger"></i>
				</button>
				<a class="mh-logo d-inline-flex align-items-center text-decoration-none" :href="routes.home">
					<img :src="logoUrl" alt="logo">
				</a>
				<div v-if="online !== null"
					class="mh-online d-flex flex-column align-items-center justify-content-center">
					<span>ONLINE</span>
					<span>{{ formattedOnline }}</span>
				</div>
			</div>
			<div class="d-flex align-items-center gap-2 ms-auto">
				<LanguageSelector class="mh-selector" />
				<CurrencySelector class="mh-selector" />
				<a v-if="user" :href="routes.profile" class="mh-avatar d-inline-block position-relative"
					:class="{ 'is-premium': user.is_premium }">
					<img :src="user.steam_avatar" alt="avatar"
						:style="user.avatar_border_color ? { borderColor: user.avatar_border_color } : {}">
				</a>
				<a v-else :href="routes.login"
					class="mh-steam d-inline-flex align-items-center gap-2 border-0 rounded-2 text-decoration-none">
					<i class="m-ico m-ico-steam"></i><span>STEAM</span>
				</a>
			</div>
		</header>

		<div class="mh-backdrop position-fixed top-0 start-0 w-100 h-100" :class="{ open: isOpen }"
			@click="isOpen = false"></div>

		<aside class="mh-drawer position-fixed top-0 start-0 bottom-0 d-flex flex-column overflow-hidden p-3"
			:class="{ open: isOpen }">
			<div class="mh-drawer-head p-3 mb-4">
				<template v-if="user">
					<div class="mh-profile-card d-flex align-items-center gap-3 mb-2">
						<div class="mh-profile-avatar-wrap position-relative"
							:class="{ 'is-premium': user.is_premium }">
							<img class="mh-profile-avatar" :src="user.steam_avatar" alt=""
								:style="user.avatar_border_color ? { borderColor: user.avatar_border_color } : {}">
						</div>
						<div class="d-flex flex-column gap-1 min-w-0">
							<div class="mh-profile-name">{{ user.name }}</div>
							<div v-if="user.is_premium" class="mh-profile-badge align-self-start">ПРЕМИУМ</div>
						</div>
					</div>
					<div class="mh-balance-card d-flex align-items-center gap-3">
						<div class="flex-grow-1 min-w-0">
							<div class="mh-balance-label">БАЛАНС</div>
							<div class="mh-balance-value" v-html="formatPrice(currentBalance, 'RUB')"></div>
						</div>
						<a :href="routes.profile + '#balance'"
							class="mh-balance-btn d-inline-flex align-items-center justify-content-center rounded-2 text-decoration-none"
							@click="isOpen = false" aria-label="Пополнить">
							<i class="m-ico m-ico-balance"></i>
						</a>
					</div>
				</template>
				<a v-else :href="routes.login"
					class="mh-login-btn d-flex align-items-center justify-content-center gap-2 w-100 rounded-3 text-decoration-none">
					<i class="m-ico m-ico-steam"></i><span>Войти через Steam</span>
				</a>
			</div>

			<div class="mh-drawer-body flex-grow-1 overflow-y-auto px-3 pb-4">
				<div class="mh-section mb-3">
					<div class="mh-section-title px-1 pb-2">НАВИГАЦИЯ</div>
					<ul class="mh-nav-list nav flex-column list-unstyled m-0 p-0">
						<li>
							<a :href="routes.marketplace"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none"
								:class="{ active: isActive('marketplace') }">
								<i class="m-ico m-ico-mplace"></i><span class="flex-grow-1">Маркетплейс</span>
							</a>
						</li>
						<li>
							<a :href="routes.auctions"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none"
								:class="{ active: isActive('auctions') }">
								<i class="m-ico m-ico-auctions"></i><span class="flex-grow-1">Аукционы</span>
							</a>
						</li>
						<li v-if="user">
							<a :href="routes.cases"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none"
								:class="{ active: isActive('cases') }">
								<i class="m-ico m-ico-cases"></i><span class="flex-grow-1">Кейсы</span>
							</a>
						</li>
						<li>
							<a :href="routes.cart"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none"
								:class="{ active: isActive('cart') }">
								<i class="m-ico m-ico-cart"></i><span class="flex-grow-1">Корзина</span>
								<span v-if="cartCount > 0" class="mh-badge">{{ cartCount }}</span>
							</a>
						</li>
						<li v-if="user">
							<a :href="routes.profile + '#favorites'"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none">
								<i class="m-ico m-ico-fav"></i><span class="flex-grow-1">Избранное</span>
							</a>
						</li>
						<li v-if="user">
							<a :href="routes.profile"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none"
								:class="{ active: isActive('profile') }">
								<i class="m-ico m-ico-profile"></i><span class="flex-grow-1">Профиль</span>
							</a>
						</li>
					</ul>
				</div>

				<div class="mh-section mb-3">
					<div class="mh-section-title px-1 pb-2">ПОМОЩЬ</div>
					<ul class="mh-nav-list nav flex-column list-unstyled m-0 p-0">
						<li>
							<a :href="routes.faq"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none">
								<i class="m-ico m-ico-faq"></i><span class="flex-grow-1">FAQ</span>
							</a>
						</li>
						<li>
							<a :href="routes.contact"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none">
								<i class="m-ico m-ico-contacts"></i><span class="flex-grow-1">Контакты</span>
							</a>
						</li>
					</ul>
				</div>

				<div v-if="user" class="mh-section mh-section-logout mt-2 pt-2">
					<ul class="mh-nav-list nav flex-column list-unstyled m-0 p-0">
						<li>
							<a :href="routes.logout"
								class="mh-nav-link d-flex align-items-center gap-3 rounded-2 text-decoration-none">
								<i class="m-ico m-ico-logout"></i><span class="flex-grow-1">Выход</span>
							</a>
						</li>
					</ul>
				</div>
			</div>
		</aside>
	</div>
</template>

<script>
import { formatPrice } from '../../shared/utils/helpers';
import LanguageSelector from '../../shared/components/LanguageSelector.vue';
import CurrencySelector from '../../shared/components/CurrencySelector.vue';

export default {
	name: 'MobileHeader',
	components: { LanguageSelector, CurrencySelector },
	setup() {
		return { formatPrice };
	},
	props: {
		user: { type: Object, default: null },
		routes: { type: Object, required: true },
		logoUrl: { type: String, required: true },
		initialCartCount: { type: Number, default: 0 },
		online: { type: Number, default: null }
	},
	computed: {
		formattedOnline() {
			return (this.online || 0).toLocaleString('en-US');
		}
	},
	data() {
		return {
			isOpen: false,
			cartCount: this.initialCartCount,
			currentBalance: this.user ? this.user.balance : 0,
			currentPath: window.location.pathname,
			isHidden: false,
			lastScrollY: 0,
			scrollTicking: false
		};
	},
	methods: {
		isActive(routeKey) {
			const url = this.routes[routeKey];
			if (!url || url === '#') return false;
			try {
				const p = new URL(url, window.location.origin).pathname;
				if (p === '/') return this.currentPath === '/';
				return this.currentPath === p || this.currentPath.startsWith(p + '/');
			} catch {
				return false;
			}
		},
		updateCart(e) {
			if (e.detail && typeof e.detail.count === 'number') this.cartCount = e.detail.count;
		},
		updateBalance(e) {
			if (e.detail && e.detail.main !== undefined) this.currentBalance = e.detail.main;
		},
		onScroll() {
			if (this.scrollTicking || this.isOpen) return;
			this.scrollTicking = true;
			requestAnimationFrame(() => {
				const y = window.scrollY;
				const delta = y - this.lastScrollY;
				if (y < 80) {
					this.isHidden = false;
				} else if (delta > 5) {
					this.isHidden = false;
				} else if (delta < -5) {
					this.isHidden = true;
				}
				this.lastScrollY = y;
				this.scrollTicking = false;
			});
		}
	},
	mounted() {
		window.addEventListener('cart-updated', this.updateCart);
		window.addEventListener('balance-updated', this.updateBalance);
		window.addEventListener('scroll', this.onScroll, { passive: true });
	},
	beforeUnmount() {
		window.removeEventListener('cart-updated', this.updateCart);
		window.removeEventListener('balance-updated', this.updateBalance);
		window.removeEventListener('scroll', this.onScroll);
		document.body.style.overflow = '';
	},
	watch: {
		isOpen(v) {
			document.body.style.overflow = v ? 'hidden' : '';
		}
	}
};
</script>
