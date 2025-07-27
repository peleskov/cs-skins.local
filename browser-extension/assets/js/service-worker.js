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
    
    async getPendingOrders() {
        try {
            const response = await this.makeRequest('/orders/pending');
            return response.data || [];
        } catch (error) {
            throw error;
        }
    }
    
    async updateTradeStatus(orderId, status, data = {}) {
        try {
            const response = await this.makeRequest(`/orders/${orderId}/trade-status`, {
                method: 'POST',
                body: JSON.stringify({ status, ...data })
            });
            return response;
        } catch (error) {
            throw error;
        }
    }
    
    async logError(errorType, errorMessage, context = {}) {
        try {
            const response = await this.makeRequest('/log-error', {
                method: 'POST',
                body: JSON.stringify({
                    type: errorType,
                    message: errorMessage,
                    context: context,
                    timestamp: new Date().toISOString()
                })
            });
            return response;
        } catch (error) {
            // Не выбрасываем ошибку, чтобы не прерывать основной процесс
            console.error('Failed to log error to server:', error);
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
            console.log('[STEAM CHECK] Status:', steamStatus);
            
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
    
    async checkSteamStatusDirect() {
        try {
            // Получаем все открытые вкладки для диагностики
            const allTabs = await chrome.tabs.query({});
            console.log('[STEAM CHECK] All open tabs:', allTabs.map(tab => ({ id: tab.id, url: tab.url, title: tab.title })));
            
            // Проверяем наличие активных вкладок Steam
            const steamTabs = await chrome.tabs.query({
                url: ["*://steamcommunity.com/*"]
            });
            
            console.log('[STEAM CHECK] Steam tabs found:', steamTabs.length);
            
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
            
            try {
                // Инжектируем скрипт для проверки сессии напрямую
                const [result] = await chrome.scripting.executeScript({
                    target: { tabId: steamTab.id },
                    world: 'MAIN',
                    func: (expectedId) => {
                        // Проверяем наличие g_steamID
                        if (typeof g_steamID !== 'undefined' && g_steamID) {
                            const isCorrectUser = !expectedId || g_steamID === expectedId;
                            
                            console.log('[STEAM CHECK] Found steamID:', g_steamID);
                            console.log('[STEAM CHECK] Expected steamID:', expectedId);
                            console.log('[STEAM CHECK] Is correct user:', isCorrectUser);
                            
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
                        
                        // Проверяем sessionid
                        if (typeof g_sessionID !== 'undefined' && g_sessionID) {
                            // Есть сессия, но нет steamID - возможно на странице логина
                            return {
                                available: false,
                                state: 'unauthorized',
                                reason: 'session_exists_but_not_logged_in'
                            };
                        }
                        
                        // Не авторизован
                        return {
                            available: false,
                            state: 'unauthorized',
                            reason: 'not_logged_in'
                        };
                    },
                    args: [expectedSteamId]
                });
                
                if (result && result.result) {
                    // Сохраняем текущий Steam ID
                    if (result.result.steamId) {
                        this.currentSteamId = result.result.steamId;
                    }
                    
                    return result.result;
                }
                
                return {
                    available: false,
                    state: 'error',
                    reason: 'no_result_from_script'
                };
                
            } catch (error) {
                console.error('[STEAM CHECK] Script injection error:', error);
                
                // Если не удалось инжектировать скрипт, возможно вкладка еще загружается
                return {
                    available: false,
                    state: 'loading',
                    reason: 'script_injection_failed'
                };
            }
            
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
                this.storage.addLogEntry('success', 'Расширение активировано');
                
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
                    let eventData = data;
                    if (data.data && typeof data.data === 'string') {
                        try {
                            const parsedData = JSON.parse(data.data);
                            eventData = parsedData;  // Используем только распарсенные данные
                            //console.log('Parsed event data:', eventData);
                        } catch (e) {
                            //console.log('Failed to parse data.data as JSON, using as is');
                            eventData = data;
                        }
                    }
                    
                    // Обрабатываем события трейдов
                    await this.handleTradeEvent(data.event, eventData);
                    
                } catch (error) {
                    // Ignore WebSocket message errors
                }
            };
            
            // Обработчик ошибок WebSocket
            this.wsConnection.onerror = (event) => {
                clearTimeout(connectionTimeout);
                this.storage.addLogEntry('error', 'Соединение прервано');
            };
            
            // Обработчик закрытия WebSocket
            this.wsConnection.onclose = (event) => {
                clearTimeout(connectionTimeout);
                
                if (event.code !== 1000) {
                    this.storage.addLogEntry('error', 'Соединение прервано');
                    
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
    
    async handleTradeEvent(eventType, eventData) {
        //console.log('WebSocket event received:', eventType, eventData);
        
        // Универсальная обработка статистики для всех сообщений
        if (eventData.stats) {
            // Сохраняем статистику в storage для восстановления после рефреша
            await this.storage.setData({ lastStats: eventData.stats });
            
            chrome.runtime.sendMessage({
                type: 'STATS_RECEIVED',
                stats: eventData.stats
            }).catch(() => {});
        }
        
        // Универсальное добавление лога для всех сообщений
        if (eventData.log_message) {
            await this.storage.addLogEntry('info', eventData.log_message);
        }
        
        // Специфичная обработка только для определенных событий
        if (eventType === 'force_logout') {
            const message = eventData.message || 'Токен изменен. Требуется переавторизация.';
            
            await this.storage.addLogEntry('warning', message);
            await this.logout();
            
            chrome.runtime.sendMessage({
                type: 'FORCE_LOGOUT',
                message: message
            }).catch(() => {});
            
            await this.showNotification('Требуется переавторизация', message);
            
        } else if (eventType === 'trade_reserved') {
            // Автоматическое создание трейд-оффера
            await this.processTrade(eventData);
        }
        
        // Для события 'stats' ничего дополнительного не делаем - статистика уже обновлена выше
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
            
            
            // Client events должны отправляться на канал
            this.wsConnection.send(JSON.stringify({
                event: 'client-stats-request',
                data: {},
                channel: channel
            }));
            
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
    
    
    async processTrade(eventData) {
        try {
            // Проверяем что есть необходимые данные для создания трейда
            if (!eventData.order) {
                await this.storage.addLogEntry('error', 'Получены неполные данные для трейда');
                return;
            }
            
            const order = eventData.order;
            const skinName = order.skin_name || 'Неизвестный предмет';
            
            // Проверяем обязательные поля
            if (!order.steam_asset_id || !order.buyer?.steam_id || !order.buyer?.trade_url) {
                await this.storage.addLogEntry('error', `Неполные данные для трейда ${skinName}`);
                return;
            }
            
            // Проверяем статус Steam
            const steamStatus = await this.checkSteamStatusDirect();
            if (!steamStatus.available) {
                await this.storage.addLogEntry('warning', `Трейд ${skinName} отложен: Steam недоступен`);
                await this.showNotification('Трейд отложен', `${skinName} - Steam недоступен для создания трейда`);
                return;
            }
            
            await this.storage.addLogEntry('info', `Создание трейда: ${skinName}`);
            
            // Логируем данные трейда для отладки
            console.log('Creating trade offer:', {
                skin_name: skinName,
                steam_asset_id: order.steam_asset_id,
                buyer_steam_id: order.buyer?.steam_id,
                trade_url: order.buyer?.trade_url
            });
            
            // Создаём трейд используя рабочую логику
            const tradeResult = await this.createTradeOffer(order);
            
            if (tradeResult.success) {
                await this.storage.addLogEntry('success', `Трейд ${skinName} создан #${tradeResult.tradeofferid}`);
                await this.showNotification('Трейд создан', `${skinName} отправлен покупателю`);
                
                // TODO: Отправить tradeofferid на сервер для мониторинга
                
            } else {
                await this.storage.addLogEntry('error', `Ошибка трейда ${skinName}: ${tradeResult.error}`);
                
                // Отправляем ошибку на сервер
                await this.platformAPI.logError('trade_creation_failed', tradeResult.error, {
                    skin_name: skinName,
                    steam_asset_id: order.steam_asset_id,
                    buyer_steam_id: order.buyer?.steam_id,
                    buyer_trade_url: order.buyer?.trade_url,
                    seller_steam_id: this.currentSteamId || 'unknown'
                });
                
                await this.showNotification('Ошибка трейда', skinName);
            }
            
        } catch (error) {
            await this.storage.addLogEntry('error', `Ошибка создания трейда: ${skinName}`);
            
            // Отправляем критическую ошибку на сервер
            await this.platformAPI.logError('trade_critical_error', error.message, {
                skin_name: skinName,
                steam_asset_id: order.steam_asset_id,
                buyer_steam_id: order.buyer?.steam_id,
                error_stack: error.stack
            });
            
            await this.showNotification('Ошибка трейда', skinName);
        }
    }
    
    async createTradeOffer(order) {
        try {
            const tradeUrl = order.buyer.trade_url;
            
            // Находим существующую Steam вкладку
            const steamTabs = await chrome.tabs.query({ url: 'https://steamcommunity.com/*' });
            
            if (!steamTabs.length) {
                return { success: false, error: 'Нет открытых вкладок Steam' };
            }
            
            const steamTab = steamTabs[0];
            
            // Переходим на страницу создания трейда в существующей вкладке
            await chrome.tabs.update(steamTab.id, { 
                url: tradeUrl,
                active: true 
            });
            
            // Ждем загрузки страницы
            await new Promise(resolve => {
                chrome.tabs.onUpdated.addListener(function listener(tabId, info) {
                    if (tabId === steamTab.id && info.status === 'complete') {
                        chrome.tabs.onUpdated.removeListener(listener);
                        resolve();
                    }
                });
            });
            
            // Небольшая задержка для полной инициализации страницы
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // Создаем предложение обмена
            const [result] = await chrome.scripting.executeScript({
                target: { tabId: steamTab.id },
                world: 'MAIN',
                func: (tradeUrl, assetId) => {
                    // Парсим trade URL для получения параметров
                    const urlParams = new URL(tradeUrl);
                    const partner = urlParams.searchParams.get('partner');
                    const token = urlParams.searchParams.get('token');
                    
                    if (!partner || !token) {
                        return { success: false, error: 'Неверный формат trade URL' };
                    }
                    
                    // Конвертируем partner в steamid64
                    const steamId64 = (BigInt(partner) + BigInt('76561197960265728')).toString();
                    
                    // Получаем session ID
                    let sessionId = '';
                    if (typeof g_sessionID !== 'undefined') {
                        sessionId = g_sessionID;
                    } else {
                        return { success: false, error: 'Не удалось получить session ID' };
                    }
                    
                    // Создаем предложение обмена
                    const tradeOffer = {
                        sessionid: sessionId,
                        serverid: 1,
                        partner: steamId64,
                        tradeoffermessage: '',
                        json_tradeoffer: JSON.stringify({
                            newversion: true,
                            version: 2,
                            me: {
                                assets: [
                                    {
                                        appid: "730",
                                        contextid: "2",
                                        amount: 1,
                                        assetid: assetId
                                    }
                                ],
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
                            trade_offer_access_token: token
                        })
                    };
                    
                    // Отправляем запрос на создание предложения
                    return fetch('https://steamcommunity.com/tradeoffer/new/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Accept': '*/*',
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Referer': tradeUrl
                        },
                        body: new URLSearchParams(tradeOffer).toString(),
                        credentials: 'include'
                    })
                    .then(response => {
                        if (response.status === 200) {
                            return response.json().then(result => {
                                if (result && result.tradeofferid) {
                                    return {
                                        success: true,
                                        tradeofferid: result.tradeofferid,
                                        needs_mobile_confirmation: result.needs_mobile_confirmation
                                    };
                                } else {
                                    return { success: false, error: result.strError || 'Неизвестная ошибка Steam' };
                                }
                            });
                        } else {
                            return { success: false, error: `HTTP ${response.status}` };
                        }
                    })
                    .catch(error => {
                        return { success: false, error: error.message };
                    });
                },
                args: [tradeUrl, order.steam_asset_id]
            });
            
            if (result && result.result) {
                return result.result;
            }
            
            return { success: false, error: 'Не удалось выполнить скрипт создания трейда' };
            
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
    
    async createTradeWithTab(order) {
        try {
            // Открываем trade URL в новой вкладке
            const tab = await chrome.tabs.create({
                url: order.buyer.trade_url,
                active: false
            });
            
            // Ждём загрузки страницы
            await new Promise(resolve => {
                const listener = (tabId, changeInfo) => {
                    if (tabId === tab.id && changeInfo.status === 'complete') {
                        chrome.tabs.onUpdated.removeListener(listener);
                        setTimeout(resolve, 2000); // Даём время на инициализацию
                    }
                };
                chrome.tabs.onUpdated.addListener(listener);
            });
            
            // Выполняем создание трейда на этой странице
            const result = await chrome.tabs.sendMessage(tab.id, {
                type: 'CREATE_TRADE_ON_PAGE',
                order: order
            });
            
            // Закрываем вкладку
            await chrome.tabs.remove(tab.id);
            
            return result;
            
        } catch (error) {
            throw error;
        }
    }
    
    async showNotification(title, message) {
        await chrome.notifications.create({
            type: 'basic',
            iconUrl: 'assets/icons/icon-48.png',
            title: title,
            message: message
        });
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

// Создаем экземпляр Trading Assistant
const tradingAssistant = new TradingAssistant();

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

