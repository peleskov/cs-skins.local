import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Функция для создания экземпляра Echo
export function createEcho() {
    return new Echo({
        broadcaster: 'pusher',  // Используем pusher вместо reverb
        key: 'cs-skins-key',
        wsHost: window.location.hostname,
        wsPort: 443,
        wsPath: '/ws',  // Базовый путь для проксирования
        forceTLS: true,
        encrypted: true,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        // Дополнительные опции для совместимости с Reverb
        cluster: false,
        auth: {
            headers: {}
        }
    });
}

// Экспортируем классы для использования в компонентах
export { Echo, Pusher };