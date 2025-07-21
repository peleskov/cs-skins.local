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
    
    async getExtensionStats() {
        const response = await this.makeRequest('/stats');
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
        
        return await this.setData({ logs });
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
        
        const toggleBtn = document.getElementById('toggleBtn');
        const refreshBtn = document.getElementById('refreshBtn');
        const settingsBtn = document.getElementById('settingsBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const detachBtn = document.getElementById('detachBtn');
        
        toggleBtn?.addEventListener('click', () => this.handleToggle());
        refreshBtn?.addEventListener('click', () => this.handleRefresh());
        settingsBtn?.addEventListener('click', () => this.handleSettings());
        logoutBtn?.addEventListener('click', () => this.handleLogout());
        
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
        notificationClose?.addEventListener('click', () => this.hideNotification());
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
        const statusIndicator = document.getElementById('statusIndicator');
        const statusDot = statusIndicator.querySelector('.status-dot');
        const statusText = statusIndicator.querySelector('.status-text');
        
        if (this.extensionStatus.isActive && this.isAuthorized) {
            statusDot.className = 'status-dot connected';
            statusText.textContent = 'Активен';
        } else if (this.isAuthorized) {
            statusDot.className = 'status-dot warning';
            statusText.textContent = 'Подключен';
        } else {
            statusDot.className = 'status-dot';
            statusText.textContent = 'Не подключен';
        }
    }
    
    showUnauthorizedContent() {
        document.getElementById('unauthorizedContent').style.display = 'block';
        document.getElementById('authorizedContent').style.display = 'none';
    }
    
    showAuthorizedContent() {
        document.getElementById('unauthorizedContent').style.display = 'none';
        document.getElementById('authorizedContent').style.display = 'block';
        
        this.updateUserInfo();
        this.updateStats();
        this.updateControls();
        this.updateActivity();
    }
    
    updateUserInfo() {
        if (!this.userInfo) return;
        
        const userName = document.getElementById('userName');
        const userSteam = document.getElementById('userSteam');
        const userAvatar = document.getElementById('userAvatar');
        
        userName.textContent = this.userInfo.name || 'Пользователь';
        userSteam.textContent = this.userInfo.steam_id ? `Steam ID: ${this.userInfo.steam_id}` : '';
        
        if (this.userInfo.steam_avatar) {
            userAvatar.src = this.userInfo.steam_avatar;
            userAvatar.style.display = 'block';
            userAvatar.nextElementSibling.style.display = 'none';
        }
    }
    
    async updateStats() {
        try {
            const stats = await this.storage.getStats();
            const extensionStats = await this.api.getExtensionStats();
            
            document.getElementById('ordersToday').textContent = extensionStats?.ordersToday || 0;
            document.getElementById('totalTrades').textContent = stats.tradesCreated || 0;
            
            const successRate = stats.tradesCreated > 0 
                ? Math.round((stats.tradesSuccessful / stats.tradesCreated) * 100)
                : 0;
            document.getElementById('successRate').textContent = `${successRate}%`;
            
        } catch (error) {
            // Ignore stats update errors
        }
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
            
            if (logs.length === 0) {
                activityList.innerHTML = `
                    <div class="activity-item">
                        <div class="activity-text">Нет активности</div>
                        <div class="activity-time">—</div>
                    </div>
                `;
                return;
            }
            
            activityList.innerHTML = logs.map(log => `
                <div class="activity-item">
                    <div class="activity-text">${log.message}</div>
                    <div class="activity-time">${this.formatTime(log.timestamp)}</div>
                </div>
            `).join('');
            
        } catch (error) {
            // Ignore activity update errors
        }
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
            await this.storage.setAuthToken(token);
            await this.api.authorize(token);
            await this.loadUserInfo();
            
            await this.sendMessageToBackground('AUTHORIZE', { token });
            
            this.isAuthorized = true;
            await this.loadState();
            this.updateInterface();
            
            this.showNotification('Успешно подключено!', 'success');
            await this.storage.addLogEntry('success', 'Расширение подключено к аккаунту');
            
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
            const response = await this.sendMessageToBackground('TOGGLE_ACTIVE');
            this.extensionStatus.isActive = response.isActive;
            
            this.updateInterface();
            
            const message = response.isActive ? 'Расширение запущено' : 'Расширение остановлено';
            this.showNotification(message, 'success');
            
            await this.storage.addLogEntry('info', message);
            
        } catch (error) {
            this.showNotification('Ошибка изменения состояния', 'error');
        }
    }
    
    async handleRefresh() {
        await this.loadState();
        this.updateInterface();
        this.showNotification('Статус обновлен', 'success');
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
        setInterval(async () => {
            if (this.isAuthorized) {
                await this.loadState();
                this.updateStatusIndicator();
                this.updateControls();
                
                if (Date.now() % 60000 < 10000) {
                    this.updateStats();
                    this.updateActivity();
                }
            }
        }, 10000);
    }
}

// Инициализируем popup когда DOM готов
document.addEventListener('DOMContentLoaded', () => {
    new PopupInterface();
});