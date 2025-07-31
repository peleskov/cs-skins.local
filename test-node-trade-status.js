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

// Проверяем, нужно ли логиниться
if (!community.steamID) {
    console.log('⚠️  Not logged in to Steam');
    console.log('   This script needs Steam session cookies to work');
    console.log('   You would normally use steamcommunity.login() with credentials');
    console.log('');
    console.log('   For testing, you can manually set cookies from browser:');
    console.log('   1. Go to steamcommunity.com in browser');
    console.log('   2. Copy sessionid and steamLoginSecure cookies');
    console.log('   3. Use community.setCookies() method');
    console.log('');
    
    // Попробуем все равно проверить (может быть публичная информация)
    checkTradeStatus();
} else {
    console.log(`✅ Logged in as: ${community.steamID}`);
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