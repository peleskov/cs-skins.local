import './bootstrap';
import { createApp } from 'vue';
import Marketplace from './components/Marketplace.vue';

// Кастомные скрипты шаблона
import './footer-accordion.js';
import './loader.js';
import './custom-swiper.js';
import './script.js';

// Инициализация Vue компонентов
document.addEventListener('DOMContentLoaded', () => {
    const marketplaceElement = document.getElementById('marketplace-app');
    if (marketplaceElement) {
        const app = createApp(Marketplace, {
            initialListings: JSON.parse(marketplaceElement.dataset.listings || '[]'),
            initialTotal: parseInt(marketplaceElement.dataset.total || '0'),
            initialHasMore: marketplaceElement.dataset.hasMore === 'true'
        });
        app.mount('#marketplace-app');
    }
});
