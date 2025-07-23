// CS-SKINS.pro Trading Assistant - Steam Content Script
class SteamTradeInjector {
    constructor() {
        this.isInitialized = false;
        this.pendingOrders = [];
        
        this.init();
    }
    
    init() {
        // Ждем полной загрузки страницы
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }
    
    setup() {
        // Проверяем, что мы на странице Steam
        if (!this.isSteamSite()) {
            return;
        }
        
        // Слушаем сообщения от background script
        chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
            this.handleMessage(message, sender, sendResponse);
        });
        
        // Ищем Steam session данные
        this.extractSteamSession();
        
        this.isInitialized = true;
    }
    
    isSteamSite() {
        return window.location.hostname === 'steamcommunity.com';
    }
    
    handleMessage(message, sender, sendResponse) {
        
        switch (message.type) {
            case 'CREATE_TRADE_OFFER':
                this.createTradeOffer(message.order)
                    .then(result => sendResponse(result))
                    .catch(error => sendResponse({ success: false, error: error.message }));
                return true; // Асинхронный ответ
                
            case 'GET_STEAM_SESSION':
                sendResponse({
                    success: true,
                    sessionData: this.getSteamSessionData()
                });
                break;
                
            default:
                sendResponse({ success: false, error: 'Unknown message type' });
        }
    }
    
    async createTradeOffer(order) {
        try {
            
            // Получаем Steam session данные
            const sessionData = this.getSteamSessionData();
            if (!sessionData.sessionId) {
                throw new Error('Не удалось получить Steam session');
            }
            
            // Подготавливаем данные для трейда
            const tradeData = this.prepareTradeData(order, sessionData);
            
            // Отправляем трейд через Steam Web API
            const tradeOfferId = await this.sendTradeOffer(tradeData);
            
            // Уведомляем background script об успехе
            chrome.runtime.sendMessage({
                type: 'TRADE_OFFER_CREATED',
                orderId: order.id,
                tradeOfferId: tradeOfferId
            });
            
            return {
                success: true,
                tradeOfferId: tradeOfferId,
                message: `Трейд-оффер #${tradeOfferId} отправлен покупателю`
            };
            
        } catch (error) {
            
            // Уведомляем background script об ошибке
            chrome.runtime.sendMessage({
                type: 'TRADE_OFFER_ERROR',
                orderId: order.id,
                error: error.message
            });
            
            throw error;
        }
    }
    
    getSteamSessionData() {
        // Извлекаем данные сессии из Steam страницы
        const sessionData = {
            sessionId: this.extractSessionId(),
            steamId: this.extractSteamId(),
            csrfToken: this.extractCSRFToken()
        };
        
        console.log('🔑 Steam session данные:', sessionData);
        return sessionData;
    }
    
    extractSessionId() {
        // Ищем sessionid в cookies
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'sessionid') {
                return value;
            }
        }
        
        // Альтернативно ищем в g_sessionID переменной
        if (window.g_sessionID) {
            return window.g_sessionID;
        }
        
        return null;
    }
    
    extractSteamId() {
        // Ищем Steam ID в глобальных переменных
        if (window.g_steamID) {
            return window.g_steamID;
        }
        
        // Ищем в URL профиля
        const profileMatch = window.location.href.match(/steamcommunity\.com\/(profiles|id)\/([^\/]+)/);
        if (profileMatch) {
            return profileMatch[2];
        }
        
        return null;
    }
    
    extractCSRFToken() {
        // Ищем CSRF токен в мета тегах
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }
        
        // Ищем в скриптах
        const scripts = document.querySelectorAll('script');
        for (let script of scripts) {
            const content = script.textContent;
            const tokenMatch = content.match(/sessionid['"]\s*:\s*['"]([^'"]+)['"]/);
            if (tokenMatch) {
                return tokenMatch[1];
            }
        }
        
        return null;
    }
    
    prepareTradeData(order, sessionData) {
        // Подготавливаем данные для Steam API
        const tradeData = {
            sessionid: sessionData.sessionId,
            partner: this.steamIdToAccountId(order.buyer.steam_id),
            tradeoffermessage: `Заказ #${order.id} с CS-SKINS.pro. Спасибо за покупку!`,
            json_tradeoffer: JSON.stringify({
                newversion: true,
                version: 2,
                me: {
                    assets: order.items.map(item => ({
                        appid: 730, // CS2 App ID
                        contextid: '2',
                        amount: item.quantity,
                        assetid: item.steam_asset_id
                    })),
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
                trade_offer_access_token: this.extractTradeToken(order.buyer.trade_url)
            })
        };
        
        return tradeData;
    }
    
    steamIdToAccountId(steamId64) {
        // Конвертируем Steam ID64 в Account ID для API
        return (BigInt(steamId64) - BigInt('76561197960265728')).toString();
    }
    
    extractTradeToken(tradeUrl) {
        // Извлекаем токен из Trade URL
        const tokenMatch = tradeUrl.match(/token=([a-zA-Z0-9_-]+)/);
        return tokenMatch ? tokenMatch[1] : '';
    }
    
    async sendTradeOffer(tradeData) {
        try {
            // Отправляем POST запрос к Steam API
            const response = await fetch('https://steamcommunity.com/tradeoffer/new/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(tradeData),
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (result.strError) {
                throw new Error(result.strError);
            }
            
            if (!result.tradeofferid) {
                throw new Error('Не получен ID трейд-оффера');
            }
            
            return result.tradeofferid;
            
        } catch (error) {
            console.error('❌ Ошибка отправки трейда через Steam API:', error);
            throw error;
        }
    }
    
    extractSteamSession() {
        // Сохраняем важные данные Steam в локальное хранилище для использования
        const sessionData = this.getSteamSessionData();
        
        if (sessionData.sessionId) {
            console.log('✅ Steam session данные извлечены');
            
            // Отправляем данные background script для сохранения
            chrome.runtime.sendMessage({
                type: 'STEAM_SESSION_EXTRACTED',
                sessionData: sessionData
            });
        }
    }
    
    // Утилита для отладки - показывает информацию о странице
    debugPageInfo() {
        console.log('🔍 Steam Page Debug Info:', {
            url: window.location.href,
            sessionId: this.extractSessionId(),
            steamId: this.extractSteamId(),
            csrfToken: this.extractCSRFToken(),
            globalVars: {
                g_sessionID: window.g_sessionID,
                g_steamID: window.g_steamID,
                g_rgWalletInfo: window.g_rgWalletInfo
            }
        });
    }
}

// Инициализируем content script
const steamInjector = new SteamTradeInjector();

// Для отладки в консоли разработчика
window.steamInjector = steamInjector;