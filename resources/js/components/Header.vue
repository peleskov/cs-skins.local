<template>
	<header>
		<div class="container-fluid">
			<nav class="navbar navbar-expand-lg p-0">
				<button class="navbar-toggler" type="button" data-bs-toggle="collapse"
					data-bs-target="#offcanvasNavbar">
					<span class="navbar-toggler-icon">
						<i class="ri-menu-line"></i>
					</span>
				</button>
				<a class="me-lg-3 me-xl-5" :href="routes.home">
					<img class="img-fluid logo" :src="logoUrl" alt="logo">
				</a>
				<div class="nav-option order-md-2">
					<!-- Language Selector -->
					<LanguageSelector />

					<!-- Currency Selector -->
					<CurrencySelector />

					<!-- Favorites Button -->
					<div v-if="user" class="dropdown-button">
						<a :href="routes.profile + '#favorites'" class="cart-button favorites-button">
							<i class="ri-heart-line text-white cart-bag"></i>
						</a>
					</div>

					<!-- Cart Button -->
					<div class="dropdown-button">
						<a :href="routes.cart" class="cart-button">
							<span v-if="cartCount > 0" class="cart-count">{{ cartCount }}</span>
							<i class="ri-shopping-cart-line text-white cart-bag"></i>
						</a>
						<div class="onhover-box cart-dropdown" @mouseenter="loadCartPreview">
							<div class="cart-dropdown-content">
								<!-- Пустая корзина -->
								<p v-if="cartCount === 0" class="cart-empty-message">
									{{ translate('cart.empty') }}
								</p>

								<!-- Загрузка -->
								<p v-else-if="isLoading" class="cart-empty-message">
									{{ translate('cart.loading') }}
								</p>
								
								<!-- Товары в корзине -->
								<div v-else-if="cartItems.length > 0">
									<div class="cart-items">
										<div v-for="item in displayItems" :key="item.listing_id" 
											class="cart-item-preview d-flex align-items-center mb-2">
											<img :src="item.item?.image_url || '/images/skin_no_image.svg'" :alt="item.item?.name || translate('cart.unknown_item')"
												style="width: 40px; height: 30px; object-fit: contain;" class="me-2">
											<div class="flex-grow-1">
												<div class="cart-item-name text-truncate" style="font-size: 12px;">
													{{ item.item?.name }}
												</div>
												<div class="cart-item-price text-muted" style="font-size: 11px;">
													<span v-html="formatPrice(item.price, 'RUB')"></span>
												</div>
											</div>
										</div>
										<div v-if="cartItems.length > 3" class="text-muted text-center" style="font-size: 11px;">
											{{ translate('cart.items_more').replace(':count', cartItems.length - 3) }}
										</div>
									</div>
									<div class="cart-total text-center border-top pt-2">
										<strong>{{ translate('cart.total') }} <span v-html="formatPrice(cartTotal, 'RUB')"></span></strong>
									</div>
									<div class="cart-actions">
										<a :href="routes.cart" class="btn theme-btn btn-sm w-100">{{ translate('cart.go_to_cart') }}</a>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- User Balance -->
					<div v-if="user && user.balance !== undefined" class="user-balance d-none d-md-block">
						<a :href="routes.profile + '#balance'" class="text-white text-decoration-none">
							<i class="ri-wallet-line me-1"></i>
							<span class="balance-amount" v-html="formatPrice(user.balance, 'RUB')"></span>
						</a>
					</div>
					
					<!-- Профиль или вход -->
					<div v-if="user" class="profile-part dropdown-button order-md-2">
						<img class="img-fluid profile-pic" :src="user.steam_avatar" alt="profile" 
							style="width: 40px; height: 40px; border-radius: 50%;">
						<div>
							<h6 class="fw-normal">{{ translate('auth.hello') }}</h6>
							<h5 class="fw-medium">{{ user.name }}</h5>
						</div>
						<div class="onhover-box onhover-sm">
							<ul class="menu-list">
								<li v-for="(tab, key) in profileTabs" :key="key">
									<a class="dropdown-item" :href="routes.profile + '#' + key">
										<i :class="tab.icon + ' me-2'"></i>{{ tab.title }}
									</a>
								</li>
							</ul>
							<div class="bottom-btn">
								<a :href="routes.logout" class="theme-color fw-medium d-flex">
									<i class="ri-logout-box-r-line me-2"></i>{{ translate('auth.logout') }}
								</a>
							</div>
						</div>
					</div>
					<a v-else :href="routes.login" class="btn btn-sm theme-btn">
						<i class="ri-steam-fill me-1"></i>{{ translate('auth.login_steam') }}
					</a>
				</div>
				<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar">
					<div class="offcanvas-header">
						<h5 class="offcanvas-title" id="offcanvasNavbarLabel">{{ translate('ui.menu') }}</h5>
						<button class="navbar-toggler btn-close" id="offcanvas-close"></button>
					</div>
					<div class="offcanvas-body">
						<ul class="navbar-nav justify-content-start flex-grow-1">
							<li v-for="(item, key) in mainNavigation" :key="key" class="nav-item">
								<template v-if="!item.auth_required || user">
									<a class="nav-link pt-1" :href="getNavigationUrl(item.route)">{{ item.title }}</a>
								</template>
							</li>
						</ul>
					</div>
				</div>
			</nav>
		</div>
	</header>
</template>

<script>
import { formatPrice } from '../utils/helpers';
import { cartAPI } from '../utils/api';
import CurrencySelector from './CurrencySelector.vue';
import LanguageSelector from './LanguageSelector.vue';

export default {
	name: 'Header',
	components: {
		CurrencySelector,
		LanguageSelector
	},
	setup() {
		return { formatPrice };
	},
	props: {
		user: {
			type: Object,
			default: null
		},
		routes: {
			type: Object,
			required: true
		},
		logoUrl: {
			type: String,
			required: true
		},
		initialCartCount: {
			type: Number,
			default: 0
		}
	},
	data() {
		return {
			cartCount: this.initialCartCount,
			cartItems: [],
			cartTotal: 0,
			isLoading: false,
			cartLoaded: false, // Флаг, что корзина была загружена
			profileTabs: window.profileTabs || {},
			mainNavigation: window.mainNavigation || {}
		}
	},
	computed: {
		displayItems() {
			return this.cartItems.slice(0, 3);
		}
	},
	methods: {
		translate(key) {
			// Разбираем ключ вида 'cart.empty' на ['cart', 'empty']
			const keys = key.split('.');
			let translation = window.translations;

			// Проходим по всем уровням вложенности
			for (const k of keys) {
				if (translation && typeof translation === 'object' && translation[k]) {
					translation = translation[k];
				} else {
					return key; // Возвращаем исходный ключ если перевод не найден
				}
			}

			return translation || key;
		},

		async loadCartPreview() {
			if (this.isLoading || this.cartLoaded) return; // Не загружаем повторно, если уже загружено
			
			this.isLoading = true;
			try {
				const data = await cartAPI.getItems();

				if (data.success) {
					this.cartItems = data.data.items;
					this.cartTotal = data.data.total;
					this.cartCount = data.data.count;
					this.cartLoaded = true; // Отмечаем, что корзина загружена
				}
			} catch (error) {
				console.error('Error loading cart preview:', error);
			} finally {
				this.isLoading = false;
			}
		},

		updateCartFromEvent(event) {
			this.cartCount = event.detail.count;
			// Сбрасываем флаг загрузки, чтобы перезагрузить данные
			this.cartLoaded = false;
			// Если корзина обновилась, перезагружаем превью
			this.loadCartPreview();
		},

		getNavigationUrl(route) {
			// Если маршрут начинается с #, возвращаем как есть
			if (route.startsWith('#')) {
				return route;
			}
			// Иначе используем маршрут из routes prop
			return this.routes[route] || '#';
		},

		handleCurrencyChange() {
			// Принудительно обновляем данные для пересчета цен
			if (this.cartItems.length > 0) {
				this.cartItems = [...this.cartItems];
			}
		}

	},

	async mounted() {
		// Счетчик корзины уже передан через props (initialCartCount)
		// Если есть товары в корзине, загружаем превью
		if (this.cartCount > 0) {
			await this.loadCartPreview();
		}

		// Слушаем события обновления корзины
		window.addEventListener('cart-updated', this.updateCartFromEvent);
		
		// Слушаем события смены валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);
	},

	beforeUnmount() {
		// Убираем слушатели при размонтировании
		window.removeEventListener('cart-updated', this.updateCartFromEvent);
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
	}
}
</script>