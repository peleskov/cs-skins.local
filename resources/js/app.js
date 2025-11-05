import './bootstrap';
import { createApp } from 'vue';
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";
import { useToast } from "vue-toastification";

// Глобальная инициализация валют
import './utils/currency-init.js';
import Marketplace from './components/Marketplace.vue';
import Auctions from './components/Auctions.vue';
import SkinDetails from './components/SkinDetails.vue';
import Profile from './components/Profile.vue';
import Cart from './components/Cart.vue';
import Checkout from './components/Checkout.vue';
import CartButton from './components/CartButton.vue';
import Header from './components/Header.vue';
import FavoriteButton from './components/FavoriteButton.vue';
import Cases from './components/Cases.vue';
import CaseDetails from './components/CaseDetails.vue';
import Chat from './components/Chat.vue';

// Утилиты CSRF уже импортируются в компонентах где нужно

// Кастомные скрипты шаблона
import './footer-accordion.js';
import './loader.js';
import './custom-swiper.js';
import './script.js';

// Настройки для vue-toastification
const toastOptions = {
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
const sounds = {
    success: new Audio('/sounds/success.mp3'),
    error: new Audio('/sounds/error.mp3'),
    info: new Audio('/sounds/info.mp3'),
    warning: new Audio('/sounds/warning.mp3')
};

// Настройки звука
const soundSettings = {
    enabled: localStorage.getItem('soundEnabled') !== 'false',
    volume: parseFloat(localStorage.getItem('soundVolume') || '0.5')
};

// Флаг для отслеживания первого взаимодействия пользователя
let userInteracted = false;

// Функция инициализации звуков после взаимодействия пользователя
const initSoundsAfterInteraction = () => {
    if (userInteracted) return;

    // Пробуем предзагрузить звуки
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
const playNotificationSound = (type) => {
    if (!soundSettings.enabled) return;

    const sound = sounds[type];
    if (sound) {
        sound.volume = soundSettings.volume;
        sound.play().catch(() => {
            // Тихо игнорируем любые ошибки воспроизведения
        });
    }
};

// Создаем глобальный экземпляр toast
const initializeGlobalToast = () => {
    const app = createApp({
        name: 'ToastApp',
        setup() {
            const originalToast = useToast();

            // Расширяем toast со звуками
            const toastWithSound = {
                success: (message, options = {}) => {
                    if (options.sound !== false) playNotificationSound('success');
                    return originalToast.success(message, options);
                },
                error: (message, options = {}) => {
                    if (options.sound !== false) playNotificationSound('error');
                    return originalToast.error(message, options);
                },
                info: (message, options = {}) => {
                    if (options.sound !== false) playNotificationSound('info');
                    return originalToast.info(message, options);
                },
                warning: (message, options = {}) => {
                    if (options.sound !== false) playNotificationSound('warning');
                    return originalToast.warning(message, options);
                },
                // Сохраняем оригинальные методы
                clear: originalToast.clear,
                updateDefaults: originalToast.updateDefaults
            };

            // Делаем toast доступным глобально
            window.toast = toastWithSound;
            return {};
        },
        template: '<div></div>'
    });

    app.use(Toast, toastOptions);

    // Создаем скрытый элемент для монтирования
    const container = document.createElement('div');
    container.style.display = 'none';
    document.body.appendChild(container);
    app.mount(container);
};

// Глобальный WebSocket слушатель для toast уведомлений
const initializeToastWebSocket = () => {
    // Проверяем есть ли авторизованный пользователь
    const headerElement = document.querySelector('#header-app');
    const userData = headerElement?.dataset?.user;

    if (!userData || userData === 'null') return;

    try {
        const user = JSON.parse(userData);
        if (!user.id) return;

        // Импортируем Echo
        import('./echo').then(({ createEcho }) => {
            const echo = createEcho();

            // Подписываемся на приватный канал пользователя для toast уведомлений
            echo.private(`user.${user.id}`)
                .listen('.toast.notification', (e) => {
                    // Показываем уведомление через глобальный toast
                    if (window.toast && e.message) {
                        const toastType = e.type || 'info';
                        window.toast[toastType](e.message, {
                            sound: true // Включаем звук
                        });
                    }
                });

        }).catch(error => {
            // Тихо игнорируем ошибки подключения Echo
        });

    } catch (error) {
        // Тихо игнорируем ошибки парсинга пользовательских данных
    }
};

// Инициализация Vue компонентов
document.addEventListener('DOMContentLoaded', () => {
    // Инициализируем глобальный toast первым
    initializeGlobalToast();

    // Инициализируем WebSocket для toast уведомлений
    initializeToastWebSocket();
    // Marketplace компонент
    const marketplaceElement = document.getElementById('marketplace-app');
    if (marketplaceElement) {
        const app = createApp(Marketplace, {
            initialListings: JSON.parse(marketplaceElement.dataset.listings || '[]'),
            initialTotal: parseInt(marketplaceElement.dataset.total || '0'),
            initialHasMore: marketplaceElement.dataset.hasMore === 'true',
            initialSeller: marketplaceElement.dataset.seller !== 'null' ? JSON.parse(marketplaceElement.dataset.seller) : null,
            initialSellerStats: marketplaceElement.dataset.sellerStats !== 'null' ? JSON.parse(marketplaceElement.dataset.sellerStats) : null
        });
        app.mount('#marketplace-app');
    }
    
    // Auctions компонент  
    const auctionsElement = document.getElementById('auctions-app');
    if (auctionsElement) {
        const app = createApp(Auctions, {
            initialAuctions: JSON.parse(auctionsElement.dataset.auctions || '[]'),
            initialTotal: parseInt(auctionsElement.dataset.total || '0'),
            initialHasMore: auctionsElement.dataset.hasMore === 'true',
            currentUser: auctionsElement.dataset.currentUser !== 'null' ? JSON.parse(auctionsElement.dataset.currentUser) : null
        });
        app.mount('#auctions-app');
    }
    
    // Cases компонент
    const casesElement = document.getElementById('cases-app');
    if (casesElement) {
        const app = createApp(Cases, {
            initialCases: JSON.parse(casesElement.dataset.cases || '[]'),
            user: casesElement.dataset.user !== 'null' ? JSON.parse(casesElement.dataset.user) : null
        });
        app.mount('#cases-app');
    }
    
    // CaseDetails компонент
    const caseDetailsElement = document.getElementById('case-details-app');
    if (caseDetailsElement) {
        const app = createApp(CaseDetails, {
            initialCase: JSON.parse(caseDetailsElement.dataset.case || '{}'),
            caseSlug: caseDetailsElement.dataset.caseSlug || ''
        });
        app.mount('#case-details-app');
    }
    
    // SkinDetails компонент
    const skinDetailsElement = document.getElementById('skin-details-app');
    if (skinDetailsElement) {
        const listingId = parseInt(skinDetailsElement.dataset.listingId);
        const app = createApp(SkinDetails, {
            listingId: listingId
        });
        app.mount('#skin-details-app');
    }
    
    // Profile компонент
    const profileElement = document.getElementById('profile-app');
    if (profileElement) {
        const app = createApp(Profile, {
            initialClient: JSON.parse(profileElement.dataset.client || '{}'),
            telegramBotName: profileElement.dataset.telegramBotName || '',
            depositSettings: JSON.parse(profileElement.dataset.depositSettings || '{}')
        });

        // Устанавливаем глобальную переменную для Telegram виджета
        window.telegramBotName = profileElement.dataset.telegramBotName || '';

        app.mount('#profile-app');
    }
    
    // Cart компонент
    const cartElement = document.getElementById('cart-app');
    if (cartElement) {
        const user = cartElement.dataset.user !== 'null' ? JSON.parse(cartElement.dataset.user) : null;
        const routes = JSON.parse(cartElement.dataset.routes);
        const app = createApp(Cart, {
            user: user,
            routes: routes
        });
        app.mount('#cart-app');
    }
    
    // Checkout компонент
    const checkoutElement = document.getElementById('checkout-app');
    if (checkoutElement) {
        const app = createApp(Checkout);
        app.mount('#checkout-app');
    }
    
    // Header компонент - с ожиданием появления элемента
    function initializeHeader() {
        const headerElement = document.getElementById('header-app');
        
        if (headerElement) {
            try {
                const user = headerElement.dataset.user !== 'null' ? JSON.parse(headerElement.dataset.user) : null;
                const routes = JSON.parse(headerElement.dataset.routes);
                const logoUrl = headerElement.dataset.logoUrl;
                const cartCount = parseInt(headerElement.dataset.cartCount || '0');
                const favoritesCount = parseInt(headerElement.dataset.favoritesCount || '0');
                const extensionDownloadUrl = headerElement.dataset.extensionDownloadUrl;

                const app = createApp(Header, {
                    user: user,
                    routes: routes,
                    logoUrl: logoUrl,
                    initialCartCount: cartCount,
                    initialFavoritesCount: favoritesCount,
                    extensionDownloadUrl: extensionDownloadUrl
                });
                app.mount('#header-app');
                
            } catch (error) {
                console.error('Error mounting header:', error);
            }
        } else {
            // Если элемент не найден, попробуем через 100ms
            setTimeout(initializeHeader, 100);
        }
    }
    
    initializeHeader();
    
    // CartButton и FavoriteButton компоненты инициализируются только если это НЕ страница маркетплейса
    // На странице маркетплейса они инициализируются самим Marketplace.vue компонентом
    if (!marketplaceElement) {
        // CartButton компоненты (может быть несколько на странице)
        const cartButtons = document.querySelectorAll('[data-cart-button]');
        cartButtons.forEach(button => {
            const listingId = parseInt(button.dataset.listingId);
            const size = button.dataset.size || 'normal';
            const variant = button.dataset.variant || 'primary';
            const initialIsInCart = button.dataset.isInCart === 'true';
            
            if (listingId) {
                const app = createApp(CartButton, {
                    listingId: listingId,
                    size: size,
                    variant: variant,
                    initialIsInCart: initialIsInCart
                });
                app.mount(button);
            }
        });
        
        // FavoriteButton компоненты (может быть несколько на странице)
        const favoriteButtons = document.querySelectorAll('[data-favorite-button]');
        favoriteButtons.forEach(button => {
            const listingId = parseInt(button.dataset.listingId);
            
            if (listingId) {
                const app = createApp(FavoriteButton, {
                    listingId: listingId
                });
                app.mount(button);
            }
        });
    }

    // Chat компонент - инициализируем для авторизованных пользователей
    const chatElement = document.getElementById('chat-app');
    if (chatElement) {
        const app = createApp(Chat);
        app.use(Toast, toastOptions);
        app.mount('#chat-app');
    }
});
