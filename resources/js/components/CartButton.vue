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
import { cartAPI } from '../utils/api';
import { handleApiError } from '../utils/helpers';

export default {
	name: 'CartButton',
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
		},
		initialIsInCart: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			isInCart: this.initialIsInCart,
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
					window.toast.success(data.message || 'Товар добавлен в корзину');
					
					// Обновляем счетчик в header
					this.updateCartCount(data.cart_count);
					
					// Эмитим событие для родительского компонента
					this.$emit('added-to-cart', {
						listingId: this.listingId,
						cartCount: data.cart_count
					});
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Add to cart error:', error);
				// Глобальный обработчик покажет toast автоматически
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
					window.toast.success(data.message || 'Товар удален из корзины');
					
					// Обновляем счетчик в header
					this.updateCartCount(data.cart_count);
					
					// Эмитим событие для родительского компонента
					this.$emit('removed-from-cart', {
						listingId: this.listingId,
						cartCount: data.cart_count
					});
				} else {
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Remove from cart error:', error);
				// Глобальный обработчик покажет toast автоматически
			} finally {
				this.isLoading = false;
			}
		},

		updateCartCount(count) {
			// Эмитим глобальное событие для Header Vue компонента
			window.dispatchEvent(new CustomEvent('cart-updated', {
				detail: { count, timestamp: Date.now() }
			}));
			
		}
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