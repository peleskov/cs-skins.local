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
import { useToast } from "vue-toastification";
import { cartAPI } from '../utils/api';

export default {
	name: 'FavoriteButton',
	setup() {
		const toast = useToast();
		return { toast };
	},
	props: {
		listingId: {
			type: Number,
			required: true
		}
	},
	data() {
		return {
			isFavorite: false,
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
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						listing_id: this.listingId
					})
				});

				const data = await response.json();

				if (data.success) {
					this.isFavorite = data.is_favorite;
					this.toast.success(data.message);
					
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
					this.toast.error(data.message || 'Не удалось обновить избранное');
				}
			} catch (error) {
				console.error('Favorite toggle error:', error);
				
				// Проверяем, авторизован ли пользователь
				if (error.response && error.response.status === 401) {
					this.toast.error('Войдите в аккаунт для добавления в избранное');
				} else {
					this.toast.error('Произошла ошибка при обновлении избранного');
				}
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
					headers: {
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					}
				});

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
		// Проверяем статус избранного при загрузке компонента
		this.checkFavoriteStatus();
	}
}
</script>

<style scoped>
.like-btn.loading {
	opacity: 0.6;
	pointer-events: none;
}
</style>