<template>
	<nav class="mobile-bottom-nav position-fixed start-0 end-0 bottom-0 d-flex align-items-stretch">
		<template v-for="item in resolvedItems" :key="item.key">
			<a v-if="!item.requiresAuth || user" :href="routes[item.route] || '#'"
				class="mbn-item flex-fill d-flex flex-column align-items-center justify-content-center text-decoration-none position-relative"
				:class="{ active: isActive(item.route) }">
				<i class="m-ico" :class="item.icon"></i>
				<span>{{ item.label }}</span>
				<span v-if="item.key === 'cart' && cartCount > 0" class="mbn-badge position-absolute">{{ cartCount }}</span>
			</a>
		</template>
	</nav>
</template>

<script>
export default {
	name: 'MobileBottomNav',
	props: {
		user: { type: Object, default: null },
		routes: { type: Object, required: true },
		initialCartCount: { type: Number, default: 0 },
		items: { type: Array, default: null }
	},
	computed: {
		resolvedItems() {
			if (this.items && this.items.length) return this.items;
			return [
				{ key: 'marketplace', label: 'МАРКЕТ', icon: 'm-ico-mplace', route: 'marketplace' },
				{ key: 'auctions', label: 'АУКЦИОН', icon: 'm-ico-auctions', route: 'auctions' },
				{ key: 'cases', label: 'КЕЙСЫ', icon: 'm-ico-cases', route: 'cases', requiresAuth: true },
				{ key: 'cart', label: 'КОРЗИНА', icon: 'm-ico-cart', route: 'cart' },
				{ key: 'profile', label: 'ПРОФИЛЬ', icon: 'm-ico-profile', route: 'profile', requiresAuth: true }
			];
		}
	},
	data() {
		return {
			cartCount: this.initialCartCount,
			currentPath: window.location.pathname
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
		}
	},
	mounted() {
		window.addEventListener('cart-updated', this.updateCart);
	},
	beforeUnmount() {
		window.removeEventListener('cart-updated', this.updateCart);
	}
};
</script>
