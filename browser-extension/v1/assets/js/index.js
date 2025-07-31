// CS-SKINS.pro Trading Assistant - Popup Interface

// API клиент (копия из service worker)
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
    
    async authorize(token) {
        return await this.makeRequest('/auth', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ token })
        });
    }
    
    async getUserInfo() {
        const response = await this.makeRequest('/user');
        return response.data;
    }
    
}

// Storage клиент (копия из service worker)
class ExtensionStorage {
    constructor() {
        this.storageKey = 'cs2_marketplace_extension';
    }
    
    async getData() {
        try {
            const result = await chrome.storage.local.get(this.storageKey);
            return result[this.storageKey] || {};
        } catch (error) {
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
            return false;
        }
    }
    
    async isAuthorized() {
        const data = await this.getData();
        return !!data.authToken;
    }
    
    async setAuthToken(token) {
        return this.setData({
            authToken: token,
            authorizedAt: new Date().toISOString()
        });
    }
    
    async clearAuth() {
        return this.setData({
            authToken: null,
            authorizedAt: null,
            userInfo: null
        });
    }
    
    async getUserInfo() {
        const data = await this.getData();
        return data.userInfo || null;
    }
    
    async setUserInfo(userInfo) {
        return this.setData({
            userInfo: userInfo,
            userInfoUpdatedAt: new Date().toISOString()
        });
    }
    
    async getStats() {
        const data = await this.getData();
        return data.stats || {
            tradesCreated: 0,
            tradesSuccessful: 0,
            tradesFailed: 0,
            totalEarnings: 0,
            lastTradeAt: null
        };
    }
    
    async getLogs(limit = 50) {
        const data = await this.getData();
        const logs = data.logs || [];
        return logs.slice(0, limit);
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
        
        // Уведомляем об обновлении лога (хотя в popup это менее критично)
        chrome.runtime.sendMessage({
            type: 'LOG_UPDATED',
            logEntry: logEntry
        }).catch(() => {});
        
        return result;
    }
}

// Popup Interface класс
class PopupInterface {
    constructor() {
        this.api = new PlatformAPI();
        this.storage = new ExtensionStorage();
        this.isAuthorized = false;
        this.userInfo = null;
        this.extensionStatus = { isActive: false, isAuthorized: false };
        
        this.init();
    }
    
    async init() {
        await this.loadState();
        this.setupEventListeners();
        this.updateInterface();
        this.startPeriodicUpdate();
        
        if (!window.isDetachedWindow) {
            this.handleDetach();
        }
    }
    
    async loadState() {
        try {
            const response = await this.sendMessageToBackground('GET_STATUS');
            this.extensionStatus = response;
            
            this.isAuthorized = await this.storage.isAuthorized();
            if (this.isAuthorized) {
                this.userInfo = await this.storage.getUserInfo();
                
                if (!this.userInfo) {
                    await this.loadUserInfo();
                }
            }
        } catch (error) {
            // Ignore state loading errors
        }
    }
    
    async loadUserInfo() {
        try {
            const userInfo = await this.api.getUserInfo();
            await this.storage.setUserInfo(userInfo);
            this.userInfo = userInfo;
        } catch (error) {
            // Ignore user info loading errors
        }
    }
    
    setupEventListeners() {
        const authorizeBtn = document.getElementById('authorizeBtn');
        const authTokenInput = document.getElementById('authToken');
        
        authorizeBtn.addEventListener('click', () => this.handleAuthorize());
        authTokenInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.handleAuthorize();
            }
        });
        
        // Слушаем сообщения от background script
        chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
            this.handleBackgroundMessage(message);
        });
        
        const toggleBtn = document.getElementById('toggleBtn');
        const refreshBtn = document.getElementById('refreshBtn');
        const settingsBtn = document.getElementById('settingsBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const detachBtn = document.getElementById('detachBtn');
        const clearLogBtn = document.getElementById('clearLogBtn');
        
        if (toggleBtn) toggleBtn.addEventListener('click', () => this.handleToggle());
        if (refreshBtn) refreshBtn.addEventListener('click', () => this.handleRefresh());
        if (settingsBtn) settingsBtn.addEventListener('click', () => this.handleSettings());
        if (logoutBtn) logoutBtn.addEventListener('click', () => this.handleLogout());
        if (clearLogBtn) clearLogBtn.addEventListener('click', () => this.handleClearLog());
        
        
        // Добавляем обработчик detach только если это не отдельное окно
        if (detachBtn && !window.isDetachedWindow) {
            detachBtn.addEventListener('click', () => this.handleDetach());
        } else if (detachBtn && window.isDetachedWindow) {
            // Для отдельного окна добавляем простой обработчик закрытия
            detachBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                window.close();
            });
        }
        
        const notificationClose = document.querySelector('.notification-close');
        if (notificationClose) notificationClose.addEventListener('click', () => this.hideNotification());
    }
    
    updateInterface() {
        this.updateStatusIndicator();
        
        if (this.isAuthorized && this.extensionStatus.isAuthorized) {
            this.showAuthorizedContent();
        } else {
            this.showUnauthorizedContent();
        }
    }
    
    updateStatusIndicator() {
        // Обновляем WebSocket статус
        const websocketIndicator = document.getElementById('websocketStatus');
        const wsStatusDot = websocketIndicator.querySelector('.status-dot');
        const wsStatusText = websocketIndicator.querySelector('.status-text');
        
        const { isActive, isAuthorized } = this.extensionStatus;
        const wsStatus = this.getWebSocketStatusConfig(isActive && this.isAuthorized, this.isAuthorized);
        
        wsStatusDot.className = `status-dot ${wsStatus.class}`;
        wsStatusText.textContent = wsStatus.text;
        websocketIndicator.title = wsStatus.tooltip;
        
        // Проверяем и обновляем Steam статус
        this.updateSteamStatus();
    }
    
    async updateSteamStatus() {
        const steamIndicator = document.getElementById('steamStatus');
        const steamStatusDot = steamIndicator.querySelector('.status-dot');
        const steamStatusText = steamIndicator.querySelector('.status-text');
        
        try {
            const steamStatus = await chrome.runtime.sendMessage({ type: 'CHECK_STEAM_STATUS' });
            const statusConfig = await this.getSteamStatusConfig(steamStatus);
            
            steamStatusDot.className = `status-dot ${statusConfig.class}`;
            steamStatusText.textContent = statusConfig.text;
            steamIndicator.title = statusConfig.tooltip;
        } catch (error) {
            steamStatusDot.className = 'status-dot';
            steamStatusText.textContent = 'Ошибка';
            steamIndicator.title = 'Ошибка связи с background script';
        }
    }
    
    getWebSocketStatusConfig(isActive, isAuthorized) {
        // Упрощенная логика: только два состояния
        if (isActive && isAuthorized) {
            return { 
                class: 'connected', 
                text: 'Активен',
                tooltip: 'CS-SKINS соединение активно, готов к получению заказов'
            };
        } else {
            return { 
                class: 'error', 
                text: 'Не активен',
                tooltip: isAuthorized ? 'Нажмите "Запустить" для активации' : 'Требуется авторизация через токен'
            };
        }
    }
    
    async getSteamStatusConfig(steamStatus) {
        // Проверяем, активно ли расширение
        if (!this.extensionStatus.isActive) {
            return { 
                class: 'error', 
                text: 'Не активен',
                tooltip: 'Расширение остановлено'
            };
        }
        
        if (!steamStatus) {
            return { 
                class: 'warning', 
                text: 'Не активен',
                tooltip: 'Проверяется статус Steam сессии...'
            };
        }
        
        // Получаем имя пользователя для tooltip
        const userName = this.userInfo?.name || this.userInfo?.steam_id;
        
        // Упрощенная логика: только два состояния
        if (steamStatus.available && steamStatus.state === 'ready') {
            return { 
                class: 'connected', 
                text: 'Активен',
                tooltip: 'Steam авторизован, готов к созданию трейдов'
            };
        } else {
            const tooltip = userName 
                ? `Откройте сайт Steam и авторизуйтесь как ${userName}`
                : 'Откройте сайт Steam и авторизуйтесь';
            
            return { 
                class: 'error', 
                text: 'Не активен',
                tooltip: tooltip
            };
        }
    }
    
    handleBackgroundMessage(message) {
        const handlers = {
            'NEW_ORDER_RECEIVED': () => {
                // Статистика придет автоматически с событием
                this.updateActivity();
                this.showNotification('Новый заказ получен!', 'success');
            },
            'TRADE_STATUS_CHANGED': () => {
                // Статистика придет автоматически с событием
                this.updateActivity();
            },
            'LOG_UPDATED': () => {
                this.updateActivity();
            },
            'STATS_RECEIVED': () => {
                if (document.getElementById('activeTrades')) {
                    this.displayStats();
                }
            },
            'FORCE_LOGOUT': () => {
                // Принудительное отключение
                this.isAuthorized = false;
                this.userInfo = null;
                this.extensionStatus = { isActive: false, isAuthorized: false };
                
                this.updateInterface();
                this.showNotification(message.message || 'Требуется переавторизация', 'warning');
            }
        };
        
        const handler = handlers[message.type];
        if (handler) {
            handler();
        }
    }
    
    showUnauthorizedContent() {
        document.getElementById('unauthorizedContent').style.display = 'block';
        document.getElementById('authorizedContent').style.display = 'none';
    }
    
    async showAuthorizedContent() {
        document.getElementById('unauthorizedContent').style.display = 'none';
        document.getElementById('authorizedContent').style.display = 'block';
        
        this.updateUserInfo();
        this.updateControls();
        this.updateActivity();
        
        await this.displayStats();
    }
    
    updateUserInfo() {
        if (!this.userInfo) return;
        
        const userName = document.getElementById('userName');
        const userSteam = document.getElementById('userSteam');
        const userAvatar = document.getElementById('userAvatar');
        const userDetailsLink = document.getElementById('userDetailsLink');
        
        userName.textContent = this.userInfo.name || 'Пользователь';
        userSteam.textContent = this.userInfo.steam_id ? `Steam ID: ${this.userInfo.steam_id}` : '';
        
        // Устанавливаем ссылку на Steam профиль
        if (this.userInfo.steam_id) {
            userDetailsLink.href = `https://steamcommunity.com/profiles/${this.userInfo.steam_id}`;
            userDetailsLink.title = 'Открыть Steam профиль';
        }
        
        if (this.userInfo.steam_avatar) {
            userAvatar.src = this.userInfo.steam_avatar;
            userAvatar.style.display = 'block';
            userAvatar.nextElementSibling.style.display = 'none';
        }
    }
    
    async updateStats() {
        try {
            this.showLoader();
            await this.sendMessageToBackground('REQUEST_STATS');
            setTimeout(() => this.displayStats(), 100);
        } catch (error) {
            console.error('Error requesting stats:', error);
            this.hideLoader();
        }
    }
    
    async displayStats() {
        if (!document.getElementById('activeTrades')) {
            return;
        }
        
        try {
            const response = await this.sendMessageToBackground('GET_CACHED_STATS');
            if (!response.success || !response.stats) {
                this.hideLoader();
                return;
            }
            
            const statistics = response.stats.statistics || {};
            
            const statFields = {
                'activeTrades': statistics.active || statistics.pending || 0,
                'completedTrades': statistics.completed || 0,
                'cancelledTrades': statistics.cancelled || 0,
                'totalTradesToday': statistics.total || 0
            };
            
            Object.entries(statFields).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element) element.textContent = value;
            });
        } catch (error) {
            console.error('Error loading stats from storage:', error);
        }
        
        this.hideLoader();
    }
    
    updateControls() {
        const toggleBtn = document.getElementById('toggleBtn');
        const btnLabel = toggleBtn.querySelector('.btn-label');
        const playIcon = toggleBtn.querySelector('.play-icon');
        const pauseIcon = toggleBtn.querySelector('.pause-icon');
        
        if (this.extensionStatus.isActive) {
            playIcon.style.display = 'none';
            pauseIcon.style.display = 'block';
            btnLabel.textContent = 'Остановить';
        } else {
            playIcon.style.display = 'block';
            pauseIcon.style.display = 'none';
            btnLabel.textContent = 'Запустить';
        }
    }
    
    async updateActivity() {
        try {
            const logs = await this.storage.getLogs(5);
            const activityList = document.getElementById('activityList');
            
            const html = logs.length === 0 
                ? this.getEmptyActivityHtml()
                : logs.map(log => this.getActivityItemHtml(log)).join('');
                
            activityList.innerHTML = html;
        } catch (error) {
            // Ignore activity update errors
        }
    }
    
    getEmptyActivityHtml() {
        return `
            <div class="activity-item">
                <div class="activity-text">Нет активности</div>
                <div class="activity-time">—</div>
            </div>
        `;
    }
    
    getActivityItemHtml(log) {
        return `
            <div class="activity-item">
                <div class="activity-text">${log.message}</div>
                <div class="activity-time">${this.formatTime(log.timestamp)}</div>
            </div>
        `;
    }
    
    async handleAuthorize() {
        const tokenInput = document.getElementById('authToken');
        const authorizeBtn = document.getElementById('authorizeBtn');
        const btnText = authorizeBtn.querySelector('.btn-text');
        const btnLoader = authorizeBtn.querySelector('.btn-loader');
        
        const token = tokenInput.value.trim();
        if (!token) {
            this.showNotification('Введите токен авторизации', 'error');
            return;
        }
        
        authorizeBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoader.style.display = 'inline';
        
        try {
            // Сначала авторизуемся на сервере и получаем канал
            const response = await this.api.authorize(token);
            
            if (response.success && response.channel) {
                await this.storage.setData({
                    authToken: token,
                    websocketChannel: response.channel,
                    authorizedAt: new Date().toISOString()
                });
                
                await this.loadUserInfo();
                await this.sendMessageToBackground('AUTHORIZE', { token });
                
                this.isAuthorized = true;
                await this.loadState();
                this.updateInterface();
                
                this.showNotification('Успешно подключено!', 'success');
            } else {
                throw new Error('Failed to get WebSocket channel');
            }
            
        } catch (error) {
            this.showNotification('Ошибка подключения. Проверьте токен.', 'error');
            await this.storage.clearAuth();
        }
        
        authorizeBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
        tokenInput.value = '';
    }
    
    async handleToggle() {
        try {
            // Если запускаем расширение, сначала проверяем Steam статус
            if (!this.extensionStatus.isActive) {
                await this.updateSteamStatus();
            }
            
            const response = await this.sendMessageToBackground('TOGGLE_ACTIVE');
            this.extensionStatus.isActive = response.isActive;
            
            this.updateInterface();
            
            const message = response.isActive ? 'Расширение запущено' : 'Расширение остановлено';
            this.showNotification(message, 'success');
            
            
        } catch (error) {
            this.showNotification('Ошибка изменения состояния', 'error');
        }
    }
    
    async handleRefresh() {
        try {
            // Запрашиваем статистику (отправит событие на сервер через WebSocket)
            await this.updateStats();
            
            await this.loadState();
            this.updateInterface();
            
            // Принудительно обновляем Steam статус
            await this.updateSteamStatus();
            
        } catch (error) {
            console.error('Error during refresh:', error);
            this.showNotification('Ошибка обновления', 'error');
        }
    }
    
    handleSettings() {
        chrome.tabs.create({
            url: 'https://cs-skins.s1temaker.ru/profile'
        });
    }
    
    async handleDetach() {
        // Только для обычного popup
        try {
            const response = await this.sendMessageToBackground('OPEN_DETACHED_WINDOW');
            
            if (response.success) {
                window.close();
            } else {
                this.showNotification('Ошибка открытия окна', 'error');
            }
        } catch (error) {
            this.showNotification('Ошибка создания окна', 'error');
        }
    }
    
    async handleLogout() {
        try {
            await this.sendMessageToBackground('LOGOUT');
            await this.storage.clearAuth();
            
            this.isAuthorized = false;
            this.userInfo = null;
            this.extensionStatus = { isActive: false, isAuthorized: false };
            
            this.updateInterface();
            this.showNotification('Расширение отключено', 'success');
            
        } catch (error) {
            this.showNotification('Ошибка отключения', 'error');
        }
    }
    
    async handleClearLog() {
        try {
            await this.storage.setData({ logs: [] });
            this.updateActivity();
            this.showNotification('Лог очищен', 'success');
        } catch (error) {
            this.showNotification('Ошибка очистки лога', 'error');
        }
    }
    
    
    showNotification(message, type = 'info') {
        const notification = document.getElementById('notification');
        const notificationText = notification.querySelector('.notification-text');
        
        notificationText.textContent = message;
        notification.className = `notification ${type}`;
        notification.style.display = 'block';
        
        setTimeout(() => {
            this.hideNotification();
        }, 3000);
    }
    
    hideNotification() {
        const notification = document.getElementById('notification');
        notification.style.display = 'none';
    }
    
    showLoader() {
        const loader = document.getElementById('fullscreenLoader');
        if (loader) {
            loader.classList.add('active');
        }
    }
    
    hideLoader() {
        const loader = document.getElementById('fullscreenLoader');
        if (loader) {
            loader.classList.remove('active');
        }
    }
    
    async sendMessageToBackground(type, data = {}) {
        return new Promise((resolve, reject) => {
            chrome.runtime.sendMessage({ type, ...data }, (response) => {
                if (chrome.runtime.lastError) {
                    reject(new Error(chrome.runtime.lastError.message));
                } else {
                    resolve(response);
                }
            });
        });
    }
    
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) {
            return 'сейчас';
        } else if (diff < 3600000) {
            return `${Math.floor(diff / 60000)} мин`;
        } else if (diff < 86400000) {
            return `${Math.floor(diff / 3600000)} ч`;
        } else {
            return date.toLocaleDateString('ru-RU');
        }
    }
    
    startPeriodicUpdate() {
        let steamCheckCounter = 0;
        
        setInterval(async () => {
            if (!this.isAuthorized) return;
            
            await this.loadState();
            this.updateStatusIndicator();
            this.updateControls();
            
            // Обновляем только активность (логи), но не статистику
            this.updateActivity();
            
            // Проверяем Steam статус каждые 30 секунд (каждый 3-й цикл)
            steamCheckCounter++;
            if (steamCheckCounter >= 3) {
                await this.updateSteamStatus();
                steamCheckCounter = 0;
            }
        }, 10000);
    }
}

// Логика для detached окна - определяем по типу окна
function initDetachedWindow() {
    window.isDetachedWindow = true;
    
    // Применяем класс для detached окна
    document.body.classList.add('detached-window');
    
    // Меняем tooltip кнопки detach
    const detachBtn = document.getElementById('detachBtn');
    if (detachBtn) {
        detachBtn.title = 'Вернуться к popup';
    }
    
    // Обновляем заголовок окна в зависимости от состояния
    function updateWindowTitle() {
        const statusTextEl = document.querySelector('.status-text');
        const statusText = (statusTextEl && statusTextEl.textContent) || 'Не подключен';
        const baseTitle = 'CS-SKINS.pro Trading Assistant';
        document.title = `${baseTitle} - ${statusText}`;
    }
    
    // Обновляем заголовок при изменениях
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList && mutation.target.classList.contains('status-text')) {
                updateWindowTitle();
            }
        });
    });
    
    // Наблюдаем за изменениями статуса
    const statusIndicator = document.getElementById('statusIndicator');
    if (statusIndicator) {
        observer.observe(statusIndicator, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }
    
    // Первоначальное обновление заголовка
    setTimeout(updateWindowTitle, 1000);
}

// Инициализируем popup когда DOM готов
document.addEventListener('DOMContentLoaded', () => {
    // Проверяем тип окна для detached режима
    if (window.chrome && chrome.windows) {
        chrome.windows.getCurrent((currentWindow) => {
            if (currentWindow.type === 'popup') {
                initDetachedWindow();
            }
        });
    }
    
    new PopupInterface();
});