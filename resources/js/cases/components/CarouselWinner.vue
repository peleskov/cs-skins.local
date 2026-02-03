<template>
	<section class="carousel-winner pt-2 mb-60">
		<div class="container-fluid">
			<div class="swiper" ref="swiperContainer">
				<div class="swiper-wrapper">
					<div v-for="drop in drops" :key="drop.id" class="swiper-slide">
						<div class="item d-flex align-items-center justify-content-center" :class="getRarityClass(drop.item.rarity)">
							<div class="image" :style="{ backgroundImage: `url(${getItemImageUrl(drop.item.image_url)})` }"></div>
							<div class="user d-flex flex-column align-items-start">
								<img :src="drop.user.avatar" :alt="drop.user.name">
								<p>{{ drop.user.name }}</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export default {
	name: 'CarouselWinner',

	data() {
		return {
			drops: [],
			swiper: null,
			echo: null,
			maxDrops: 30,
		};
	},

	mounted() {
		this.loadInitialDrops();
		this.initSwiper();
		this.initWebSocket();
	},

	beforeUnmount() {
		if (this.echo) {
			this.echo.leave('presence-chat');
		}
		if (this.swiper) {
			this.swiper.destroy();
		}
	},

	methods: {
		async loadInitialDrops() {
			try {
				const response = await fetch('/api/live-feed');
				const data = await response.json();
				if (data.success && data.drops) {
					this.drops = data.drops;
					this.$nextTick(() => {
						if (this.swiper) {
							this.swiper.update();
						}
					});
				}
			} catch (error) {
				console.error('Failed to load live feed:', error);
			}
		},

		initSwiper() {
			if (typeof Swiper === 'undefined') {
				console.error('Swiper не загружен');
				return;
			}

			this.$nextTick(() => {
				this.swiper = new Swiper(this.$refs.swiperContainer, {
					slidesPerView: 8,
					spaceBetween: 10,
					loop: true,
					autoplay: {
						delay: 3000,
						disableOnInteraction: false,
					},
					breakpoints: {
						0: { slidesPerView: 2, spaceBetween: 10 },
						375: { slidesPerView: 3 },
						576: { slidesPerView: 4, spaceBetween: 15 },
						767: { slidesPerView: 6 },
						991: { slidesPerView: 7 },
						1200: { slidesPerView: 8, spaceBetween: 20 },
						1800: { slidesPerView: 10, spaceBetween: 20 },
					},
				});
			});
		},

		initWebSocket() {
			// Используем тот же Echo что и чат
			this.echo = new Echo({
				broadcaster: 'reverb',
				key: import.meta.env.VITE_REVERB_APP_KEY,
				wsHost: import.meta.env.VITE_REVERB_HOST,
				wsPort: 80,
				wssPort: 443,
				forceTLS: true,
				enabledTransports: ['ws', 'wss'],
				wsPath: '/ws',
				disableStats: true,
			});

			this.echo.join('presence-chat')
				.listen('.case.drop', (drop) => {
					this.addDrop(drop);
				});
		},

		addDrop(drop) {
			// Добавляем в начало
			this.drops.unshift(drop);

			// Ограничиваем количество
			if (this.drops.length > this.maxDrops) {
				this.drops = this.drops.slice(0, this.maxDrops);
			}

			// Обновляем swiper
			this.$nextTick(() => {
				if (this.swiper) {
					this.swiper.update();
					// Прокручиваем к новому слайду
					this.swiper.slideTo(0, 500);
				}
			});
		},

		getItemImageUrl(imageUrl) {
			if (!imageUrl) return '';
			if (imageUrl.startsWith('http')) return imageUrl;
			return `https://community.steamstatic.com/economy/image/${imageUrl}/256x128`;
		},

		getRarityClass(rarity) {
			if (!rarity) return '';
			const rarityMap = {
				'Consumer Grade': 'rarity-consumer',
				'Industrial Grade': 'rarity-industrial',
				'Mil-Spec Grade': 'rarity-milspec',
				'Mil-Spec': 'rarity-milspec',
				'Restricted': 'rarity-restricted',
				'Classified': 'rarity-classified',
				'Covert': 'rarity-covert',
				'Contraband': 'rarity-contraband',
			};
			return rarityMap[rarity] || '';
		},
	},
};
</script>
