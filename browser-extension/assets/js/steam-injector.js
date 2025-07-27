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
                // Выполняем создание трейда напрямую на текущей странице
                console.log('=== CREATE_TRADE_OFFER начало ===');
                console.log('URL страницы:', window.location.href);
                console.log('jQuery доступен:', typeof $ !== 'undefined');
                console.log('Order data:', message.order);
                
                try {
                    const sessionData = this.getSteamSessionData();
                    console.log('Session data:', sessionData);
                    
                    if (!sessionData.sessionId) {
                        throw new Error('Не удалось получить Steam session');
                    }
                    
                    const tradeData = this.prepareTradeData(message.order, sessionData);
                    console.log('Prepared trade data:', tradeData);
                    
                    const tradeOfferId = await this.sendTradeOffer(tradeData, message.order);
                    console.log('Trade offer created with ID:', tradeOfferId);
                    
                    sendResponse({
                        success: true,
                        tradeOfferId: tradeOfferId,
                        message: `Трейд-оффер #${tradeOfferId} отправлен покупателю`
                    });
                } catch (error) {
                    console.error('=== CREATE_TRADE_OFFER ошибка ===', error);
                    sendResponse({ 
                        success: false, 
                        error: error.message,
                        context: error.context || {}
                    });
                }
            },
            'GET_STEAM_SESSION': () => {
                sendResponse({
                    success: true,
                    sessionData: this.getSteamSessionData()
                });
            },
            'CREATE_TRADE_ON_PAGE': async () => {
                try {
                    const sessionData = this.getSteamSessionData();
                    if (!sessionData.sessionId) {
                        throw new Error('Не удалось получить Steam session');
                    }
                    
                    const tradeData = this.prepareTradeData(message.order, sessionData);
                    const tradeOfferId = await this.sendTradeOffer(tradeData, message.order);
                    
                    sendResponse({
                        success: true,
                        tradeOfferId: tradeOfferId,
                        message: `Трейд-оффер #${tradeOfferId} отправлен покупателю`
                    });
                } catch (error) {
                    sendResponse({ 
                        success: false, 
                        error: error.message,
                        context: error.context || {}
                    });
                }
            },
            'CHECK_STEAM_SESSION': () => {
                const sessionData = this.getSteamSessionData();
                const expectedSteamId = message.expectedSteamId;
                
                // Проверяем наличие Steam ID и соответствие
                const hasSteamId = !!sessionData.steamId;
                const isCorrectUser = !expectedSteamId || sessionData.steamId === expectedSteamId;
                
                let reason = 'not_logged_in';
                let isAuthenticated = false;
                
                if (!hasSteamId) {
                    reason = 'not_logged_in';
                } else if (expectedSteamId && !isCorrectUser) {
                    reason = 'wrong_user';
                } else {
                    reason = 'authenticated';
                    isAuthenticated = true;
                }
                
                sendResponse({
                    available: isAuthenticated,
                    steamId: sessionData.steamId,
                    loggedIn: hasSteamId,
                    reason: reason,
                    debug: {
                        url: window.location.href,
                        hasGSessionID: !!window.g_sessionID,
                        hasGSteamID: !!window.g_steamID,
                        hasCookies: document.cookie.includes('sessionid'),
                        sessionId: !!sessionData.sessionId,
                        steamId: !!sessionData.steamId,
                        expectedSteamId: expectedSteamId,
                        isCorrectUser: isCorrectUser
                    }
                });
            }
        };
        
        const handler = handlers[message.type];
        if (handler) {
            handler();
            return true;
        } else {
            sendResponse({ success: false, error: 'Unknown message type' });
            return false;
        }
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
        // Подготавливаем данные точно как в ручном запросе
        const tradeData = {
            sessionid: sessionData.sessionId,
            serverid: '1',
            partner: order.buyer.steam_id,
            tradeoffermessage: '', // Пустое сообщение как в ручном запросе
            json_tradeoffer: JSON.stringify({
                newversion: true,
                version: 4,
                me: {
                    assets: [{
                        appid: "730",
                        contextid: "2", 
                        amount: 1,
                        assetid: order.steam_asset_id
                    }],
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
    
    
    extractTradeToken(tradeUrl) {
        // Извлекаем токен из Trade URL
        const tokenMatch = tradeUrl.match(/token=([a-zA-Z0-9_-]+)/);
        return tokenMatch ? tokenMatch[1] : '';
    }
    
    async sendTradeOffer(tradeData, order) {
        return new Promise((resolve, reject) => {
            // Логируем все параметры перед отправкой
            console.log('Trade offer parameters:', {
                sessionid: tradeData.sessionid,
                partner: tradeData.partner,
                tradeoffermessage: tradeData.tradeoffermessage,
                json_tradeoffer: tradeData.json_tradeoffer,
                trade_offer_create_params: tradeData.trade_offer_create_params
            });
            
            // Проверяем наличие jQuery
            if (typeof $ === 'undefined' || !$.ajax) {
                reject(new Error('jQuery не найден на странице Steam'));
                return;
            }
            
            // Используем jQuery AJAX как в оригинальном Steam коде
            $.ajax({
                url: 'https://steamcommunity.com/tradeoffer/new/send',
                type: 'POST',
                data: tradeData,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                },
                success: function(result) {
                    console.log('Steam API jQuery response:', result);
                    
                    if (result.strError) {
                        const error = new Error(result.strError);
                        error.context = {
                            steamResponse: result,
                            requestData: {
                                partner: tradeData.partner,
                                assetId: JSON.parse(tradeData.json_tradeoffer).me.assets[0].assetid
                            }
                        };
                        reject(error);
                        return;
                    }
                    
                    if (!result.tradeofferid) {
                        const error = new Error('Не получен ID трейд-оффера');
                        error.context = {
                            steamResponse: result
                        };
                        reject(error);
                        return;
                    }
                    
                    resolve(result.tradeofferid);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Steam API jQuery error:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                    
                    const error = new Error(`AJAX Error: ${textStatus} - ${errorThrown}`);
                    error.context = {
                        httpStatus: jqXHR.status,
                        responseText: jqXHR.responseText,
                        partner: tradeData.partner,
                        assetId: tradeData.json_tradeoffer ? JSON.parse(tradeData.json_tradeoffer).me.assets[0].assetid : 'unknown'
                    };
                    reject(error);
                }
            });
        });
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