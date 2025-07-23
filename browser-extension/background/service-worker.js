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
}

// Основной класс Trading Assistant
class TradingAssistant {
    constructor() {
        this.isActive = false;
        this.isAuthorized = false;
        this.pollInterval = null;
        this.platformAPI = new PlatformAPI();
        this.storage = new ExtensionStorage();
        
        this.init();
    }
    
    async init() {
        this.isAuthorized = await this.storage.isAuthorized();
        if (this.isAuthorized) {
            await this.startPolling();
        }
        
        await updateActivityIcon();
    }
    
    async startPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        this.isActive = true;
        startKeepAlive();
        
        this.pollInterval = setInterval(async () => {
            try {
                await this.checkForNewOrders();
            } catch (error) {
                // Order check error ignored
            }
        }, 5000);
        
        await updateActivityIcon();
    }
    
    async stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        
        this.isActive = false;
        
        if (detachedWindows.size === 0) {
            stopKeepAlive();
        }
        
        await updateActivityIcon();
    }
    
    async checkForNewOrders() {
        try {
            console.log('🔍 Проверяем новые заказы...');
            const orders = await this.platformAPI.getPendingOrders();
            console.log('📦 Получено заказов:', orders?.length || 0);
            
            if (orders && orders.length > 0) {
                console.log('✅ Найдены заказы:', orders);
                for (const order of orders) {
                    await this.processOrder(order);
                }
            }
        } catch (error) {
            console.error('❌ Ошибка получения заказов:', error);
            if (error.status === 401) {
                await this.stopPolling();
                await this.storage.clearAuth();
            }
        }
    }
    
    async processOrder(order) {
        try {
            const tabs = await chrome.tabs.query({
                url: "https://steamcommunity.com/*"
            });
            
            if (tabs.length > 0) {
                await chrome.tabs.sendMessage(tabs[0].id, {
                    type: 'CREATE_TRADE_OFFER',
                    order: order
                });
            } else {
                const tab = await chrome.tabs.create({
                    url: 'https://steamcommunity.com/tradeoffer/new/',
                    active: false
                });
                
                setTimeout(async () => {
                    await chrome.tabs.sendMessage(tab.id, {
                        type: 'CREATE_TRADE_OFFER',
                        order: order
                    });
                }, 3000);
            }
            
            await this.showNotification(
                'Новый заказ!',
                `Создаем трейд для заказа #${order.id} на сумму ${order.total}₽`
            );
            
        } catch (error) {
            // Order processing error ignored
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
                    await this.startPolling();
                    await updateActivityIcon();
                    sendResponse({ success: true });
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
                break;
                
            case 'LOGOUT':
                await this.stopPolling();
                await this.storage.clearAuth();
                this.isAuthorized = false;
                await updateActivityIcon();
                sendResponse({ success: true });
                break;
                
            case 'TOGGLE_ACTIVE':
                if (this.isActive) {
                    await this.stopPolling();
                } else {
                    const isAuthorized = await this.storage.isAuthorized();
                    if (isAuthorized) {
                        this.isAuthorized = true;
                        await this.startPolling();
                    }
                }
                await updateActivityIcon();
                sendResponse({ isActive: this.isActive });
                break;
                
            case 'TRADE_OFFER_CREATED':
                try {
                    await this.platformAPI.updateTradeStatus(
                        message.orderId, 
                        'trade_sent',
                        { tradeOfferId: message.tradeOfferId }
                    );
                    // Trade sent successfully
                } catch (error) {
                    // Trade status update error ignored
                }
                break;
                
            case 'TRADE_OFFER_ERROR':
                try {
                    await this.platformAPI.updateTradeStatus(
                        message.orderId, 
                        'error',
                        { error: message.error }
                    );
                } catch (error) {
                    // Trade status update error ignored
                }
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

chrome.runtime.onSuspend.addListener(() => {
    if (tradingAssistant.pollInterval) {
        clearInterval(tradingAssistant.pollInterval);
    }
});