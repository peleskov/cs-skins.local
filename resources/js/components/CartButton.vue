<template>
	<button 
		:class="buttonClass" 
		@click="toggleCart" 
		:disabled="isLoading"
		:title="buttonTitle">
		<i :class="iconClass" class="me-1"></i>
		{{ buttonText }}
	</button>
</template>

<script>
import { useToast } from "vue-toastification";
import { cartAPI } from '../utils/api';

export default {
	name: 'CartButton',
	setup() {
		const toast = useToast();
		return { toast };
	},
	props: {
		listingId: {
			type: Number,
			required: true
		},
		size: {
			type: String,
			default: 'normal', // normal, small, large
			validator: value => ['small', 'normal', 'large'].includes(value)
		},
		variant: {
			type: String,
			default: 'primary', // primary, outline
			validator: value => ['primary', 'outline'].includes(value)
		}
	},
	data() {
		return {
			isInCart: false,
			isLoading: false
		}
	},
	computed: {
		buttonClass() {
			let baseClass = 'btn';
			
			// Размер кнопки
			if (this.size === 'small') {
				baseClass += ' btn-sm';
			} else if (this.size === 'large') {
				baseClass += ' btn-lg';
			}
			
			// Стиль кнопки
			if (this.isInCart) {
				baseClass += ' theme-btn theme-btn-success';
			} else {
				if (this.variant === 'outline') {
					baseClass += ' theme-outline';
				} else {
					baseClass += ' theme-btn';
				}
			}
			
			return baseClass;
		},
		iconClass() {
			if (this.isLoading) {
				return 'ri-loader-2-line animate-spin';
			}
			return this.isInCart ? 'ri-check-line' : 'ri-shopping-cart-line';
		},
		buttonText() {
			if (this.isLoading) {
				return this.isInCart ? 'Удаляем...' : 'Добавляем...';
			}
			return this.isInCart ? 'В корзине' : 'В корзину';
		},
		buttonTitle() {
			return this.isInCart ? 'Удалить из корзины' : 'Добавить в корзину';
		}
	},
	methods: {
		async toggleCart() {
			if (this.isLoading) return;

			if (this.isInCart) {
				await this.removeFromCart();
			} else {
				await this.addToCart();
			}
		},

		async addToCart() {
			this.isLoading = true;
			try {
				const data = await cartAPI.addItem(this.listingId);

				if (data.success) {
					this.isInCart = true;
					this.toast.success(data.message || 'Товар добавлен в корзину');
					
					// Обновляем счетчик в header
					this.updateCartCount(data.cart_count);
					
					// Эмитим событие для родительского компонента
					this.$emit('added-to-cart', {
						listingId: this.listingId,
						cartCount: data.cart_count
					});
				} else {
					this.toast.error(data.message || 'Не удалось добавить товар в корзину');
				}
			} catch (error) {
				console.error('Add to cart error:', error);
				this.toast.error('Произошла ошибка при добавлении товара в корзину');
			} finally {
				this.isLoading = false;
			}
		},

		async removeFromCart() {
			this.isLoading = true;
			try {
				const data = await cartAPI.removeItem(this.listingId);

				if (data.success) {
					this.isInCart = false;
					this.toast.success(data.message || 'Товар удален из корзины');
					
					// Обновляем счетчик в header
					this.updateCartCount(data.cart_count);
					
					// Эмитим событие для родительского компонента
					this.$emit('removed-from-cart', {
						listingId: this.listingId,
						cartCount: data.cart_count
					});
				} else {
					this.toast.error(data.message || 'Не удалось удалить товар из корзины');
				}
			} catch (error) {
				console.error('Remove from cart error:', error);
				this.toast.error('Произошла ошибка при удалении товара из корзины');
			} finally {
				this.isLoading = false;
			}
		},

		async checkCartStatus() {
			try {
				const data = await cartAPI.checkItem(this.listingId);

				if (data.success) {
					this.isInCart = data.in_cart;
				}
			} catch (error) {
				console.error('Check cart status error:', error);
			}
		},

		updateCartCount(count) {
			// Эмитим глобальное событие для Header Vue компонента
			window.dispatchEvent(new CustomEvent('cart-updated', {
				detail: { count, timestamp: Date.now() }
			}));
			
			console.log('Cart count updated:', count);
		}
	},

	mounted() {
		// Проверяем статус товара в корзине при загрузке компонента
		this.checkCartStatus();
	}
}
</script>

<style scoped>
.animate-spin {
	animation: spin 1s linear infinite;
}

@keyframes spin {
	from {
		transform: rotate(0deg);
	}
	to {
		transform: rotate(360deg);
	}
}
</style>