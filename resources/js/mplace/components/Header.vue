<template>
	<header>
		<div class="container-fluid">
			<nav class="navbar navbar-expand-lg p-0 gap-0">
				<button class="navbar-toggler" type="button" data-bs-toggle="offcanvas"
					data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
					<span class="navbar-toggler-icon">
						<i class="ri-menu-line"></i>
					</span>
				</button>
				<a class="me-lg-3 me-xl-4" :href="routes.home">
					<img class="img-fluid logo" :src="logoUrl" alt="logo">
				</a>
				<!-- Main Navigation Menu (Desktop) -->
				<ul class="navbar-nav d-none d-lg-flex gap-0">
					<template v-for="(item, key) in mainNavigation" :key="key">
						<li v-if="!item.auth_required || user" class="nav-item me-lg-3 me-xl-4">
							<a class="nav-link" :href="getNavigationUrl(item.route)">{{ item.title }}</a>
						</li>
					</template>
				</ul>
				<div class="nav-option order-md-2">
					<!-- Language Selector -->
					<LanguageSelector />

					<!-- Currency Selector -->
					<CurrencySelector />

					<!-- Favorites Button -->
					<div v-if="user" class="dropdown-button">
						<a :href="routes.profile + '#favorites'" class="cart-button favorites-button">
							<span v-if="favoritesCount > 0" class="cart-count">{{ favoritesCount }}</span>
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
											<img :src="item.item?.image_url || '/images/skin_no_image.svg'"
												:alt="item.item?.name || translate('cart.unknown_item')"
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
										<div v-if="cartItems.length > 3" class="text-muted text-center"
											style="font-size: 11px;">
											{{ translate('cart.items_more').replace(':count', cartItems.length - 3) }}
										</div>
									</div>
									<div class="cart-total text-center border-top pt-2">
										<strong>{{ translate('cart.total') }} <span
												v-html="formatPrice(cartTotal, 'RUB')"></span></strong>
									</div>
									<div class="cart-actions">
										<a :href="routes.cart" class="btn theme-btn btn-sm w-100">{{
											translate('cart.go_to_cart') }}</a>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- User Balance -->
					<div v-if="user && currentBalance !== undefined" class="user-balance d-none d-md-block">
						<a :href="routes.profile + '#balance'" class="btn theme-btn text-decoration-none">
							<i class="ri-add-circle-line me-1"></i>
							<span class="balance-amount" v-html="formatPrice(currentBalance, 'RUB')"></span>
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
								<a class="dropdown-item" href="#" data-bs-toggle="modal"
									data-bs-target="#extensionModal">
									<i class="ri-download-2-line me-2"></i>Расширение
								</a>
							</div>
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
			</nav>
		</div>
	</header>
	<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar">
		<div class="offcanvas-header">
			<h5 class="offcanvas-title" id="offcanvasNavbarLabel">{{ translate('ui.menu') }}</h5>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body d-flex flex-column">
			<!-- Профиль пользователя -->
			<div v-if="user" class="mb-3 pb-3 border-bottom">
				<div class="d-flex align-items-center mb-3">
					<img class="rounded-circle me-2" :src="user.steam_avatar" alt="profile"
						style="width: 50px; height: 50px;">
					<div class="flex-grow-1">
						<div class="fw-medium content-color">{{ user.name }}</div>
						<small class="text-muted">{{ user.email || 'Email не указан' }}</small>
					</div>
				</div>
				<!-- Баланс -->
				<a :href="routes.profile + '#balance'" class="btn theme-outline btn-sm w-100 text-decoration-none"
					@click="closeOffcanvas">
					<i class="ri-add-circle-line me-1"></i>
					<span v-html="formatPrice(currentBalance, 'RUB')"></span>
				</a>
			</div>

			<!-- Корзина -->
			<ul class="w-100 navbar-nav mb-3 pb-3 border-bottom">
				<li class="nav-item mb-1">
					<a class="nav-link mb-0 p-0 content-color" :href="routes.cart">
						<span>
							<i class="ri-shopping-cart-line me-2"></i>
							Корзина
						</span>
						<span v-if="cartCount > 0" class="badge bg-primary ms-2">{{ cartCount }}</span>
					</a>
				</li>
			</ul>


			<!-- Язык и валюта -->
			<div class="mb-3 pb-3 border-bottom">
				<div class="row g-2">
					<div class="col-6">
						<LanguageSelector />
					</div>
					<div class="col-6">
						<CurrencySelector />
					</div>
				</div>
			</div>

			<!-- Основное меню -->
			<ul class="navbar-nav mb-3 pb-3 border-bottom">
				<template v-for="(item, key) in mainNavigation" :key="key">
					<li v-if="!item.auth_required || user" class="nav-item mb-1 content-color">
						<a class="nav-link  mb-0 p-0" :href="getNavigationUrl(item.route)">{{ item.title }}</a>
					</li>
				</template>
			</ul>

			<!-- Меню профиля -->
			<ul v-if="user" class="navbar-nav mb-3 pb-3 border-bottom">
				<li v-for="(tab, key) in profileTabs" :key="key" class="mb-1 content-color">
					<a :href="routes.profile + '#' + key" class="nav-link  mb-0 p-0" @click="closeOffcanvas">
						{{ tab.title }}
					</a>
				</li>
				<li class="mb-1">
					<a v-if="isWebStoreExtension" :href="extensionDownloadUrl" target="_blank" rel="noopener"
						class="nav-link  mb-0 p-0 content-color">
						<i class="ri-chrome-line me-2"></i>Расширение
					</a>
					<a v-else href="#" data-bs-toggle="modal" data-bs-target="#extensionModal"
						class="nav-link  mb-0 p-0 content-color">
						<i class="ri-download-2-line me-2"></i>Расширение
					</a>
				</li>
			</ul>

			<!-- Вход/Выход -->
			<div class="mt-auto pt-3">
				<a v-if="user" :href="routes.logout" class="btn theme-outline w-100">
					<i class="ri-logout-box-r-line me-2"></i>{{ translate('auth.logout') }}
				</a>
				<a v-else :href="routes.login" class="btn theme-btn w-100">
					<i class="ri-steam-fill me-1"></i>{{ translate('auth.login_steam') }}
				</a>
			</div>
		</div>
	</div>

	<!-- Extension Installation Modal -->
	<div class="modal fade" id="extensionModal" tabindex="-1" aria-labelledby="extensionModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="extensionModalLabel">
						<i class="ri-chrome-line me-2"></i>Расширение для браузера
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p class="mb-3">Установите расширение для удобной работы с CS-SKINS прямо из Steam Community.</p>

					<div class="alert alert-info mb-3">
						<strong>Инструкция по установке:</strong>
						<ol class="mb-0 mt-2 ps-3">
							<li>Скачайте файл расширения</li>
							<li>Распакуйте архив в любую папку</li>
							<li>Скопируйте и вставьте в адресную строку: <code>chrome://extensions/</code></li>
							<li>Включите "Режим разработчика" (Developer mode)</li>
							<li>Нажмите "Загрузить распакованное расширение"</li>
							<li>Выберите папку с распакованным расширением</li>
						</ol>
					</div>

					<div class="text-center">
						<a :href="extensionDownloadUrl" download class="btn theme-btn">
							<i class="ri-download-2-line me-2"></i>Скачать расширение
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { formatPrice } from '../../shared/utils/helpers';
import { cartAPI } from '../../shared/utils/api';
import CurrencySelector from '../../shared/components/CurrencySelector.vue';
import LanguageSelector from '../../shared/components/LanguageSelector.vue';

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
		},
		initialFavoritesCount: {
			type: Number,
			default: 0
		},
		extensionDownloadUrl: {
			type: String,
			required: true
		}
	},
	data() {
		return {
			currentBalance: this.user ? this.user.balance : 0,
			cartCount: this.initialCartCount,
			favoritesCount: this.initialFavoritesCount,
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
		},
		isWebStoreExtension() {
			return this.extensionDownloadUrl && this.extensionDownloadUrl.startsWith('https://chromewebstore.google.com/');
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

		updateFavoritesFromEvent(event) {
			this.favoritesCount = event.detail.count;
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
			this.$forceUpdate();
		},

		handleBalanceUpdate(event) {
			if (event.detail && event.detail.main !== undefined) {
				this.currentBalance = event.detail.main;
			}
		},

		closeOffcanvas() {
			// Даем время браузеру обработать переход по ссылке, затем закрываем offcanvas
			setTimeout(() => {
				const offcanvasElement = document.getElementById('offcanvasNavbar');
				if (offcanvasElement) {
					const offcanvasInstance = window.bootstrap.Offcanvas.getInstance(offcanvasElement);
					if (offcanvasInstance) {
						offcanvasInstance.hide();
					}
				}
			}, 100);
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

		// Слушаем события обновления избранного
		window.addEventListener('favorites-updated', this.updateFavoritesFromEvent);

		// Слушаем события смены валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);

		// Слушаем обновления баланса
		window.addEventListener('balance-updated', this.handleBalanceUpdate);
	},

	beforeUnmount() {
		// Убираем слушатели при размонтировании
		window.removeEventListener('cart-updated', this.updateCartFromEvent);
		window.removeEventListener('favorites-updated', this.updateFavoritesFromEvent);
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
		window.removeEventListener('balance-updated', this.handleBalanceUpdate);
	}
}
</script>