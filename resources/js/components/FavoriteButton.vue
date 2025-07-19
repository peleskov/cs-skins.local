<template>
	<a href="#!" 
		class="like-btn" 
		@click="toggleFavorite" 
		:class="{ active: isFavorite, animate: isAnimating, loading: isLoading }"
		:title="buttonTitle">
		<i class="ri-heart-3-fill fill-icon"></i>
		<i class="ri-heart-3-line outline-icon"></i>
		<div class="effect-group">
			<span class="effect"></span>
			<span class="effect"></span>
			<span class="effect"></span>
			<span class="effect"></span>
			<span class="effect"></span>
		</div>
	</a>
</template>

<script>
import { cartAPI } from '../utils/api';
import { getApiHeaders, handleApiError } from '../utils/helpers';

export default {
	name: 'FavoriteButton',
	props: {
		listingId: {
			type: Number,
			required: true
		},
		initialIsFavorite: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			isFavorite: this.initialIsFavorite,
			isLoading: false,
			isAnimating: false
		}
	},
	computed: {
		buttonTitle() {
			if (this.isLoading) {
				return 'Обновляем...';
			}
			return this.isFavorite ? 'Удалить из избранного' : 'Добавить в избранное';
		}
	},
	methods: {
		async toggleFavorite() {
			if (this.isLoading) return;

			// Добавляем анимацию как в оригинальном скрипте
			this.isAnimating = true;
			
			this.isLoading = true;
			try {
				const response = await fetch('/api/favorites/toggle', {
					method: 'POST',
					headers: getApiHeaders(),
					body: JSON.stringify({
						listing_id: this.listingId
					})
				});

				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}

				const data = await response.json();

				if (data.success) {
					this.isFavorite = data.is_favorite;
					window.toast.success(data.message);
					
					// Эмитим событие для родительского компонента
					this.$emit('favorite-updated', {
						listingId: this.listingId,
						isFavorite: this.isFavorite
					});
					
					// Если товар был удален из избранного, отправляем глобальное событие
					if (!this.isFavorite) {
						window.dispatchEvent(new CustomEvent('favoriteRemoved', {
							detail: { listingId: this.listingId }
						}));
					}
				} else {
					window.toast.error(data.message || 'Не удалось обновить избранное');
				}
			} catch (error) {
				console.error('Favorite toggle error:', error);
				window.toast.error(handleApiError(error));
			} finally {
				this.isLoading = false;
				// Убираем анимацию через небольшую задержку
				setTimeout(() => {
					this.isAnimating = false;
				}, 600);
			}
		},

		async checkFavoriteStatus() {
			try {
				const response = await fetch(`/api/favorites/check/${this.listingId}`, {
					headers: getApiHeaders()
				});

				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}

				const data = await response.json();

				if (data.success) {
					this.isFavorite = data.is_favorite;
				}
			} catch (error) {
				console.error('Check favorite status error:', error);
			}
		}
	},

	mounted() {
		// Статус избранного теперь передается через initialIsFavorite prop
		// Проверка через API не нужна
	}
}
</script>

<style scoped>
.like-btn.loading {
	opacity: 0.6;
	pointer-events: none;
}
</style>