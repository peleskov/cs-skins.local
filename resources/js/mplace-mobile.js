import { createApp } from 'vue';
import MobileHeader from './mplace/components/MobileHeader.vue';
import MobileBottomNav from './mplace/components/MobileBottomNav.vue';
import MobileHomeFilters from './mplace/components/MobileHomeFilters.vue';

document.addEventListener('DOMContentLoaded', () => {
    const mobileHeaderElement = document.getElementById('mobile-header-app');
    if (mobileHeaderElement) {
        try {
            const user = mobileHeaderElement.dataset.user !== 'null' ? JSON.parse(mobileHeaderElement.dataset.user) : null;
            const routes = JSON.parse(mobileHeaderElement.dataset.routes);
            const app = createApp(MobileHeader, {
                user: user,
                routes: routes,
                logoUrl: mobileHeaderElement.dataset.logoUrl,
                initialCartCount: parseInt(mobileHeaderElement.dataset.cartCount || '0'),
                online: mobileHeaderElement.dataset.online ? parseInt(mobileHeaderElement.dataset.online) : null
            });
            app.mount('#mobile-header-app');
        } catch (error) {
            console.error('Error mounting mobile header:', error);
        }
    }

    const mobileHomeFiltersElement = document.getElementById('mobile-home-filters-app');
    if (mobileHomeFiltersElement) {
        try {
            const app = createApp(MobileHomeFilters, {
                marketplaceUrl: mobileHomeFiltersElement.dataset.marketplaceUrl
            });
            app.mount('#mobile-home-filters-app');
        } catch (error) {
            console.error('Error mounting mobile home filters:', error);
        }
    }

    const mobileBottomNavElement = document.getElementById('mobile-bottom-nav-app');
    if (mobileBottomNavElement) {
        try {
            const user = mobileBottomNavElement.dataset.user !== 'null' ? JSON.parse(mobileBottomNavElement.dataset.user) : null;
            const routes = JSON.parse(mobileBottomNavElement.dataset.routes);
            const app = createApp(MobileBottomNav, {
                user: user,
                routes: routes,
                initialCartCount: parseInt(mobileBottomNavElement.dataset.cartCount || '0')
            });
            app.mount('#mobile-bottom-nav-app');
        } catch (error) {
            console.error('Error mounting mobile bottom nav:', error);
        }
    }
});
