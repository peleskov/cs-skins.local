import './bootstrap';
import { createApp } from 'vue';
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";
import Marketplace from './components/Marketplace.vue';
import SkinDetails from './components/SkinDetails.vue';
import InventoryGrid from './components/InventoryGrid.vue';
import Profile from './components/Profile.vue';

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

// Инициализация Vue компонентов
document.addEventListener('DOMContentLoaded', () => {
    // Marketplace компонент
    const marketplaceElement = document.getElementById('marketplace-app');
    if (marketplaceElement) {
        const app = createApp(Marketplace, {
            initialListings: JSON.parse(marketplaceElement.dataset.listings || '[]'),
            initialTotal: parseInt(marketplaceElement.dataset.total || '0'),
            initialHasMore: marketplaceElement.dataset.hasMore === 'true'
        });
        app.use(Toast, toastOptions);
        app.mount('#marketplace-app');
    }
    
    // SkinDetails компонент
    const skinDetailsElement = document.getElementById('skin-details-app');
    if (skinDetailsElement) {
        const listingId = parseInt(skinDetailsElement.dataset.listingId);
        const app = createApp(SkinDetails, {
            listingId: listingId
        });
        app.use(Toast, toastOptions);
        app.mount('#skin-details-app');
    }
    
    // InventoryGrid компонент (standalone)
    const inventoryElement = document.getElementById('inventory-app');
    if (inventoryElement) {
        const app = createApp(InventoryGrid, {
            initialItems: JSON.parse(inventoryElement.dataset.items || '[]'),
            initialStats: JSON.parse(inventoryElement.dataset.stats || '{}')
        });
        app.use(Toast, toastOptions);
        app.mount('#inventory-app');
    }
    
    // Profile компонент
    const profileElement = document.getElementById('profile-app');
    if (profileElement) {
        const app = createApp(Profile, {
            initialClient: JSON.parse(profileElement.dataset.client || '{}'),
            telegramBotName: profileElement.dataset.telegramBotName || ''
        });
        app.use(Toast, toastOptions);
        
        // Устанавливаем глобальную переменную для Telegram виджета
        window.telegramBotName = profileElement.dataset.telegramBotName || '';
        
        app.mount('#profile-app');
    }
});
