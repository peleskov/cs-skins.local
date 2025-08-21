<template>
	<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden bg-white">
		<div class="container">
			<div class="change-profile-content">
				<div class="title">
					<div class="loader-line"></div>
					<h3>Корзина</h3>
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
					<!-- Cart summary -->
					<div class="cart-summary mb-4 p-3 bg-light rounded">
						<div class="row align-items-center">
							<div class="col-md-6">
								<h5 class="mb-1">Товаров в корзине: {{ cartItems.length }}</h5>
								<p class="text-muted mb-0">Общая стоимость: <strong>{{ formatPrice(cartTotal, 'RUB') }}</strong>
								</p>
							</div>
							<div class="col-md-6 text-end">
								<a href="/checkout" class="btn theme-btn me-2"
								   :class="{ disabled: cartItems.length === 0 }">
									<i class="ri-shopping-cart-line me-1"></i>Оформить заказ
								</a>
								<button class="btn theme-outline theme-outline-danger" @click="clearCart">
									<i class="ri-delete-bin-line me-1"></i>Очистить
								</button>
							</div>
						</div>
					</div>

					<!-- Cart items list -->
					<div class="product-details-box-list">
						<div v-for="item in cartItems" :key="item.listing_id" class="product-details-box gap-2">
							<div class="product-img" :style="{ backgroundImage: 'url(' + (item.item?.image_url || '/images/skin_no_image.svg') + ')' }">
							</div>
							<div
								class="description d-flex align-items-center justify-content-between flex-grow-1 gap-3">
								<div>
									<div class="d-flex align-items-center gap-2">
										<h6 class="product-name">{{ item.item?.name }}</h6>
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
									<div class="product-box-price text-center mb-3">
										<span class="fw-bold fs-5">{{ formatPrice(item.price, 'RUB') }}</span>
									</div>
									<div class="btn-group">
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
					<a href="/marketplace" class="btn theme-btn">
						<i class="ri-store-2-line me-2"></i>Перейти в маркетплейс
					</a>
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
								<small class="text-muted">{{ formatPrice(itemToRemove.price, 'RUB') }}</small>
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
import { formatPrice, handleApiError } from '../utils/helpers';
import { cartAPI } from '../utils/api';

export default {
	name: 'Cart',
	setup() {
		return { formatPrice };
	},
	data() {
		return {
			cartItems: [],
			cartTotal: 0,
			cartCount: 0,
			isLoading: false,
			itemToRemove: null
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

					// Показываем предупреждения если были удалены недоступные товары
					if (data.warnings && data.warnings.removed_items.length > 0) {
						window.toast.warning(data.warnings.message);
					}
				} else {
					window.toast.error(data.message || 'Не удалось загрузить корзину');
				}
			} catch (error) {
				console.error('Error loading cart:', error);
				window.toast.error(handleApiError(error));
			} finally {
				this.isLoading = false;
			}
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
					window.toast.error(data.message || 'Не удалось удалить товар');
				}
			} catch (error) {
				console.error('Remove from cart error:', error);
				window.toast.error(handleApiError(error));
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
					window.toast.error(data.message || 'Не удалось очистить корзину');
				}
			} catch (error) {
				console.error('Clear cart error:', error);
				window.toast.error(handleApiError(error))
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