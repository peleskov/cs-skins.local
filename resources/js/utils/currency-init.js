/**
 * Глобальная инициализация валют
 * Загружает основную валюту при старте приложения
 */

/**
 * Инициализация системы валют
 * Загружает валюты с сервера и устанавливает основную валюту если не выбрана
 */
export async function initializeCurrencies() {
    try {
        // Проверяем, есть ли уже валюта в localStorage
        const savedCurrency = localStorage.getItem('selectedCurrency');
        
        if (savedCurrency) {
            // Валюта уже выбрана, проверяем актуальность курса
            await updateCurrencyRates();
            return;
        }
        
        // Загружаем валюты с сервера
        const response = await fetch('/api/currencies');
        if (!response.ok) {
            return;
        }
        
        const currencies = await response.json();
        
        // Обновляем глобальный кэш
        if (window.currencyRatesCache !== currencies) {
            window.currencyRatesCache = currencies;
        }
        
        // Находим основную валюту
        const primaryCurrency = currencies.find(c => c.is_primary);
        if (primaryCurrency) {
            // Сохраняем основную валюту как выбранную по умолчанию
            localStorage.setItem('selectedCurrency', JSON.stringify(primaryCurrency));
        }
        
    } catch (error) {
        // Тихо игнорируем ошибки инициализации
    }
}

/**
 * Обновляет курсы валют в localStorage из свежих данных с сервера
 */
async function updateCurrencyRates() {
    try {
        const savedCurrency = localStorage.getItem('selectedCurrency');
        if (!savedCurrency) {
            return;
        }
        
        const currentCurrency = JSON.parse(savedCurrency);
        
        // Загружаем свежие данные
        const response = await fetch('/api/currencies');
        if (!response.ok) {
            return;
        }
        
        const currencies = await response.json();
        
        // Обновляем глобальный кэш
        window.currencyRatesCache = currencies;
        
        const updatedCurrency = currencies.find(c => c.code === currentCurrency.code);
        
        if (updatedCurrency) {
            // Обновляем сохраненную валюту актуальным курсом
            localStorage.setItem('selectedCurrency', JSON.stringify(updatedCurrency));
        }
        
    } catch (error) {
        // Тихо игнорируем ошибки обновления
    }
}

// Автоматическая инициализация при загрузке модуля
initializeCurrencies();