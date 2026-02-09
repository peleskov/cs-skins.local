import { createApp } from 'vue';
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";
import { useToast } from "vue-toastification";

// Настройки для vue-toastification
export const toastOptions = {
    position: "bottom-right",
    timeout: 8000,
    closeOnClick: true,
    pauseOnFocusLoss: true,
    pauseOnHover: true,
    draggable: false,
    draggablePercent: 0.6,
    showCloseButtonOnHover: false,
    hideProgressBar: false,
    closeButton: "button",
    icon: true,
    rtl: false,
    maxToasts: 5,
    newestOnTop: true
};

// Звуковые файлы для уведомлений
const soundVersion = 2;
const sounds = {
    success: new Audio(`/sounds/success.mp3?v=${soundVersion}`),
    error: new Audio(`/sounds/error.mp3?v=${soundVersion}`),
    info: new Audio(`/sounds/info.mp3?v=${soundVersion}`),
    warning: new Audio(`/sounds/warning.mp3?v=${soundVersion}`)
};

// Получение настроек звука из localStorage
const getSoundSettings = () => {
    const defaults = {
        saleTransfer: true,
        purchaseReceive: true,
        success: false,
        failed: false,
        auction: true,
        other: true
    };
    try {
        const saved = localStorage.getItem('soundSettings');
        if (saved) {
            return { ...defaults, ...JSON.parse(saved) };
        }
    } catch (e) {}
    return defaults;
};

// Флаг для отслеживания первого взаимодействия пользователя
let userInteracted = false;

// Функция инициализации звуков после взаимодействия пользователя
const initSoundsAfterInteraction = () => {
    if (userInteracted) return;
    Object.values(sounds).forEach(sound => {
        if (sound) {
            sound.load();
        }
    });
    userInteracted = true;
};

// Добавляем обработчики для первого взаимодействия
document.addEventListener('click', initSoundsAfterInteraction, { once: true });
document.addEventListener('keydown', initSoundsAfterInteraction, { once: true });
document.addEventListener('touchstart', initSoundsAfterInteraction, { once: true });

// Функция воспроизведения звука
export const playNotificationSound = (type, noticeType = null) => {
    const settings = getSoundSettings();
    const settingKey = noticeType || 'other';
    if (!settings[settingKey]) return;

    const sound = sounds[type];
    if (sound) {
        sound.volume = parseFloat(localStorage.getItem('soundVolume') || '0.5');
        sound.play().catch(() => {});
    }
};

// Проверка включены ли toast уведомления
export const isToastEnabled = () => {
    const headerElement = document.querySelector('#header-app');
    const userData = headerElement?.dataset?.user;

    if (!userData || userData === 'null') {
        return true;
    }

    try {
        const user = JSON.parse(userData);
        const settings = user.notification_settings;

        if (!settings || !Array.isArray(settings)) {
            return true;
        }

        return settings.includes('toast');
    } catch {
        return true;
    }
};

// Создаем глобальный экземпляр toast
export const initializeGlobalToast = () => {
    const app = createApp({
        name: 'ToastApp',
        setup() {
            const originalToast = useToast();

            const toastWithSound = {
                success: (message, options = {}) => {
                    if (options.sound !== false) playNotificationSound('success', options.noticeType);
                    if (!isToastEnabled() && options.force !== true) return;
                    return originalToast.success(message, options);
                },
                error: (message, options = {}) => {
                    if (options.sound !== false) playNotificationSound('error', options.noticeType);
                    return originalToast.error(message, options);
                },
                info: (message, options = {}) => {
                    if (options.sound !== false) playNotificationSound('info', options.noticeType);
                    if (!isToastEnabled() && options.force !== true) return;
                    return originalToast.info(message, options);
                },
                warning: (message, options = {}) => {
                    if (options.sound !== false) playNotificationSound('warning', options.noticeType);
                    if (!isToastEnabled() && options.force !== true) return;
                    return originalToast.warning(message, options);
                },
                clear: originalToast.clear,
                updateDefaults: originalToast.updateDefaults
            };

            window.toast = toastWithSound;
            return {};
        },
        template: '<div></div>'
    });

    app.use(Toast, toastOptions);

    const container = document.createElement('div');
    container.style.display = 'none';
    document.body.appendChild(container);
    app.mount(container);
};

// Глобальный WebSocket слушатель для toast уведомлений
export const initializeToastWebSocket = () => {
    const headerElement = document.querySelector('#header-app');
    const userData = headerElement?.dataset?.user;

    if (!userData || userData === 'null') return;

    try {
        const user = JSON.parse(userData);
        if (!user.id) return;

        import('./echo').then(({ createEcho }) => {
            const echo = createEcho();

            echo.private(`user.${user.id}`)
                .listen('.toast.notification', (e) => {
                    if (window.toast && e.message) {
                        const toastType = e.type || 'info';
                        window.toast[toastType](e.message, {
                            noticeType: e.noticeType || 'other'
                        });
                    }
                });

        }).catch(error => {});

    } catch (error) {}
};
