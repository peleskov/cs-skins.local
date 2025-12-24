/*=====================
    loader js
   ==========================*/

document.addEventListener('DOMContentLoaded', function() {
    const skeletonLoader = document.querySelector('.skeleton-loader');
    if (skeletonLoader) {
        // Задержка 4 секунды (4000мс) после загрузки страницы
        setTimeout(() => {
            // Добавляем класс для анимации
            skeletonLoader.classList.add('hiding');
            
            // Скрываем элемент после завершения анимации (800мс)
            setTimeout(() => {
                skeletonLoader.style.display = 'none';
            }, 800);
        }, 500);
    }
});
