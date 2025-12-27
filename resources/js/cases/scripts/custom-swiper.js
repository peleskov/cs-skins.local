// Ждем когда Swiper будет доступен глобально
window.addEventListener('load', function () {
	if (typeof Swiper === 'undefined') {
		console.error('Swiper не загружен');
		return;
	}

	// Carousel winner
	const carouselWinner = document.querySelector('.carousel-winner .swiper');
	if (carouselWinner) {
		new Swiper(carouselWinner, {
			slidesPerView: 8,
			spaceBetween: 10,
			loop: true,
			autoplay: {
				delay: 20000,
				disableOnInteraction: false,
			},

			breakpoints: {
				0: {
					slidesPerView: 2,
					spaceBetween: 10,
				},
				375: {
					slidesPerView: 3,
				},
				576: {
					slidesPerView: 4,
					spaceBetween: 15,
				},
				767: {
					slidesPerView: 6,
				},
				991: {
					slidesPerView: 7,
				},
				1200: {
					slidesPerView: 8,
					spaceBetween: 20,
				},
				1800: {
					slidesPerView: 10,
					spaceBetween: 20,
				},
			},
		});
	}

});
