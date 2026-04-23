<template>
	<section id="Cart" class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden">
		<div class="container">
			<div class="change-profile-content">
				<div class="title">
					<div class="loader-line d-none d-lg-block"></div>
					<h3>Корзина</h3>
					<p class="mp-cart-subtitle d-lg-none mt-2">{{ cartItems.length }} {{ pluralItems(cartItems.length)
					}}
						готовы к выводу</p>
				</div>

				<!-- Loading state -->
				<div v-if="isLoading" class="text-center py-5">
					<div class="loader-gif">
						<div class="radar-ring"></div>
						<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
					</div>
					<p class="mt-3">Загружаем корзину...</p>
				</div>

				<!-- Cart items -->
				<div v-else-if="cartItems.length > 0" class="product-box-section section-b-space">

					<!-- Mobile: список карточек -->
					<div class="mp-cart-list d-lg-none d-flex flex-column gap-3 mb-4">
						<div v-for="item in cartItems" :key="'m-' + item.listing_id" class="mp-cart-card"
							:class="item.item?.rarity ? 'mp-rarity-' + item.item.rarity : ''">
							<a :href="`/marketplace/${item.listing_id}`" class="mp-cart-img"
								:style="{ backgroundImage: 'url(' + (item.item?.image_url || '/images/skin_no_image.svg') + ')' }"></a>
							<div class="mp-cart-info">
								<div class="mp-cart-top">
									<h6 class="mp-cart-name">{{ item.item?.name }}</h6>
									<button class="mp-cart-del" @click="removeFromCart(item.listing_id)"
										title="Удалить">
										<i class="ri-delete-bin-line"></i>
									</button>
								</div>
								<div class="mp-cart-meta">
									<span v-if="item.item?.rarity_translated">{{ item.item.rarity_translated }}</span>
									<span v-if="item.wear_name">| {{ item.wear_name }}</span>
								</div>
								<div class="mp-cart-bottom">
									<span class="mp-cart-price" v-html="formatPrice(item.price, 'RUB')"></span>
									<button class="mp-cart-select"
										:class="{ 'is-selected': isItemSelected(item.listing_id) }"
										@click="toggleItemSelection(item.listing_id)">
										{{ isItemSelected(item.listing_id) ? 'Выбрано' : 'Выбрать' }}
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Mobile: итоги + оплата -->
					<div class="mp-cart-summary d-lg-none">
						<div class="mp-cart-row"><span>Предметы ({{ selectedCount }})</span><strong
								v-html="formatPrice(selectedTotal, 'RUB')"></strong></div>
						<div class="mp-cart-row"><span>Скидка</span><strong class="text-success"
								v-html="formatPrice(0, 'RUB')"></strong></div>
						<div class="mp-cart-row mp-cart-row-total"><span>СУММА</span><strong
								v-html="formatPrice(selectedTotal, 'RUB')"></strong></div>
						<button class="mp-cart-pay" :class="{ disabled: selectedCount === 0 }"
							@click="proceedToCheckout">
							<i class="m-ico m-ico-pay"></i>
							<span>Оформить заказ</span>
						</button>
					</div>

					<!-- Cart summary -->
					<div class="cart-summary mb-4 p-3 bg-color rounded d-none d-lg-block">
						<div class="row align-items-center">
							<div class="col-md-6">
								<div class="form-check p-0 mb-2">
									<input type="checkbox" class="checkbox-animated" id="selectAll"
										:checked="allSelected" @change="toggleSelectAll">
									<label class="form-check-label" for="selectAll">
										<span class="name">Выбрать все</span>
									</label>
								</div>
								<h5 class="mb-1">Выбрано: {{ selectedCount }} из {{ cartItems.length }}</h5>
								<p class="text-muted mb-0">К оплате: <strong
										v-html="formatPrice(selectedTotal, 'RUB')"></strong></p>
							</div>
							<div class="col-md-6 text-end">
								<button class="btn theme-btn me-2" @click="proceedToCheckout"
									:class="{ disabled: selectedCount === 0 }">
									<i class="ri-shopping-cart-line me-1"></i>Оформить заказ
								</button>
								<button class="btn theme-outline theme-outline-danger" @click="clearCart">
									<i class="ri-delete-bin-line me-1"></i>Очистить
								</button>
							</div>
						</div>
					</div>

					<!-- Cart items list -->
					<div class="cart-items-box product-details-box-list d-none d-lg-block">
						<div v-for="item in cartItems" :key="item.listing_id" class="product-details-box gap-2">
							<div class="form-check me-2">
								<input type="checkbox" class="checkbox-animated" :id="'item-' + item.listing_id"
									:checked="isItemSelected(item.listing_id)"
									@change="toggleItemSelection(item.listing_id)">
								<label class="form-check-label" :for="'item-' + item.listing_id"></label>
							</div>
							<a :href="`/marketplace/${item.listing_id}`" class="product-img"
								:style="{ backgroundImage: 'url(' + (item.item?.image_url || '/images/skin_no_image.svg') + ')' }"
								title="Перейти к товару">
							</a>
							<div
								class="description d-flex flex-column flex-lg-row align-items-center justify-content-between flex-grow-1 gap-3">
								<div>
									<div class="d-flex align-items-center gap-2">
										<a :href="`/marketplace/${item.listing_id}`" class="product-name-link"
											title="Перейти к товару">
											<h6 class="product-name">{{ item.item?.name }}</h6>
										</a>
										<span v-if="item.is_stattrak" class="badge bg-warning">StatTrak™</span>
										<span v-if="item.is_souvenir" class="badge bg-info">Souvenir</span>
									</div>
									<div class="rating-section">
										<div class="d-flex align-items-center gap-2">
											<span v-if="item.wear_value" class="badge bg-secondary">
												{{ item.wear_name }}
											</span>
											<small class="text-muted">{{ formatDate(item.added_at) }}</small>
										</div>
									</div>
									<p class="text-muted mb-0">{{ item.seller.name }}</p>
								</div>
								<div class="h-100 d-flex flex-column justify-content-between">
									<div class="product-box-price text-muted text-center mb-3">
										<span class="fw-bold fs-5" v-html="formatPrice(item.price, 'RUB')"></span>
									</div>
									<div class="d-flex justify-content-end">
										<button class="btn theme-outline theme-outline-danger"
											@click="removeFromCart(item.listing_id)" title="Удалить из корзины">
											<i class="ri-delete-bin-line me-1"></i>Удалить
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Empty cart state -->
				<div v-else class="text-center py-5">
					<i class="ri-shopping-cart-line display-4 text-muted mb-3"></i>
					<h4>Корзина пуста</h4>
					<p class="text-muted mb-4">Добавьте товары из маркетплейса для покупки</p>
					<a :href="routes.marketplace" class="btn theme-outline me-sm-2 mb-2 mb-sm-0">
						<i class="ri-store-2-line me-2"></i>Перейти в маркетплейс
					</a>
					<a v-if="!user" :href="routes.login" class="btn theme-btn"><i class="ri-steam-fill me-1"></i>Войти
						через Steam</a>
				</div>
			</div>
		</div>
	</section>

	<!-- Модальное окно подтверждения очистки корзины -->
	<div class="modal fade" id="confirmClearModal" tabindex="-1" aria-labelledby="confirmClearModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmClearModalLabel">
						<i class="ri-delete-bin-line me-2 text-danger"></i>Очистить корзину
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p>Вы уверены, что хотите очистить корзину? Все товары будут удалены.</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn theme-btn-danger" @click="confirmClearCart">
						<i class="ri-delete-bin-line me-1"></i>Очистить
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Модальное окно подтверждения удаления товара -->
	<div class="modal fade" id="confirmRemoveModal" tabindex="-1" aria-labelledby="confirmRemoveModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="confirmRemoveModalLabel">
						<i class="ri-delete-bin-line me-2 text-danger"></i>Удалить товар
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div v-if="itemToRemove" class="mb-3">
						<div class="d-flex align-items-center">
							<div class="product-img me-3"
								:style="{ backgroundImage: 'url(' + (itemToRemove.item?.image_url || '/images/skin_no_image.svg') + ')', width: '64px', height: '64px', backgroundSize: 'contain', backgroundRepeat: 'no-repeat', backgroundPosition: 'center' }">
							</div>
							<div>
								<h6 class="mb-1">{{ itemToRemove.item?.name }}</h6>
								<small class="text-muted" v-html="formatPrice(itemToRemove.price, 'RUB')"></small>
							</div>
						</div>
					</div>
					<p>Вы уверены, что хотите удалить этот товар из корзины?</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" data-bs-dismiss="modal">Отмена</button>
					<button type="button" class="btn theme-btn theme-btn-danger" @click="confirmRemoveFromCart">
						<i class="ri-delete-bin-line me-1"></i>Удалить
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';
import { formatPrice, handleApiError } from '../../shared/utils/helpers';
import { cartAPI } from '../../shared/utils/api';

export default {
	name: 'Cart',
	props: {
		user: {
			type: Object,
			default: null
		},
		routes: {
			type: Object,
			required: true
		}
	},
	setup() {
		return { formatPrice };
	},
	data() {
		return {
			cartItems: [],
			cartTotal: 0,
			cartCount: 0,
			isLoading: false,
			itemToRemove: null,
			selectedItems: []
		}
	},
	computed: {
		allSelected() {
			return this.cartItems.length > 0 && this.selectedItems.length === this.cartItems.length;
		},
		selectedTotal() {
			return this.cartItems
				.filter(item => this.selectedItems.includes(item.listing_id))
				.reduce((sum, item) => sum + parseFloat(item.price), 0);
		},
		selectedCount() {
			return this.selectedItems.length;
		}
	},
	methods: {
		async loadCart() {
			this.isLoading = true;
			try {
				const response = await axios.get('/api/cart');
				const data = response.data;

				if (data.success) {
					this.cartItems = data.data.items;
					this.cartTotal = data.data.total;
					this.cartCount = data.data.count;

					// По умолчанию выбираем все товары
					this.selectedItems = this.cartItems.map(item => item.listing_id);

					// Показываем предупреждения если были удалены недоступные товары
					if (data.warnings && data.warnings.removed_items.length > 0) {
						window.toast.warning(data.warnings.message);
					}
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Error loading cart:', error);
				// Глобальный обработчик покажет toast автоматически
			} finally {
				this.isLoading = false;
			}
		},

		toggleSelectAll() {
			if (this.allSelected) {
				this.selectedItems = [];
			} else {
				this.selectedItems = this.cartItems.map(item => item.listing_id);
			}
		},

		toggleItemSelection(listingId) {
			const index = this.selectedItems.indexOf(listingId);
			if (index > -1) {
				this.selectedItems.splice(index, 1);
			} else {
				this.selectedItems.push(listingId);
			}
		},

		isItemSelected(listingId) {
			return this.selectedItems.includes(listingId);
		},

		proceedToCheckout() {
			if (this.selectedItems.length === 0) {
				window.toast.warning('Выберите товары для покупки');
				return;
			}
			// Сохраняем выбранные товары в sessionStorage
			sessionStorage.setItem('checkout_items', JSON.stringify(this.selectedItems));
			window.location.href = this.routes.checkout;
		},

		removeFromCart(listingId) {
			const item = this.cartItems.find(item => item.listing_id === listingId);
			if (item) {
				this.itemToRemove = item;
				const modal = new bootstrap.Modal(document.getElementById('confirmRemoveModal'));
				modal.show();
			}
		},

		async confirmRemoveFromCart() {
			if (!this.itemToRemove) return;

			try {
				const response = await axios.delete(`/api/cart/${this.itemToRemove.listing_id}`);
				const data = response.data;

				if (data.success) {
					// Удаляем товар из локального массива
					this.cartItems = this.cartItems.filter(item => item.listing_id !== this.itemToRemove.listing_id);
					this.cartCount = data.cart_count;
					this.cartTotal = data.cart_total;
					window.toast.success(data.message || 'Товар удален из корзины');

					// Обновляем счетчик в header
					this.updateCartCount();
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Remove from cart error:', error);
				// Глобальный обработчик покажет toast автоматически
			} finally {
				// Закрываем модальное окно
				const modal = bootstrap.Modal.getInstance(document.getElementById('confirmRemoveModal'));
				if (modal) {
					modal.hide();
				}
				this.itemToRemove = null;
			}
		},

		clearCart() {
			const modal = new bootstrap.Modal(document.getElementById('confirmClearModal'));
			modal.show();
		},

		async confirmClearCart() {
			try {
				const data = await cartAPI.clearCart();

				if (data.success) {
					this.cartItems = [];
					this.cartTotal = 0;
					this.cartCount = 0;
					window.toast.success(data.message || 'Корзина очищена');

					// Обновляем счетчик в header
					this.updateCartCount();
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Clear cart error:', error);
				// Глобальный обработчик покажет toast автоматически
			} finally {
				// Закрываем модальное окно
				const modal = bootstrap.Modal.getInstance(document.getElementById('confirmClearModal'));
				if (modal) {
					modal.hide();
				}
			}
		},

		updateCartCount() {
			// Диспатчим событие для обновления мини-корзины в хедере
			const event = new CustomEvent('cart-updated', {
				detail: {
					count: this.cartCount,
					total: this.cartTotal
				}
			});
			window.dispatchEvent(event);
		},


		pluralItems(n) {
			const mod10 = n % 10, mod100 = n % 100;
			if (mod10 === 1 && mod100 !== 11) return 'предмет';
			if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return 'предмета';
			return 'предметов';
		},

		formatDate(dateString) {
			return new Date(dateString).toLocaleDateString('ru-RU', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			});
		},

		handleCurrencyChange() {
			// Принудительно обновляем данные для пересчета цен
			if (this.cartItems.length > 0) {
				this.cartItems = [...this.cartItems];
			}
		}
	},

	mounted() {
		this.loadCart();

		// Слушаем события смены валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);
	},

	beforeUnmount() {
		// Убираем слушатель при размонтировании
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
	}
}
</script>