// CS-SKINS.pro Trading Assistant - Steam Content Script
class SteamTradeInjector {
    constructor() {
        this.isInitialized = false;
        this.pendingOrders = [];
        
        this.init();
    }
    
    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }
    
    setup() {
        if (!this.isSteamSite()) return;
        
        chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
            this.handleMessage(message, sender, sendResponse);
        });
        
        this.extractSteamSession();
        this.isInitialized = true;
    }
    
    isSteamSite() {
        return window.location.hostname === 'steamcommunity.com';
    }
    
    handleMessage(message, sender, sendResponse) {
        const handlers = {
            'CREATE_TRADE_OFFER': async () => {
                try {
                    const result = await this.createTradeOffer(message.order);
                    sendResponse(result);
                } catch (error) {
                    sendResponse({ success: false, error: error.message });
                }
            },
            'GET_STEAM_SESSION': () => {
                sendResponse({
                    success: true,
                    sessionData: this.getSteamSessionData()
                });
            }
        };
        
        const handler = handlers[message.type];
        if (handler) {
            handler();
            return true;
        } else {
            sendResponse({ success: false, error: 'Unknown message type' });
        }
    }
    
    async createTradeOffer(order) {
        try {
            const sessionData = this.getSteamSessionData();
            if (!sessionData.sessionId) {
                throw new Error('Не удалось получить Steam session');
            }
            
            const tradeData = this.prepareTradeData(order, sessionData);
            const tradeOfferId = await this.sendTradeOffer(tradeData);
            
            this.notifyTradeResult('TRADE_OFFER_CREATED', order.id, { tradeOfferId });
            
            return {
                success: true,
                tradeOfferId: tradeOfferId,
                message: `Трейд-оффер #${tradeOfferId} отправлен покупателю`
            };
        } catch (error) {
            this.notifyTradeResult('TRADE_OFFER_ERROR', order.id, { error: error.message });
            throw error;
        }
    }
    
    notifyTradeResult(type, orderId, data) {
        chrome.runtime.sendMessage({
            type,
            orderId,
            ...data
        });
    }
    
    getSteamSessionData() {
        return {
            sessionId: this.extractSessionId(),
            steamId: this.extractSteamId(),
            csrfToken: this.extractCSRFToken()
        };
    }
    
    extractSessionId() {
        // Проверяем глобальную переменную сначала
        if (window.g_sessionID) {
            return window.g_sessionID;
        }
        
        // Ищем в cookies
        const sessionCookie = document.cookie
            .split(';')
            .find(cookie => cookie.trim().startsWith('sessionid='));
            
        return sessionCookie ? sessionCookie.split('=')[1] : null;
    }
    
    extractSteamId() {
        if (window.g_steamID) {
            return window.g_steamID;
        }
        
        const profileMatch = window.location.href.match(/steamcommunity\.com\/(profiles|id)\/([^\/]+)/);
        return profileMatch ? profileMatch[2] : null;
    }
    
    extractCSRFToken() {
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }
        
        const scripts = Array.from(document.querySelectorAll('script'));
        for (const script of scripts) {
            const tokenMatch = script.textContent && script.textContent.match(/sessionid['"]\s*:\s*['"]([^'"]+)['"]/);
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
            throw error;
        }
    }
    
    extractSteamSession() {
        const sessionData = this.getSteamSessionData();
        
        if (sessionData.sessionId) {
            chrome.runtime.sendMessage({
                type: 'STEAM_SESSION_EXTRACTED',
                sessionData: sessionData
            });
        }
    }
    
}

// Инициализируем content script
const steamInjector = new SteamTradeInjector();

// Для отладки в консоли разработчика
window.steamInjector = steamInjector;