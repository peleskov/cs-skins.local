const TradeOfferManager = require('steam-tradeoffer-manager');
const SteamCommunity = require('steamcommunity');

// Создаем экземпляры
const community = new SteamCommunity();
const manager = new TradeOfferManager({
    steam: community,
    domain: 'localhost',
    language: 'en'
});

// ID трейда для проверки
const tradeOfferId = '8314206824';

console.log('=== Node.js Test: Checking Trade Offer Status ===');
console.log(`Trade Offer ID: ${tradeOfferId}`);
console.log('');

// Функция для проверки статуса
function checkTradeStatus() {
    console.log('🔍 Checking trade offer status...');
    
    manager.getOffer(tradeOfferId, (err, offer) => {
        if (err) {
            console.error('❌ Error getting trade offer:', err.message);
            
            // Выводим детали ошибки
            if (err.eresult) {
                console.log(`   EResult: ${err.eresult}`);
            }
            if (err.cause) {
                console.log(`   Cause: ${err.cause}`);
            }
            
            return;
        }
        
        if (!offer) {
            console.log('❌ Trade offer not found');
            return;
        }
        
        console.log('✅ Trade offer found!');
        console.log(`   State: ${offer.state} (${getStateText(offer.state)})`);
        console.log(`   Created: ${offer.created}`);
        console.log(`   Updated: ${offer.updated}`);
        console.log(`   Partner Steam ID: ${offer.partner}`);
        
        // Выводим предметы
        if (offer.itemsToGive && offer.itemsToGive.length > 0) {
            console.log(`   Items to give: ${offer.itemsToGive.length}`);
            offer.itemsToGive.forEach((item, index) => {
                console.log(`     ${index + 1}. ${item.name || item.market_name} (Asset ID: ${item.assetid})`);
            });
        }
        
        if (offer.itemsToReceive && offer.itemsToReceive.length > 0) {
            console.log(`   Items to receive: ${offer.itemsToReceive.length}`);
        }
        
        console.log('');
        console.log('🔗 View in browser:');
        console.log(`   https://steamcommunity.com/tradeoffer/${tradeOfferId}/`);
    });
}

// Маппинг состояний трейда (из steam-tradeoffer-manager)
function getStateText(state) {
    const states = {
        1: 'Invalid',
        2: 'Active', 
        3: 'Accepted',
        4: 'Countered',
        5: 'Expired',
        6: 'Canceled',
        7: 'Declined',
        8: 'InvalidItems',
        9: 'CreatedNeedsConfirmation',
        10: 'CanceledBySecondFactor',
        11: 'InEscrow'
    };
    
    return states[state] || 'Unknown';
}

// Получаем куки из кеша Laravel (эмулируем)
const fs = require('fs');
const path = require('path');

console.log('🔍 Loading session from Laravel cache...');

// Попробуем загрузить сессию из файлового кеша или Redis
// В реальном приложении это было бы через Redis/Database
let sessionData = null;

try {
    // Для примера, установим куки вручную - в реальном приложении их нужно получить из кеша
    // Эти куки должны быть такими же, как в SteamSessionCache
    console.log('⚠️  Manual cookie setup needed');
    console.log('   Add your Steam cookies here:');
    
    // Пример установки кук (замените на реальные)
    const cookies = [
        'sessionid=31c51c7ab3b5201246cd13f6',
        'steamLoginSecure=76561198985797138%7C%7CeyAidHlwIjogIkpXVCIsICJhbGciOiAiRWREU0EiIH0.eyAiaXNzIjogInI6MDAwOV8yNkIyRUM3Q185NDlGNiIsICJzdWIiOiAiNzY1NjExOTg5ODU3OTcxMzgiLCAiYXVkIjogWyAid2ViOmNvbW11bml0eSIgXSwgImV4cCI6IDE3NTQwNDUwMTUsICJuYmYiOiAxNzQ1MzE4MDg5LCAiaWF0IjogMTc1Mzk1ODA4OSwgImp0aSI6ICIwMDBCXzI2QjJFQzc4XzA0NTE3IiwgIm9hdCI6IDE3NTM5NTgwODgsICJydF9leHAiOiAxNzcyNDA0MjY2LCAicGVyIjogMCwgImlwX3N1YmplY3QiOiAiOTEuMjQyLjE0OS4yNSIsICJpcF9jb25maXJtZXIiOiAiOTEuMjQyLjE0OS4yNSIgfQ.nF6oTOa5_THNvN2M7ZxGuBGkqTwAGbIFvK26BA4wZCPL4TVEIecwuQDxjafOaVPl9zsClnNbrzohyXS5xvPIDQ'
    ];
    
    if (cookies.length > 0 && cookies[0] !== '// sessionid=YOUR_SESSION_ID_HERE') {
        console.log('✅ Setting Steam cookies...');
        
        manager.setCookies(cookies, (err) => {
            if (err) {
                console.error('❌ Error setting cookies:', err.message);
                return;
            }
            
            console.log(`✅ Cookies set successfully! Logged in as: ${manager.steamID}`);
            
            community.on('sessionExpired', () => {
                console.log('❌ Steam session expired');
            });
            
            checkTradeStatus();
        });
    } else {
        console.log('');
        console.log('To test with real session:');
        console.log('1. Go to steamcommunity.com in your browser');
        console.log('2. Open Developer Tools (F12)');
        console.log('3. Go to Application/Storage -> Cookies -> steamcommunity.com');
        console.log('4. Copy sessionid and steamLoginSecure values');
        console.log('5. Update the cookies array in this script');
        console.log('');
        
        // Попробуем проверить без авторизации
        checkTradeStatus();
    }
} catch (error) {
    console.error('❌ Error loading session:', error.message);
    checkTradeStatus();
}

// Обработка ошибок
process.on('unhandledRejection', (reason, promise) => {
    console.error('Unhandled Rejection at:', promise, 'reason:', reason);
});

process.on('uncaughtException', (error) => {
    console.error('Uncaught Exception:', error);
    process.exit(1);
});