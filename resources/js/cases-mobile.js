import { createApp } from 'vue';
import MobileHeader from './mplace/components/MobileHeader.vue';
import MobileBottomNav from './mplace/components/MobileBottomNav.vue';
import MobileBalance from './cases/components/MobileBalance.vue';

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
                online: parseInt(mobileHeaderElement.dataset.online || '0'),
                avatarRoute: 'caseInventory'
            });
            app.mount('#mobile-header-app');
        } catch (error) {
            console.error('Error mounting mobile header:', error);
        }
    }

    const balanceEl = document.getElementById('cases-mobile-balance-app');
    if (balanceEl) {
        try {
            const user = JSON.parse(balanceEl.dataset.user);
            const routes = JSON.parse(balanceEl.dataset.routes);
            createApp(MobileBalance, { user, routes }).mount('#cases-mobile-balance-app');
        } catch (e) {
            console.error('Error mounting cases mobile balance:', e);
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
                initialCartCount: parseInt(mobileBottomNavElement.dataset.cartCount || '0'),
                items: [
                    { key: 'marketplace', label: 'МАРКЕТ', icon: 'm-ico-mplace', route: 'marketplace' },
                    { key: 'cases', label: 'КЕЙСЫ', icon: 'm-ico-cases', route: 'cases', requiresAuth: true },
                    { key: 'upgrade', label: 'АПГРЕЙД', icon: 'm-ico-upgrade', route: 'upgrade', requiresAuth: true },
                    { key: 'socials', label: 'СОЦСЕТИ', icon: 'm-ico-socials', route: 'faq' },
                    { key: 'faq', label: 'FAQ', icon: 'm-ico-faq', route: 'faq' }
                ]
            });
            app.mount('#mobile-bottom-nav-app');
        } catch (error) {
            console.error('Error mounting mobile bottom nav:', error);
        }
    }
});
