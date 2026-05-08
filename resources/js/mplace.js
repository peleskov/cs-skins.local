import './shared/bootstrap';
import { createApp } from 'vue';
import Toast from "vue-toastification";
import { toastOptions, initializeGlobalToast, initializeToastWebSocket } from './shared/toast';

// Глобальная инициализация валют
import './shared/utils/currency-init.js';

// Mplace Vue компоненты
import Marketplace from './mplace/components/Marketplace.vue';
import Auctions from './mplace/components/Auctions.vue';
import SkinDetails from './mplace/components/SkinDetails.vue';
import Profile from './mplace/components/Profile.vue';
import Cart from './mplace/components/Cart.vue';
import Checkout from './mplace/components/Checkout.vue';
import CartButton from './mplace/components/CartButton.vue';
import Header from './mplace/components/Header.vue';
import FavoriteButton from './mplace/components/FavoriteButton.vue';
import Chat from './mplace/components/Chat.vue';
import CarouselWinner from './cases/components/CarouselWinner.vue';

// Кастомные скрипты шаблона mplace
import './mplace/scripts/footer-accordion.js';
import './mplace/scripts/loader.js';
import './mplace/scripts/custom-swiper.js';
import './mplace/scripts/script.js';

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
            currentUser: auctionsElement.dataset.currentUser !== 'null' ? JSON.parse(auctionsElement.dataset.currentUser) : null
        });
        app.mount('#auctions-app');
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

    // Header компонент
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
            setTimeout(initializeHeader, 100);
        }
    }

    initializeHeader();

    // CartButton и FavoriteButton компоненты
    if (!marketplaceElement) {
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

    // Chat компонент
    const chatElement = document.getElementById('chat-app');
    if (chatElement) {
        const app = createApp(Chat);
        app.use(Toast, toastOptions);
        app.mount('#chat-app');
    }

    // CarouselWinner компонент (лента дропов)
    const carouselWinnerElement = document.getElementById('carousel-winner-app');
    if (carouselWinnerElement) {
        const app = createApp(CarouselWinner);
        app.mount('#carousel-winner-app');
    }
});
