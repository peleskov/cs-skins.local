// Дополнительная логика для отдельного окна
// Устанавливаем флаг что это отдельное окно
window.isDetachedWindow = true;

document.addEventListener('DOMContentLoaded', function() {
    // Обновляем заголовок окна в зависимости от состояния
    function updateWindowTitle() {
        const statusText = document.querySelector('.status-text')?.textContent || 'Не подключен';
        const baseTitle = 'CS-SKINS.pro Trading Assistant';
        document.title = `${baseTitle} - ${statusText}`;
    }
    
    // Обновляем заголовок при изменениях
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList?.contains('status-text')) {
                updateWindowTitle();
            }
        });
    });
    
    // Наблюдаем за изменениями статуса
    const statusIndicator = document.getElementById('statusIndicator');
    if (statusIndicator) {
        observer.observe(statusIndicator, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }
    
    // Первоначальное обновление заголовка
    setTimeout(updateWindowTitle, 1000);
});