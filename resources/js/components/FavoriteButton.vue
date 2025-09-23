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
import axios from 'axios';
import { handleApiError } from '../utils/helpers';

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
				return this.translate('ui.updating');
			}
			return this.isFavorite ? this.translate('ui.remove_from_favorites') : this.translate('ui.add_to_favorites');
		}
	},
	methods: {
		translate(key) {
			const keys = key.split('.');
			let translation = window.translations;

			for (const k of keys) {
				if (translation && typeof translation === 'object' && translation[k]) {
					translation = translation[k];
				} else {
					return key;
				}
			}

			return translation || key;
		},
		async toggleFavorite() {
			if (this.isLoading) return;

			// Добавляем анимацию как в оригинальном скрипте
			this.isAnimating = true;
			
			this.isLoading = true;
			try {
				const response = await axios.post('/api/favorites/toggle', {
					listing_id: this.listingId
				});

				const data = response.data;

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
					// Глобальный обработчик покажет toast автоматически
				}
			} catch (error) {
				console.error('Favorite toggle error:', error);
				// Глобальный обработчик покажет toast автоматически
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
				const response = await axios.get(`/api/favorites/check/${this.listingId}`);
				const data = response.data;

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