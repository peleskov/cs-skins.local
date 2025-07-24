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
        
        this.init();
    }
    
    async init() {
        this.isAuthorized = await this.storage.isAuthorized();
        if (this.isAuthorized) {
            // Восстанавливаем последнюю статистику при запуске
            await this.restoreLastStats();
            await this.connectWebSocket();
        }
        
        await updateActivityIcon();
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
            // Пока не открываем Steam автоматически
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
        
        this.isActive = false;
        
        // Очищаем логи при отключении расширения
        await this.storage.setData({ logs: [] });
        
        if (detachedWindows.size === 0) {
            stopKeepAlive();
        }
        
        await updateActivityIcon();
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
            }
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

