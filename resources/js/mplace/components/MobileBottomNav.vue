<template>
	<nav class="mobile-bottom-nav position-fixed start-0 end-0 bottom-0 d-flex align-items-stretch">
		<a :href="routes.marketplace"
			class="mbn-item flex-fill d-flex flex-column align-items-center justify-content-center text-decoration-none position-relative"
			:class="{ active: isActive('marketplace') }">
			<i class="m-ico m-ico-mplace"></i>
			<span>МАРКЕТ</span>
		</a>
		<a :href="routes.auctions"
			class="mbn-item flex-fill d-flex flex-column align-items-center justify-content-center text-decoration-none position-relative"
			:class="{ active: isActive('auctions') }">
			<i class="m-ico m-ico-auctions"></i>
			<span>АУКЦИОН</span>
		</a>
		<a v-if="user" :href="routes.cases"
			class="mbn-item flex-fill d-flex flex-column align-items-center justify-content-center text-decoration-none position-relative"
			:class="{ active: isActive('cases') }">
			<i class="m-ico m-ico-cases"></i>
			<span>КЕЙСЫ</span>
		</a>
		<a :href="routes.cart"
			class="mbn-item flex-fill d-flex flex-column align-items-center justify-content-center text-decoration-none position-relative"
			:class="{ active: isActive('cart') }">
			<i class="m-ico m-ico-cart"></i>
			<span>КОРЗИНА</span>
			<span v-if="cartCount > 0" class="mbn-badge position-absolute">{{ cartCount }}</span>
		</a>
		<a v-if="user" :href="routes.profile"
			class="mbn-item flex-fill d-flex flex-column align-items-center justify-content-center text-decoration-none position-relative"
			:class="{ active: isActive('profile') }">
			<i class="m-ico m-ico-profile"></i>
			<span>ПРОФИЛЬ</span>
		</a>
	</nav>
</template>

<script>
export default {
	name: 'MobileBottomNav',
	props: {
		user: { type: Object, default: null },
		routes: { type: Object, required: true },
		initialCartCount: { type: Number, default: 0 }
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
