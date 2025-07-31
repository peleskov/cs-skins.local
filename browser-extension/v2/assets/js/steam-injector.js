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
    }
    return true;
});