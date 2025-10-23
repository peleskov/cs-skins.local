chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.type === 'GET_STEAM_SESSION') {
        const sessionData = { sessionid: null, steamLoginSecure: null, steamid: null };
        
        // Извлечение Session ID (как в v1)
        if (window.g_sessionID) {
            sessionData.sessionid = window.g_sessionID;
        } else {
            const sessionCookie = document.cookie
                .split(';')
                .find(cookie => cookie.trim().startsWith('sessionid='));
            sessionData.sessionid = sessionCookie ? sessionCookie.split('=')[1] : null;
        }
        
        // Извлечение Steam ID (как в v1)
        if (window.g_steamID) {
            sessionData.steamid = window.g_steamID;
        } else {
            // Пробуем извлечь из steamLoginSecure cookie
            const steamLoginCookie = document.cookie
                .split(';')
                .find(cookie => cookie.trim().startsWith('steamLoginSecure='));
            if (steamLoginCookie) {
                sessionData.steamLoginSecure = steamLoginCookie.split('=')[1];
                const steamidMatch = sessionData.steamLoginSecure.match(/^(\d+)/);
                if (steamidMatch) sessionData.steamid = steamidMatch[1];
            }
            
            // Если не получили из cookie, пробуем из URL (как в v1)
            if (!sessionData.steamid) {
                const profileMatch = window.location.href.match(/steamcommunity\.com\/(profiles|id)\/([^\/]+)/);
                sessionData.steamid = profileMatch ? profileMatch[2] : null;
            }
        }
        
        sendResponse(sessionData);
    } else if (request.type === 'GET_TRADE_OFFERS') {
        getTradeOffersFromSteam(request.steamLoginSecure).then(trades => {
            sendResponse(trades);
        }).catch(error => {
            sendResponse(null);
        });
        return true;
    }
    return true;
});

async function getTradeOffersFromSteam(steamLoginSecure) {
    try {
        //console.log('🔍 Начинаем получение трейдов...');
        //console.log('🌐 Текущая страница:', window.location.href);
        
        if (!steamLoginSecure) {
            //console.log('❌ steamLoginSecure cookie не передан из service worker');
            throw new Error('steamLoginSecure cookie не найден');
        }

        //console.log('✅ steamLoginSecure cookie получен из service worker');

        const cookieValue = decodeURIComponent(steamLoginSecure);
        const accessToken = cookieValue.split('||')[1];
        
        if (!accessToken) {
            //console.log('❌ access_token не найден в cookie');
            throw new Error('access_token не найден в cookie');
        }
        
        //console.log('✅ access_token извлечен:', accessToken.substring(0, 10) + '...');
        
        const params = new URLSearchParams({
            access_token: accessToken,
            get_sent_offers: '1',
            active_only: '1',
            language: 'english'
        });
        
        const url = `https://api.steampowered.com/IEconService/GetTradeOffers/v1/?${params}`;
        //console.log('🌐 Делаем запрос к Steam API...');
        
        const response = await fetch(url);
        
        if (!response.ok) {
            //console.log('❌ HTTP ошибка:', response.status, response.statusText);
            throw new Error(`HTTP error ${response.status}`);
        }
        
        //console.log('✅ Ответ получен, парсим JSON...');
        const data = await response.json();
        //console.log('📦 Полный ответ Steam API:', data);
        
        const offers = data.response.trade_offers_sent || [];
        //console.log('📋 Количество трейдов:', offers.length);
        
        return offers;
    } catch (error) {
        console.error('💥 Ошибка в getTradeOffersFromSteam:', error);
        throw error;
    }
}