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
 * Форматирование даты
 * @param {string|Date} date - Дата для форматирования
 * @returns {string} Отформатированная дата
 */
export function formatDate(date) {
    if (!date) return '';
    
    const dateObj = new Date(date);
    const now = new Date();
    
    // Сбрасываем время для корректного сравнения дат
    const dateStart = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());
    const nowStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    const diffInMs = nowStart - dateStart;
    const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
    
    if (diffInDays === 0) {
        return 'Сегодня, ' + dateObj.toLocaleTimeString('ru-RU', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    } else if (diffInDays === 1) {
        return 'Вчера, ' + dateObj.toLocaleTimeString('ru-RU', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    } else if (diffInDays < 7 && diffInDays > 0) {
        return diffInDays + ' дн. назад';
    } else {
        return dateObj.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

/**
 * Получение CSRF токена из мета-тега или cookie
 * @returns {string|null} CSRF токен
 */
export function getCsrfToken() {
    // Сначала пытаемся получить из мета-тега
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        const token = metaToken.getAttribute('content');
        return token;
    }
    
    // Если не найден, пытаемся получить из XSRF-TOKEN cookie
    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken) {
        const token = decodeURIComponent(xsrfToken);
        console.log('CSRF token from cookie:', token);
        return token;
    }
    
    console.log('No CSRF token found');
    return null;
}

/**
 * Получение cookie по имени
 * @param {string} name - Имя cookie
 * @returns {string|null} Значение cookie
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop().split(';').shift();
    }
    return null;
}

/**
 * Обработка ошибок API
 * @param {Error} error - Объект ошибки
 * @returns {string} Сообщение об ошибке для пользователя
 */
export function handleApiError(error) {
    // Сначала проверяем, есть ли сообщение в response.data
    if (error.response?.data?.message) {
        return error.response.data.message;
    }
    
    // Если это уже обработанная ошибка с сообщением от сервера
    if (error.message && !error.message.includes('HTTP error!')) {
        return error.message;
    }
    
    // Обработка HTTP статус кодов
    if (error.response?.status === 400) {
        return 'Неверный запрос.';
    }
    
    if (error.response?.status === 403) {
        return 'Недостаточно прав для выполнения действия.';
    }
    
    if (error.response?.status === 404) {
        return 'Запрашиваемый ресурс не найден.';
    }
    
    if (error.response?.status === 429) {
        return 'Слишком много запросов. Попробуйте через несколько секунд.';
    }
    
    if (error.response?.status === 500) {
        return 'Ошибка сервера. Попробуйте позже.';
    }
    
    if (error.message === 'Network Error') {
        return 'Ошибка сети. Проверьте подключение к интернету.';
    }
    
    if (error.message?.includes('HTTP error!')) {
        return 'Ошибка сервера. Попробуйте позже.';
    }
    
    return error.message || 'Произошла неизвестная ошибка. Попробуйте позже.';
}

/**
 * Получение оставшегося времени до указанной даты
 * @param {string|Date|number} endTime - Время окончания или количество секунд
 * @returns {string} Отформатированное время или статус
 */
export function getTimeRemaining(endTime) {
    let diff;
    
    // Если передано число секунд, используем его напрямую
    if (typeof endTime === 'number') {
        diff = endTime * 1000;
    } else {
        // Иначе вычисляем разность с текущим временем
        const now = new Date();
        const end = new Date(endTime);
        diff = end.getTime() - now.getTime();
    }

    if (diff <= 0) {
        return 'Истек';
    }

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    if (hours > 0) {
        return `${hours}ч ${minutes}м ${seconds}с`;
    } else if (minutes > 0) {
        return `${minutes}м ${seconds}с`;
    } else {
        return `${seconds}с`;
    }
}