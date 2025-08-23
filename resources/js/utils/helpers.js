/**
 * Общие вспомогательные функции
 */

/**
 * Форматирование цены с разделителями тысяч и конвертацией валюты
 * @param {number|string} price - Цена для форматирования
 * @param {string} sourceCurrency - Валюта исходной цены (по умолчанию RUB)
 * @param {boolean} returnNumber - Если true, возвращает число вместо форматированной строки
 * @returns {string|number} Отформатированная цена с символом валюты или число
 */
export function formatPrice(price, sourceCurrency = 'RUB', returnNumber = false) {
    const selectedCurrency = getSelectedCurrency();
    
    if (!selectedCurrency) {
        if (returnNumber) {
            return Number(price);
        }
        return Number(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    
    // Если исходная валюта совпадает с выбранной, возвращаем как есть
    if (selectedCurrency.code === sourceCurrency) {
        if (returnNumber) {
            return Number(price);
        }
        return Number(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ' + selectedCurrency.symbol;
    }
    
    // Конвертируем цену из исходной валюты в выбранную
    let convertedPrice = price;
    
    // Если исходная валюта RUB (рубли), а выбранная другая
    if (sourceCurrency === 'RUB' && selectedCurrency.code !== 'RUB') {
        // Конвертируем рубли в выбранную валюту
        // exchange_rate показывает сколько рублей за единицу валюты
        convertedPrice = price / selectedCurrency.exchange_rate;
    } 
    // Если исходная валюта не RUB, а выбранная RUB  
    else if (sourceCurrency !== 'RUB' && selectedCurrency.code === 'RUB') {
        // Находим курс исходной валюты к рублю
        const sourceCurrencyData = getCurrencyByCode(sourceCurrency);
        if (sourceCurrencyData) {
            convertedPrice = price * sourceCurrencyData.exchange_rate;
        } else {
            console.warn(`Currency ${sourceCurrency} not found in cache for conversion to RUB`);
        }
    }
    // Если обе валюты не RUB, конвертируем через рубли
    else if (sourceCurrency !== 'RUB' && selectedCurrency.code !== 'RUB') {
        const sourceCurrencyData = getCurrencyByCode(sourceCurrency);
        if (sourceCurrencyData) {
            // Сначала в рубли, затем в целевую валюту
            const rubleAmount = price * sourceCurrencyData.exchange_rate;
            convertedPrice = rubleAmount / selectedCurrency.exchange_rate;
        } else {
            console.warn(`Currency ${sourceCurrency} not found in cache for conversion`);
        }
    }
    
    if (returnNumber) {
        return Number(convertedPrice);
    }
    
    return Number(convertedPrice).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ' + selectedCurrency.symbol;
}

/**
 * Получение выбранной валюты из localStorage
 * @returns {Object|null} Объект валюты или null
 */
function getSelectedCurrency() {
    try {
        const saved = localStorage.getItem('selectedCurrency');
        return saved ? JSON.parse(saved) : null;
    } catch (error) {
        console.error('Error getting selected currency:', error);
        return null;
    }
}

/**
 * Получение данных валюты по коду из кэшированных курсов (синхронно)
 * @param {string} currencyCode - Код валюты
 * @returns {Object|null} Объект валюты или null
 */
function getCurrencyByCode(currencyCode) {
    // Сначала проверяем глобальный кэш (наиболее актуальный)
    if (window.currencyRatesCache && Array.isArray(window.currencyRatesCache)) {
        const found = window.currencyRatesCache.find(c => c.code === currencyCode);
        if (found) {
            return found;
        }
    }
    
    // Проверяем локальный кэш
    if (currencyRatesCache && Array.isArray(currencyRatesCache)) {
        const found = currencyRatesCache.find(c => c.code === currencyCode);
        if (found) {
            return found;
        }
    }
    
    // Логируем доступные валюты для отладки
    const availableCurrencies = window.currencyRatesCache || currencyRatesCache;
    if (availableCurrencies && Array.isArray(availableCurrencies)) {
        console.warn(`Currency ${currencyCode} not found in cache. Available currencies:`, availableCurrencies.map(c => c.code));
    } else {
        console.warn('No currency cache available');
    }
    
    return null;
}

// Кэш курсов валют (глобальный)
let currencyRatesCache = null;
let cacheExpiry = null;

// Делаем кэш доступным глобально для обновления из CurrencySelector
window.currencyRatesCache = currencyRatesCache;
const CACHE_DURATION = 5 * 60 * 1000; // 5 минут

/**
 * Получение курсов валют с кэшированием
 * @returns {Promise<Array>} Массив валют с курсами
 */
async function getCurrencyRates() {
    const now = Date.now();
    
    // Проверяем кэш
    if (currencyRatesCache && cacheExpiry && now < cacheExpiry) {
        return currencyRatesCache;
    }
    
    try {
        const response = await fetch('/api/currencies');
        if (!response.ok) {
            throw new Error('Failed to fetch currency rates');
        }
        
        const currencies = await response.json();
        
        // Кэшируем результат
        currencyRatesCache = currencies;
        window.currencyRatesCache = currencies; // Обновляем глобальный кэш
        cacheExpiry = now + CACHE_DURATION;
        
        return currencies;
    } catch (error) {
        console.error('Error fetching currency rates:', error);
        // Возвращаем кэш если есть, иначе пустой массив
        return currencyRatesCache || [];
    }
}

/**
 * Конвертация цены из основной валюты в целевую валюту
 * @param {number} price - Цена в основной валюте
 * @param {string} toCurrency - Целевая валюта
 * @returns {Promise<number>} Конвертированная цена
 */
async function convertPrice(price, toCurrency) {
    try {
        const currencies = await getCurrencyRates();
        
        const toCurrencyData = currencies.find(c => c.code === toCurrency);
        
        if (!toCurrencyData) {
            console.warn(`Currency not found: ${toCurrency}`);
            return price;
        }
        
        // Умножаем цену на курс целевой валюты
        return price * toCurrencyData.exchange_rate;
    } catch (error) {
        console.error('Error converting currency:', error);
        return price;
    }
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
 * Копирование текста в буфер обмена с отображением уведомления
 * @param {string} text - Текст для копирования
 * @param {string} successMessage - Сообщение при успешном копировании
 * @param {string} errorMessage - Сообщение при ошибке
 * @param {HTMLElement} iconElement - Элемент иконки для временного изменения (опционально)
 * @returns {Promise<boolean>} true если успешно скопировано
 */
export async function copyToClipboard(text, successMessage = 'Скопировано в буфер обмена', errorMessage = 'Не удалось скопировать', iconElement = null) {
    try {
        await navigator.clipboard.writeText(text);
        
        if (window.toast) {
            window.toast.success(successMessage);
        }
        
        // Временно меняем иконку если элемент передан
        if (iconElement) {
            const originalClass = iconElement.className;
            iconElement.className = 'ri-check-line text-success';
            setTimeout(() => {
                iconElement.className = originalClass;
            }, 2000);
        }
        
        return true;
    } catch (err) {
        console.error('Failed to copy:', err);
        
        if (window.toast) {
            window.toast.error(errorMessage);
        }
        
        return false;
    }
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
    } else if (seconds > 0) {
        return `${seconds}с`;
    } else {
        return 'Истек';
    }
}