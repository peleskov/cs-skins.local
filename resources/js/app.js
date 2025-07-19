import './bootstrap';
import { createApp } from 'vue';
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";
import { useToast } from "vue-toastification";
import Marketplace from './components/Marketplace.vue';
import SkinDetails from './components/SkinDetails.vue';
import Profile from './components/Profile.vue';
import Cart from './components/Cart.vue';
import CartButton from './components/CartButton.vue';
import Header from './components/Header.vue';
import FavoriteButton from './components/FavoriteButton.vue';

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
    draggable: true,
    draggablePercent: 0.6,
    showCloseButtonOnHover: false,
    hideProgressBar: false,
    closeButton: "button",
    icon: true,
    rtl: false,
    maxToasts: 5,
    newestOnTop: true
};

// Создаем глобальный экземпляр toast
const initializeGlobalToast = () => {
    const app = createApp({
        name: 'ToastApp',
        setup() {
            const toast = useToast();
            // Делаем toast доступным глобально
            window.toast = toast;
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

// Инициализация Vue компонентов
document.addEventListener('DOMContentLoaded', () => {
    // Инициализируем глобальный toast первым
    initializeGlobalToast();
    // Marketplace компонент
    const marketplaceElement = document.getElementById('marketplace-app');
    if (marketplaceElement) {
        const app = createApp(Marketplace, {
            initialListings: JSON.parse(marketplaceElement.dataset.listings || '[]'),
            initialTotal: parseInt(marketplaceElement.dataset.total || '0'),
            initialHasMore: marketplaceElement.dataset.hasMore === 'true'
        });
        app.mount('#marketplace-app');
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
            telegramBotName: profileElement.dataset.telegramBotName || ''
        });
        
        // Устанавливаем глобальную переменную для Telegram виджета
        window.telegramBotName = profileElement.dataset.telegramBotName || '';
        
        app.mount('#profile-app');
    }
    
    // Cart компонент
    const cartElement = document.getElementById('cart-app');
    if (cartElement) {
        const app = createApp(Cart);
        app.mount('#cart-app');
    }
    
    // Header компонент - с ожиданием появления элемента
    function initializeHeader() {
        const headerElement = document.getElementById('header-app');
        
        if (headerElement) {
            try {
                const user = headerElement.dataset.user !== 'null' ? JSON.parse(headerElement.dataset.user) : null;
                const routes = JSON.parse(headerElement.dataset.routes);
                const logoUrl = headerElement.dataset.logoUrl;
                
                
                const app = createApp(Header, {
                    user: user,
                    routes: routes,
                    logoUrl: logoUrl
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
});
