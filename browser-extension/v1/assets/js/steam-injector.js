/**
 * CS-SKINS.pro Trading Assistant - Steam Content Script
 * Обрабатывает создание и отмену трейд-офферов через централизованный SteamAPI
 */
class SteamTradeInjector {
    constructor() {
        this.isInitialized = false;
        this.sessionId = null;
        this.steamId = null;
        this.init();
    }
    
    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }
    
    setup() {
        if (!this.isSteamSite()) return;
        
        chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
            this.handleMessage(message, sender, sendResponse);
            return true; // Указываем что будет асинхронный ответ
        });
        
        this.loadSteamSession();
        this.isInitialized = true;
    }
    
    isSteamSite() {
        return window.location.hostname === 'steamcommunity.com';
    }
    
    /**
     * Обработчик сообщений от service-worker
     */
    async handleMessage(message, sender, sendResponse) {
        if (message.type !== 'STEAM_API_REQUEST') {
            sendResponse({ success: false, error: 'Unknown message type' });
            return false;
        }
        
        try {
            const config = message.config;
            
            // Добавляем session ID если нужно
            if (config.data && config.data.sessionid === '') {
                if (!this.sessionId) {
                    throw new Error('Не удалось получить Steam session');
                }
                config.data.sessionid = this.sessionId;
            }
            
            // Выполняем AJAX запрос
            const result = await this.executeAjaxRequest(config);
            sendResponse({ success: true, ...result });
            
        } catch (error) {
            sendResponse({ 
                success: false, 
                error: error.message,
                httpStatus: error.httpStatus,
                rawResponse: error.rawResponse
            });
        }
        
        return true;
    }
    
    /**
     * Загрузка Steam сессии при инициализации
     */
    loadSteamSession() {
        // Извлечение Session ID
        if (window.g_sessionID) {
            this.sessionId = window.g_sessionID;
        } else {
            const sessionCookie = document.cookie
                .split(';')
                .find(cookie => cookie.trim().startsWith('sessionid='));
            this.sessionId = sessionCookie ? sessionCookie.split('=')[1] : null;
        }
        
        // Извлечение Steam ID
        if (window.g_steamID) {
            this.steamId = window.g_steamID;
        } else {
            const profileMatch = window.location.href.match(/steamcommunity\.com\/(profiles|id)\/([^\/]+)/);
            this.steamId = profileMatch ? profileMatch[2] : null;
        }
        
        // Отправляем данные в service-worker
        if (this.sessionId) {
            chrome.runtime.sendMessage({
                type: 'STEAM_SESSION_EXTRACTED',
                sessionData: { sessionId: this.sessionId, steamId: this.steamId }
            });
        }
    }
    
    /**
     * Универсальный метод для выполнения AJAX запросов к Steam API
     */
    async executeAjaxRequest(config) {
        const { url, method = 'POST', data = {}, successValidator, operation } = config;
        
        try {
            let requestConfig = {
                url: url,
                method: method,
                validateStatus: () => true // Не бросать ошибку на HTTP статусы
            };
            
            // Для GET запросов данные идут в params (URL), для POST - в data
            if (method.toUpperCase() === 'GET') {
                requestConfig.params = data;
                requestConfig.headers = {
                    'Accept': 'application/json'
                };
            } else {
                requestConfig.data = data;
                requestConfig.headers = {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                };
            }
            
            const response = await axios(requestConfig);
            
            // Для операций получения статуса трейда - проверяем по-другому
            if (operation === 'getTradeOfferStatus') {
                if (response.status === 200 && response.data?.response) {
                    return {
                        success: true,
                        result: response.data
                    };
                }
            }
            
            // Для обычных трейд операций - только HTTP 200 с tradeofferid считается успехом
            if (response.status === 200 && response.data?.tradeofferid) {
                return response.data;
            }
            
            // Все остальные ответы - неуспешные, но не ошибки расширения
            console.log(`[Steam API] Non-success response:`, {
                status: response.status,
                data: response.data,
                operation: operation
            });
            
            // Возвращаем структуру для обработки как неуспешный результат
            return {
                success: false,
                rawResponse: response
            };
            
        } catch (error) {
            // Обработка ошибок axios
            if (error.response) {
                const apiError = new Error(error.message || 'Request failed');
                apiError.httpStatus = error.response.status;
                apiError.rawResponse = error.response.data;
                throw apiError;
            } else {
                // Ошибка валидации или сети
                throw error;
            }
        }
    }
}

// Инициализируем content script
const steamInjector = new SteamTradeInjector();

// Для отладки в консоли разработчика
window.steamInjector = steamInjector;