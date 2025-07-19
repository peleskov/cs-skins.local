/**
 * Общие вспомогательные функции
 */

/**
 * Форматирование цены с разделителями тысяч
 * @param {number|string} price - Цена для форматирования
 * @returns {string} Отформатированная цена
 */
export function formatPrice(price) {
    return Number(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

/**
 * Получение CSRF токена из мета-тега
 * @returns {string|null} CSRF токен
 */
export function getCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : null;
}

/**
 * Базовые заголовки для API запросов
 * @returns {Object} Объект с заголовками
 */
export function getApiHeaders() {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken()
    };
}

/**
 * Обработка ошибок API
 * @param {Error} error - Объект ошибки
 * @returns {string} Сообщение об ошибке для пользователя
 */
export function handleApiError(error) {
    // Если это уже обработанная ошибка с сообщением от сервера
    if (error.message && !error.message.includes('HTTP error!')) {
        return error.message;
    }
    
    if (error.response?.data?.message) {
        return error.response.data.message;
    }
    
    if (error.message === 'Network Error') {
        return 'Ошибка сети. Проверьте подключение к интернету.';
    }
    
    if (error.message?.includes('HTTP error!')) {
        return 'Ошибка сервера. Попробуйте позже.';
    }
    
    return error.message || 'Произошла неизвестная ошибка. Попробуйте позже.';
}