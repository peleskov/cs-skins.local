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
            console.log('📡 Запрос заказов с API...');
            const response = await this.makeRequest('/orders/pending');
            console.log('📥 Ответ API:', response);
            return response.data || [];
        } catch (error) {
            console.error('❌ Ошибка API при получении заказов:', error);
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
            // Ignore trade status update errors
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
        
        return await this.setData({ logs });
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
        
        this.init();
    }
    
    async init() {
        this.isAuthorized = await this.storage.isAuthorized();
        if (this.isAuthorized) {
            // Тестируем WebSocket подключение
            //console.log('🔗 Тестируем WebSocket подключение...');
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
                console.error('❌ Нет токена для WebSocket подключения');
                return;
            }
            
            // Подключаемся к Laravel Reverb WebSocket серверу через стандартный HTTPS порт
            const wsUrl = 'wss://cs-skins.s1temaker.ru/ws/app/cs-skins-key?protocol=7&client=js&version=8.0.1&flash=false';
            
            console.log('🔗 WebSocket подключение к:', wsUrl);
            
            // Создаем WebSocket соединение
            this.wsConnection = new WebSocket(wsUrl);
            
            // Таймаут для подключения WebSocket (30 секунд)
            const connectionTimeout = setTimeout(() => {
                if (this.wsConnection && this.wsConnection.readyState === WebSocket.CONNECTING) {
                    console.error('⏰ WebSocket подключение превысило таймаут (30 сек)');
                    this.wsConnection.close();
                    this.wsConnection = null;
                }
            }, 30000);
            
            // Обработчик подключения WebSocket
            this.wsConnection.onopen = (event) => {
                console.log('✅ WebSocket подключен:', event);
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
                    console.log('📨 WebSocket сообщение:', data);
                    
                    // Проверяем тип события
                    if (data.event === 'trade_reserved') {
                        const trade = data.data;
                        console.log('🔒 Трейд зарезервирован через WebSocket:', trade);
                        this.storage.addLogEntry('info', 'Новый трейд');
                        
                        // Уведомляем popup об обновлении статистики
                        chrome.runtime.sendMessage({
                            type: 'TRADE_STATUS_CHANGED',
                            event: 'trade_reserved'
                        }).catch(() => {});
                    } else if (data.event === 'trade_sent') {
                        const trade = data.data;
                        console.log('📤 Трейд отправлен через WebSocket:', trade);
                        this.storage.addLogEntry('success', 'Трейд отправлен в Steam');
                        
                        chrome.runtime.sendMessage({
                            type: 'TRADE_STATUS_CHANGED',
                            event: 'trade_sent'
                        }).catch(() => {});
                    } else if (data.event === 'trade_completed') {
                        const trade = data.data;
                        console.log('✅ Трейд завершен через WebSocket:', trade);
                        this.storage.addLogEntry('success', 'Трейд завершен');
                        
                        chrome.runtime.sendMessage({
                            type: 'TRADE_STATUS_CHANGED',
                            event: 'trade_completed'
                        }).catch(() => {});
                    } else if (data.event === 'trade_cancelled') {
                        const trade = data.data;
                        console.log('❌ Трейд отменен через WebSocket:', trade);
                        this.storage.addLogEntry('warning', 'Трейд отменен');
                        
                        chrome.runtime.sendMessage({
                            type: 'TRADE_STATUS_CHANGED',
                            event: 'trade_cancelled'
                        }).catch(() => {});
                    } else if (data.event === 'pusher:pong') {
                        console.log('💓 Получен WebSocket pong');
                    } else if (data.event === 'pusher:subscription_succeeded' || data.event === 'pusher_internal:subscription_succeeded') {
                        console.log('✅ Подписка на канал успешна:', data.channel);
                    } else if (data.event === 'pusher:subscription_error') {
                        console.error('❌ Ошибка подписки на канал:', data);
                    }
                    
                } catch (error) {
                    console.error('❌ Ошибка обработки WebSocket сообщения:', error);
                }
            };
            
            // Обработчик ошибок WebSocket
            this.wsConnection.onerror = (event) => {
                console.error('❌ WebSocket ошибка подключения:', event);
                clearTimeout(connectionTimeout);
                this.storage.addLogEntry('error', 'Соединение прервано');
            };
            
            // Обработчик закрытия WebSocket
            this.wsConnection.onclose = (event) => {
                console.log('🔌 WebSocket соединение закрыто:', event.code, event.reason);
                clearTimeout(connectionTimeout);
                
                if (event.code !== 1000) { // Не нормальное закрытие
                    this.storage.addLogEntry('error', 'Соединение прервано');
                    console.log('❌ WebSocket закрыт неожиданно, код:', event.code, 'причина:', event.reason);
                    
                    // Переподключаемся через 5 секунд
                    setTimeout(() => {
                        if (this.isActive && this.isAuthorized) {
                            console.log('🔄 Переподключение WebSocket...');
                            this.connectWebSocket();
                        }
                    }, 5000);
                }
            };
            
            // Отключаем polling - используем только WebSocket
            // this.startBackupPolling();
            
        } catch (error) {
            console.error('❌ Ошибка создания WebSocket подключения:', error);
            // Не используем fallback на polling
            this.isActive = false;
        }
        
        await updateActivityIcon();
    }
    
    disconnectWebSocket() {
        if (this.wsConnection) {
            this.wsConnection.close();
            this.wsConnection = null;
            console.log('🔌 WebSocket отключен');
        }
        this.stopHeartbeat();
    }
    
    startHeartbeat() {
        // Отправляем ping каждые 25 секунд (меньше чем activity_timeout=30)
        this.heartbeatInterval = setInterval(() => {
            if (this.wsConnection && this.wsConnection.readyState === WebSocket.OPEN) {
                console.log('💓 Отправляем WebSocket ping');
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
        if (this.wsConnection && this.wsConnection.readyState === WebSocket.OPEN) {
            // Получаем ID клиента для подписки на персональный канал
            try {
                const token = await this.platformAPI.getAuthToken();
                const userResponse = await this.platformAPI.makeRequest('/user');
                const clientId = userResponse.data.id;
                
                const channelName = `seller-${clientId}`;
                console.log('📡 Подписываемся на канал', channelName);
                
                this.wsConnection.send(JSON.stringify({
                    event: 'pusher:subscribe',
                    data: {
                        channel: channelName
                    }
                }));
            } catch (error) {
                console.error('❌ Ошибка подписки на канал:', error);
            }
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
        switch (message.type) {
            case 'GET_STATUS':
                sendResponse({
                    isActive: this.isActive,
                    isAuthorized: this.isAuthorized
                });
                break;
                
            case 'OPEN_DETACHED_WINDOW':
                try {
                    const window = await chrome.windows.create({
                        url: chrome.runtime.getURL('popup/detached.html'),
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
                            await chrome.windows.update(window.id, {
                                focused: true
                            });
                        }
                    } catch (iconError) {
                        // Window update error ignored
                    }
                    
                    sendResponse({ success: true, windowId: window.id });
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
                break;
                
            case 'AUTHORIZE':
                try {
                    await this.storage.setAuthToken(message.token);
                    this.isAuthorized = true;
                    await this.connectWebSocket();
                    await updateActivityIcon();
                    sendResponse({ success: true });
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
                break;
                
            case 'LOGOUT':
                await this.stop();
                await this.storage.clearAuth();
                this.isAuthorized = false;
                await updateActivityIcon();
                sendResponse({ success: true });
                break;
                
            case 'TOGGLE_ACTIVE':
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
                sendResponse({ isActive: this.isActive });
                break;
                
        }
    }
}

// Создаем экземпляр Trading Assistant
const tradingAssistant = new TradingAssistant();

// Отслеживание detached окон и keep-alive система
let detachedWindows = new Set();
let keepAliveTimer = null;
let isExtensionActive = false;

// Функции для поддержания активного состояния service worker
function startKeepAlive() {
    if (keepAliveTimer) return;
    
    // Создаем алармы для активности и тикера
    chrome.alarms.create('keepAlive', { periodInMinutes: 1 }); // keep-alive
    chrome.alarms.create('badgeTicker', { periodInMinutes: 0.1 }); // тикер каждые 6 сек
    
    // Сразу обновляем иконку
    updateActivityIcon();
}

function stopKeepAlive() {
    chrome.alarms.clear('keepAlive');
    chrome.alarms.clear('badgeTicker');
    chrome.action.setBadgeText({ text: '' }); // очищаем badge
    keepAliveTimer = null;
}

// Обработчик алармов  
chrome.alarms.onAlarm.addListener((alarm) => {
    if (alarm.name === 'keepAlive' || alarm.name === 'badgeTicker') {
        updateActivityIcon();
    }
});

// Функция обновления иконки через badge
async function updateActivityIcon() {
    try {
        // Обновляем title
        const title = (tradingAssistant.isActive && tradingAssistant.isAuthorized)
            ? 'CS-SKINS.pro - Активен'
            : tradingAssistant.isAuthorized
            ? 'CS-SKINS.pro - Подключен'
            : 'CS-SKINS.pro - Не подключен';
            
        await chrome.action.setTitle({ title });
        
        if (tradingAssistant.isActive && tradingAssistant.isAuthorized) {
            // Активно И авторизовано - зеленый badge
            chrome.action.setBadgeText({ text: '●' });
            chrome.action.setBadgeBackgroundColor({ color: '#44ff44' });
            
        } else if (tradingAssistant.isAuthorized) {
            // Авторизовано но неактивно - оранжевый badge  
            chrome.action.setBadgeText({ text: '○' });
            chrome.action.setBadgeBackgroundColor({ color: '#ff8d2f' });
            
        } else if (detachedWindows.size > 0) {
            // Detached окно открыто но не авторизовано - серый но круглый
            chrome.action.setBadgeText({ text: '○' });
            chrome.action.setBadgeBackgroundColor({ color: '#888888' });
            
        } else {
            // Не авторизовано - серый крестик
            chrome.action.setBadgeText({ text: '×' });
            chrome.action.setBadgeBackgroundColor({ color: '#888888' });
        }
        
    } catch (error) {
        // Ignore icon update errors - fallback to default
        chrome.action.setBadgeText({ text: '' });
    }
}

// Обработчики событий Chrome API
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    tradingAssistant.handleMessage(message, sender, sendResponse);
    return true;
});

// Обработчик закрытия окон
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

