// Ждем когда Swiper будет доступен глобально
window.addEventListener('load', function () {
	if (typeof Swiper === 'undefined') {
		console.error('Swiper не загружен');
		return;
	}

});
