class PopupManager {
    constructor() {
        this.API_BASE_URL = 'https://cs-skins.s1temaker.ru';
        this.storageKey = 'cs2_marketplace_extension';
        this.currentUser = null;
        this.isRunning = false;
        
        this.init();
    }
    
    async init() {
        
        const data = await this.getStorage();
        
        if (data?.authToken && data?.websocketChannel) {
            // Есть полные данные авторизации
            this.showView('authorized');
            const userData = await this.loadUserData();
            if (userData) {
                this.updateUserInfo(userData);
            } else {
                // Токен есть, но данные пользователя не загрузились
                document.getElementById('userName').textContent = 'Пользователь';
                document.getElementById('userSteam').textContent = '';
            }
            
            // Уведомляем service worker что мы авторизованы
            chrome.runtime.sendMessage({ type: 'AUTHORIZE', token: data.authToken }).catch(() => {});
        } else {
            this.showView('unauthorized');
        }
        
        this.bindEvents();
        chrome.runtime.sendMessage({ type: 'GET_STATUS' });
        
        // Уведомляем service worker о необходимости изменить размер окна
        this.notifyWindowResize();
    }
    
    notifyWindowResize() {
        const isCompact = document.body.classList.contains('compact');
        chrome.runtime.sendMessage({ 
            type: 'RESIZE_WINDOW', 
            isAuthorized: isCompact 
        }).catch(() => {});
    }
    
    bindEvents() {
        document.getElementById('authorizeBtn').addEventListener('click', () => this.handleAuthorize());
        document.getElementById('toggleBtn').addEventListener('click', () => this.handleToggle());
        document.querySelector('.notification-close').addEventListener('click', () => this.hideNotification());
        
        chrome.runtime.onMessage.addListener((message) => {
            if (message.type === 'STATUS_UPDATE') {
                this.updateStatuses(message.data);
            } else if (message.type === 'FORCE_LOGOUT') {
                this.showView('unauthorized');
                this.showNotification(message.message || 'Требуется переавторизация', 'warning');
            }
        });
        
        // Запрашиваем статус после авторизации и периодически обновляем
        this.startStatusUpdates();
    }
    
    async getStorage() {
        try {
            const result = await chrome.storage.local.get(this.storageKey);
            return result[this.storageKey] || {};
        } catch (error) {
            return {};
        }
    }
    
    showView(type) {
        const unauthorized = document.getElementById('unauthorizedContent');
        const authorized = document.getElementById('authorizedContent');
        
        if (type === 'unauthorized') {
            unauthorized.style.display = 'block';
            authorized.style.display = 'none';
            document.body.className = 'expanded';
        } else {
            unauthorized.style.display = 'none';
            authorized.style.display = 'block';
            document.body.className = 'compact';
        }
        
        // Уведомляем о смене состояния для изменения размера окна
        this.notifyWindowResize();
    }
    
    setButtonState(btnId, loading) {
        const btn = document.getElementById(btnId);
        const text = btn.querySelector('.btn-text');
        const loader = btn.querySelector('.btn-loader');
        
        if (loading) {
            text.style.display = 'none';
            loader.style.display = 'inline';
            btn.disabled = true;
        } else {
            text.style.display = 'inline';
            loader.style.display = 'none';
            btn.disabled = false;
        }
    }

    async handleAuthorize() {
        const tokenInput = document.getElementById('authToken');
        const token = tokenInput.value.trim();
        
        if (!token) {
            this.showNotification('Введите токен авторизации', 'error');
            return;
        }

        this.setButtonState('authorizeBtn', true);

        try {
            // Сначала проверяем токен через HTTP запрос
            const authResponse = await fetch(`${this.API_BASE_URL}/api/ext-api/auth`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token })
            });
            
            const authData = await authResponse.json();
            
            if (authResponse.ok && authData.success && authData.channel) {
                // Сохраняем данные авторизации локально
                await chrome.storage.local.set({
                    [this.storageKey]: {
                        authToken: token,
                        websocketChannel: authData.channel,
                        authorizedAt: new Date().toISOString()
                    }
                });
                
                // Загружаем информацию о пользователе
                const userData = await this.loadUserData();
                
                // Отправляем команду на авторизацию в service worker
                await chrome.runtime.sendMessage({ type: 'AUTHORIZE', token });
                
                // Обновляем UI
                this.showView('authorized');
                if (userData) this.updateUserInfo(userData);
                this.showNotification('Успешно авторизовано', 'success');
            } else {
                throw new Error(authData.message || 'Ошибка авторизации');
            }
        } catch (error) {
            this.showNotification(error.message || 'Ошибка подключения к серверу', 'error');
        } finally {
            this.setButtonState('authorizeBtn', false);
            tokenInput.value = '';
        }
    }

    async loadUserData() {
        try {
            const data = await this.getStorage();
            if (data.userInfo) return data.userInfo;

            if (!data.authToken) return null;

            const response = await fetch(`${this.API_BASE_URL}/api/ext-api/user`, {
                headers: { 'Authorization': `Bearer ${data.authToken}` }
            });

            if (response.ok) {
                const userData = await response.json();
                const userInfo = userData.data || userData.user || userData;
                const updatedData = { ...data, userInfo: userInfo };
                await chrome.storage.local.set({
                    [this.storageKey]: updatedData
                });
                return userInfo;
            } else {
                if (response.status === 401 || response.status === 403) {
                    await chrome.storage.local.set({
                        [this.storageKey]: {}
                    });
                }
                return null;
            }
        } catch (error) {
        }
        return null;
    }

    updateUserInfo(user) {
        
        // Имя пользователя - приоритет: name, потом email
        const userName = user.name || user.email || 'Пользователь';
        document.getElementById('userName').textContent = userName;
        
        // Steam информация
        const steamInfo = user.steam_id ? `Steam ID: ${user.steam_id}` : '';
        document.getElementById('userSteam').textContent = steamInfo;
        
        // Аватар
        if (user.steam_avatar) {
            const avatar = document.getElementById('userAvatar');
            avatar.src = user.steam_avatar;
            avatar.style.display = 'block';
            avatar.nextElementSibling.style.display = 'none';
        }
    }

    async handleToggle() {
        try {
            await chrome.runtime.sendMessage({ type: 'LOGOUT' });
            this.showView('unauthorized');
            this.showNotification('Расширение отключено', 'success');
        } catch (error) {
            // Тихо игнорируем ошибки выхода
        }
    }

    updateStatuses(data) {
        // WebSocket статус на основе overallStatus
        const websocketActive = data.overallStatus === 'active';
        const websocketPending = data.overallStatus === 'pending';
        
        this.updateStatusIndicator('websocketStatus', websocketActive, 
            'Подключено к серверу', 'Отключено от сервера', websocketPending);
        
        // Steam статус на основе steamStatus
        const steamActive = data.steamStatus === 'ok';
        let steamInactiveTitle = 'Steam сессия не активна';
        
        if (data.steamStatus === 'wrong_account') {
            steamInactiveTitle = 'Авторизован другой пользователь Steam';
        } else if (data.steamStatus === 'no_auth') {
            steamInactiveTitle = 'Не авторизован в Steam';
        } else if (data.steamStatus === 'error') {
            steamInactiveTitle = 'Ошибка получения Steam сессии';
        }
        
        this.updateStatusIndicator('steamStatus', steamActive, 
            'Steam сессия активна', steamInactiveTitle);
        
        // Показываем/скрываем loader только в авторизованном состоянии
        const isAuthorized = document.getElementById('authorizedContent').style.display !== 'none';
        if (isAuthorized) {
            if (websocketActive) {
                this.hideLoader();
            } else {
                this.showLoader('Подключение к серверу...<br>Подождите 20-30 секунд');
            }
        }
    }
    
    showLoader(text = 'Загрузка...') {
        const loader = document.getElementById('fullscreenLoader');
        const loaderText = loader.querySelector('.loader-text');
        loaderText.innerHTML = text;
        loader.classList.add('active');
    }
    
    hideLoader() {
        const loader = document.getElementById('fullscreenLoader');
        loader.classList.remove('active');
    }
    
    updateStatusIndicator(id, isActive, activeTitle, inactiveTitle, isPending = false) {
        const element = document.getElementById(id);
        element.classList.toggle('active', isActive);
        element.classList.toggle('warning', isPending);
        element.title = isPending ? 'Подключение...' : (isActive ? activeTitle : inactiveTitle);
    }

    showNotification(text, type = 'info') {
        const notification = document.getElementById('notification');
        notification.className = `notification ${type}`;
        notification.querySelector('.notification-text').textContent = text;
        notification.style.display = 'block';
        
        setTimeout(() => this.hideNotification(), 5000);
    }

    hideNotification() {
        document.getElementById('notification').style.display = 'none';
    }
    
    startStatusUpdates() {
        // Первый запрос статуса
        setTimeout(() => {
            chrome.runtime.sendMessage({ type: 'GET_STATUS' }, (response) => {
                if (response) {
                    this.updateStatuses(response);
                }
            });
        }, 1000);
        
        // Периодическое обновление статусов каждые 5 секунд
        setInterval(() => {
            chrome.runtime.sendMessage({ type: 'GET_STATUS' }, (response) => {
                if (response) {
                    this.updateStatuses(response);
                }
            });
        }, 5000);
    }
}

document.addEventListener('DOMContentLoaded', () => new PopupManager());