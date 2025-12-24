<template>
	<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden bg-white">
		<div class="container">
			<div class="change-profile-content">
				<div class="title">
					<div class="loader-line"></div>
					<h3>Оформление заказа</h3>
				</div>

				<!-- Loading state -->
				<div v-if="isLoading" class="text-center py-5">
					<div class="loader-gif">
						<div class="radar-ring"></div>
						<img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
					</div>
					<p class="mt-3">Загружаем данные...</p>
				</div>

				<!-- Empty cart (не показываем если заказ успешно оформлен) -->
				<div v-else-if="cartItems.length === 0 && !showSuccessModal" class="text-center py-5">
					<i class="ri-shopping-cart-line display-4 text-muted mb-3"></i>
					<h4>Корзина пуста</h4>
					<p class="text-muted mb-4">Добавьте товары из маркетплейса для оформления заказа</p>
					<a href="/marketplace" class="btn theme-btn">
						<i class="ri-store-2-line me-2"></i>Перейти в маркетплейс
					</a>
				</div>

				<!-- Checkout content -->
				<div v-else class="product-box-section section-b-space">
					<!-- Order summary -->
					<div class="cart-summary mb-4 p-3 bg-light rounded">
						<div class="row align-items-center">
							<div class="col-md-8 mb-2 mb-md-0">
								<h5 class="mb-1">Заказ на сумму: <strong class="text-primary" v-html="formatPrice(cartTotal)"></strong></h5>
								<p class="text-muted mb-0">Товаров: {{ cartItems.length }}</p>
							</div>
							<div class="col-md-4 text-end">
								<button class="btn theme-btn" 
								        @click="placeOrder" 
								        :disabled="isProcessing">
									<i v-if="isProcessing" class="ri-loader-2-line me-1"></i>
									<i v-else class="ri-wallet-line me-1"></i>
									{{ isProcessing ? 'Обрабатываем...' : 'Оплатить с баланса' }}
								</button>
							</div>
						</div>
					</div>

					<!-- Order items -->
					<div class="product-details-box-list">
						<div v-for="item in cartItems" :key="item.listing_id" class="product-details-box gap-2">
							<div class="product-img" :style="{ backgroundImage: 'url(' + (item.item?.image_url || '/images/skin_no_image.svg') + ')' }">
							</div>
							<div class="description d-flex align-items-center justify-content-between flex-grow-1 gap-3">
								<div>
									<div class="d-flex align-items-center gap-2">
										<h6 class="product-name">{{ item.item.name }}</h6>
										<span v-if="item.is_stattrak" class="badge bg-warning">StatTrak™</span>
										<span v-if="item.is_souvenir" class="badge bg-info">Souvenir</span>
									</div>
									<div class="rating-section">
										<div class="d-flex align-items-center gap-2">
											<span v-if="item.wear_value" class="badge bg-secondary">
												{{ item.wear_name }}
											</span>
										</div>
									</div>
									<p class="text-muted mb-0">Продавец: {{ item.seller.name }}</p>
								</div>
								<div class="h-100 d-flex flex-column justify-content-center">
									<div class="product-box-price text-center">
										<span class="fw-bold fs-5 text-primary" v-html="formatPrice(item.price)"></span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Order notes -->
					<div class="mt-4">
						<div class="cart-summary p-3 bg-light rounded">
							<label for="orderNotes" class="form-label">Комментарий к заказу (необязательно)</label>
							<textarea 
								id="orderNotes" 
								v-model="orderNotes" 
								class="form-control" 
								rows="3" 
								placeholder="Дополнительные пожелания или комментарии к заказу">
							</textarea>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Success modal -->
	<div v-if="showSuccessModal" class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title">
						<i class="ri-check-line me-2"></i>Заказ успешно оформлен!
					</h5>
				</div>
				<div class="modal-body text-center">
					<i class="ri-check-double-line text-success" style="font-size: 3rem;"></i>
					<h4 class="mt-3">{{ createdOrders.length === 1 ? 'Заказ успешно оформлен!' : `Оформлено заказов: ${createdOrders.length}` }}</h4>
					
					<!-- Orders list -->
					<div class="mt-3">
						<div v-for="order in createdOrders" :key="order.id" class="border rounded p-2 mb-2 text-start">
							<div class="d-flex justify-content-between align-items-center">
								<span>
									<strong>Заказ {{ order.order_number }}</strong>
									<small class="text-muted d-block">Продавец: {{ order.seller?.name || 'Не указан' }}</small>
								</span>
								<strong class="text-primary" v-html="formatPrice(order.total_amount)"></strong>
							</div>
						</div>
						
						<!-- Total amount if multiple orders -->
						<div v-if="createdOrders.length > 1" class="mt-3 pt-3 border-top">
							<div class="d-flex justify-content-between align-items-center">
								<strong>Итого:</strong>
								<strong class="text-success fs-5" v-html="formatPrice(totalAmount)"></strong>
							</div>
						</div>
					</div>
					
					<p class="text-muted mt-3">
						{{ createdOrders.length === 1 ? 'Заказ' : 'Заказы' }} успешно оплачены и переданы в обработку. 
						Вы получите уведомление, когда трейд-оффер будет отправлен.
					</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn theme-outline" @click="goToProfile">
						Мой профиль
					</button>
					<button v-if="remainingCartCount > 0" type="button" class="btn theme-outline" @click="goToCart">
						<i class="ri-shopping-cart-line me-1"></i>В корзину ({{ remainingCartCount }})
					</button>
					<button type="button" class="btn theme-btn" @click="goToMarketplace">
						Продолжить покупки
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { formatPrice, handleApiError } from '../../shared/utils/helpers';
import { cartAPI, orderAPI } from '../../shared/utils/api';

export default {
	name: 'Checkout',
	data() {
		return {
			isLoading: true,
			isProcessing: false,
			cartItems: [],
			cartTotal: 0,
			orderNotes: '',
			showSuccessModal: false,
			createdOrders: [],
			selectedListingIds: [],
			remainingCartCount: 0
		}
	},
	async mounted() {
		// Получаем выбранные товары из sessionStorage
		const savedItems = sessionStorage.getItem('checkout_items');
		if (savedItems) {
			try {
				this.selectedListingIds = JSON.parse(savedItems);
			} catch (e) {
				this.selectedListingIds = [];
			}
		}
		await this.loadCartItems();
	},
	computed: {
		totalAmount() {
			return this.createdOrders.reduce((sum, order) => sum + parseFloat(order.total_amount), 0);
		}
	},
	methods: {
		formatPrice,

		async loadCartItems() {
			this.isLoading = true;
			try {
				const response = await cartAPI.getItems();
				if (response.success) {
					let items = response.data.items;

					// Фильтруем только выбранные товары если есть выбор
					if (this.selectedListingIds.length > 0) {
						items = items.filter(item => this.selectedListingIds.includes(item.listing_id));
					}

					this.cartItems = items;
					this.cartTotal = items.reduce((sum, item) => sum + parseFloat(item.price), 0);
				}
			} catch (error) {
				console.error('Error loading cart:', error);
				window.toast.error('Ошибка при загрузке корзины');
			} finally {
				this.isLoading = false;
			}
		},

		async placeOrder() {
			this.isProcessing = true;
			try {
				// Создаем и сразу оплачиваем заказ (списание с баланса)
				// Передаем только выбранные товары
				const listingIds = this.cartItems.map(item => item.listing_id);
				const orderResponse = await orderAPI.createOrder({ listing_ids: listingIds });
				if (!orderResponse.success) {
					throw new Error(orderResponse.message);
				}

				// Показываем модал успеха
				this.createdOrders = orderResponse.orders || [];
				this.remainingCartCount = orderResponse.cart_count ?? 0;
				this.showSuccessModal = true;

				// Очищаем sessionStorage
				sessionStorage.removeItem('checkout_items');

				// Обновляем счетчик корзины в header
				window.dispatchEvent(new CustomEvent('cart-updated', {
					detail: { count: this.remainingCartCount, timestamp: Date.now() }
				}));

			} catch (error) {
				console.error('Checkout error:', error);
				// Toast будет показан автоматически через глобальный interceptor axios
			} finally {
				this.isProcessing = false;
			}
		},

		goToProfile() {
			window.location.href = '/profile#orders';
		},

		goToCart() {
			window.location.href = '/cart';
		},

		goToMarketplace() {
			window.location.href = '/marketplace';
		}
	}
}
</script>