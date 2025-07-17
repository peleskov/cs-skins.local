<template>
	<header>
		<div class="container">
			<nav class="navbar navbar-expand-lg p-0">
				<button class="navbar-toggler" type="button" data-bs-toggle="collapse"
					data-bs-target="#offcanvasNavbar">
					<span class="navbar-toggler-icon">
						<i class="ri-menu-line"></i>
					</span>
				</button>
				<a :href="routes.home">
					<img class="img-fluid logo" :src="logoUrl" alt="logo">
				</a>
				<div class="nav-option order-md-2">
					<div class="dropdown-button">
						<a :href="routes.cart" class="cart-button">
							<span v-if="cartCount > 0" class="cart-count">{{ cartCount }}</span>
							<i class="ri-shopping-cart-line text-white cart-bag"></i>
						</a>
						<div class="onhover-box cart-dropdown" @mouseenter="loadCartPreview">
							<div class="cart-dropdown-content">
								<!-- Пустая корзина -->
								<p v-if="cartCount === 0" class="cart-empty-message">
									Корзина пуста
								</p>
								
								<!-- Загрузка -->
								<p v-else-if="isLoading" class="cart-empty-message">
									Загружаем корзину...
								</p>
								
								<!-- Товары в корзине -->
								<div v-else-if="cartItems.length > 0">
									<div class="cart-items">
										<div v-for="item in displayItems" :key="item.listing_id" 
											class="cart-item-preview d-flex align-items-center mb-2">
											<img :src="item.item.image_url" :alt="item.item.name" 
												style="width: 40px; height: 30px; object-fit: contain;" class="me-2">
											<div class="flex-grow-1">
												<div class="cart-item-name text-truncate" style="font-size: 12px;">
													{{ item.item.name }}
												</div>
												<div class="cart-item-price text-muted" style="font-size: 11px;">
													{{ formatPrice(item.price) }} ₽
												</div>
											</div>
										</div>
										<div v-if="cartItems.length > 3" class="text-muted text-center" style="font-size: 11px;">
											И еще {{ cartItems.length - 3 }} товар(ов)
										</div>
									</div>
									<div class="cart-total text-center border-top pt-2">
										<strong>Итого: {{ formatPrice(cartTotal) }} ₽</strong>
									</div>
									<div class="cart-actions">
										<a :href="routes.cart" class="btn theme-btn btn-sm w-100">Перейти в корзину</a>
									</div>
								</div>
							</div>
						</div>
					</div>
					
					<!-- Профиль или вход -->
					<div v-if="user" class="profile-part dropdown-button order-md-2">
						<img class="img-fluid profile-pic" :src="user.steam_avatar" alt="profile" 
							style="width: 40px; height: 40px; border-radius: 50%;">
						<div>
							<h6 class="fw-normal">Привет,</h6>
							<h5 class="fw-medium">{{ user.name }}</h5>
						</div>
						<div class="onhover-box onhover-sm">
							<ul class="menu-list">
								<li>
									<a class="dropdown-item" :href="routes.profile + '#profile'">
										<i class="ri-user-3-line me-2"></i>Профиль
									</a>
								</li>
								<li>
									<a class="dropdown-item" :href="routes.profile + '#trading'">
										<i class="ri-shopping-bag-3-line me-2"></i>Торговля
									</a>
								</li>
								<li>
									<a class="dropdown-item" :href="routes.profile + '#inventory'">
										<i class="ri-treasure-map-line me-2"></i>Инвентарь
									</a>
								</li>
								<li>
									<a class="dropdown-item" :href="routes.profile + '#favorites'">
										<i class="ri-heart-line me-2"></i>Избранное
									</a>
								</li>
								<li>
									<a class="dropdown-item" :href="routes.profile + '#auctions'">
										<i class="ri-store-2-line me-2"></i>Мои аукционы
									</a>
								</li>
								<li>
									<a class="dropdown-item" :href="routes.profile + '#balance'">
										<i class="ri-bank-card-line me-2"></i>Баланс
									</a>
								</li>
								<li>
									<a class="dropdown-item" :href="routes.profile + '#settings'">
										<i class="ri-settings-3-line me-2"></i>Настройки
									</a>
								</li>
							</ul>
							<div class="bottom-btn">
								<a :href="routes.logout" class="theme-color fw-medium d-flex">
									<i class="ri-logout-box-r-line me-2"></i>Выйти
								</a>
							</div>
						</div>
					</div>
					<a v-else :href="routes.login" class="btn btn-sm theme-btn">
						<i class="ri-steam-fill me-1"></i>Войти через Steam
					</a>
				</div>
				<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar">
					<div class="offcanvas-header">
						<h5 class="offcanvas-title" id="offcanvasNavbarLabel">Menu</h5>
						<button class="navbar-toggler btn-close" id="offcanvas-close"></button>
					</div>
					<div class="offcanvas-body">
						<ul class="navbar-nav justify-content-center flex-grow-1">
							<li class="nav-item">
								<a class="nav-link" :href="routes.marketplace">Маркетплейс</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" href="#">Кейсы</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" href="#">Аукцион</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" :href="routes.faq">FAQ</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" :href="routes.contact">Контакты</a>
							</li>
						</ul>
					</div>
				</div>
			</nav>
		</div>
	</header>
</template>

<script>
import { useToast } from "vue-toastification";
import { formatPrice } from '../utils/helpers';
import { cartAPI } from '../utils/api';

export default {
	name: 'Header',
	setup() {
		const toast = useToast();
		return { toast, formatPrice };
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
		}
	},
	data() {
		return {
			cartCount: 0,
			cartItems: [],
			cartTotal: 0,
			isLoading: false,
			cartLoaded: false // Флаг, что корзина была загружена
		}
	},
	computed: {
		displayItems() {
			return this.cartItems.slice(0, 3);
		}
	},
	methods: {
		async loadCartCount() {
			try {
				const data = await cartAPI.getCount();

				if (data.success) {
					this.cartCount = data.count;
				}
			} catch (error) {
				console.error('Error loading cart count:', error);
			}
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

	},

	async mounted() {
		// Загружаем начальный счетчик корзины
		await this.loadCartCount();
		
		// Если есть товары в корзине, загружаем превью
		if (this.cartCount > 0) {
			await this.loadCartPreview();
		}

		// Слушаем события обновления корзины
		window.addEventListener('cart-updated', this.updateCartFromEvent);
	},

	beforeUnmount() {
		// Убираем слушатель при размонтировании
		window.removeEventListener('cart-updated', this.updateCartFromEvent);
	}
}
</script>