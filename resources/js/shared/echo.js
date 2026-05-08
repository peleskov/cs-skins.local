import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Функция для создания экземпляра Echo
export function createEcho() {
    return new Echo({
        broadcaster: 'pusher',  // Используем pusher вместо reverb
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT,
        wsPath: '/ws',  // Базовый путь для проксирования
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        encrypted: import.meta.env.VITE_REVERB_SCHEME === 'https',
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        // Дополнительные опции для совместимости с Reverb
        cluster: false,
        auth: {
            headers: {}
        }
    });
}

// Синглтон, чтобы переиспользовать одно соединение в рамках страницы
let sharedEcho = null;
export function getEcho() {
	if (!sharedEcho) sharedEcho = createEcho();
	return sharedEcho;
}

// Экспортируем классы для использования в компонентах
export { Echo, Pusher };