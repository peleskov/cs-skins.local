<template>
    <div class="container-fluid">
        <div class="row justify-content-between align-items-stretch">
            <div class="col-auto">
                <a :href="routes.home" class="logo my-3 mx-xxl-3">
                    <img :src="logoUrl" alt="logo" class="d-none d-lg-block">
                    <img :src="logoIco" alt="logo" class="d-lg-none">
                </a>
            </div>
            <div class="col-auto p-0">
                <div class="h-100 divider mx-auto"></div>
            </div>
            <div class="col d-flex align-items-center">
                <ul class="navbar-nav flex-row ms-3">
                    <template v-for="(item, key) in mainNavigation" :key="key">
                        <li v-if="!item.auth_required || user" class="nav-item">
                            <a class="nav-link" :class="{ 'active': isActive(item.route) }"
                                :href="getNavigationUrl(item.route)">{{ item.title }}</a>
                        </li>
                    </template>
                </ul>
            </div>
            <div class="col-auto d-flex align-items-center gap-2 gap-lg-3">
                <div class="col-auto d-flex gap-2 gap-lg-3">
                    <LanguageSelector />
                    <CurrencySelector />
                </div>
                <div class="col-auto">
                    <ul class="list-group list-group-horizontal balances">
                        <li class="list-group-item d-flex align-items-center gap-1" data-bs-toggle="tooltip"
                            data-bs-title="Бонусный баланс">
                            <span class="ico dollar"></span>
                            <span class="balance-amount" v-html="formatPrice(bonusBalance, 'RUB', true)"></span>
                        </li>
                        <li class="list-group-item">
                            <span class="divider"></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center gap-1">
                            <span class="ico ruble"></span>
                            <span class="balance-amount" v-html="formatPrice(mainBalance, 'RUB', true)"></span>
                        </li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a :href="routes.profile + '#balance'" class="btn-add_balance"></a>
                </div>
                <div class="col-auto">
                    <a :href="routes.caseInventory" class="avatar">
                        <img class="img-fluid" :src="user.steam_avatar" alt="profile">
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { formatPrice } from '../../shared/utils/helpers';
import CurrencySelector from '../../shared/components/CurrencySelector.vue';
import LanguageSelector from '../../shared/components/LanguageSelector.vue';

export default {
    name: 'Header',
    components: {
        CurrencySelector,
        LanguageSelector
    },
    setup() {
        return { formatPrice };
    },
    props: {
        user: {
            type: Object,
            default: null
        },
        routes: {
            type: Object,
            required: true
        },
        logoUrl: {
            type: String,
            required: true
        },
        logoIco: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            cartCount: this.initialCartCount,
            isLoading: false,
            tooltips: [],
            mainBalance: this.user?.balance || 0,
            bonusBalance: this.user?.bonus_balance || 0
        }
    },
    computed: {
        mainNavigation() {
            // Кастомное меню для cases (только нужные пункты)
            return {
                cases: { title: 'Кейсы', route: 'cases', order: 1 },
                marketplace: { title: 'Маркетплейс', route: 'marketplace', order: 2 },
                upgrade: { title: 'Апгрейд', route: 'upgrade', order: 3 },
                socials: { title: 'Соцсети', route: '#', order: 4 },
                faq: { title: 'FAQ', route: 'faq', order: 5 }
            };
        }
    },
    methods: {
        translate(key) {
            // Разбираем ключ вида 'cart.empty' на ['cart', 'empty']
            const keys = key.split('.');
            let translation = window.translations;

            // Проходим по всем уровням вложенности
            for (const k of keys) {
                if (translation && typeof translation === 'object' && translation[k]) {
                    translation = translation[k];
                } else {
                    return key; // Возвращаем исходный ключ если перевод не найден
                }
            }

            return translation || key;
        },

        getNavigationUrl(route) {
            // Если маршрут начинается с #, возвращаем как есть
            if (route.startsWith('#')) {
                return route;
            }
            // Иначе используем маршрут из routes prop
            return this.routes[route] || '#';
        },

        isActive(route) {
            const url = this.getNavigationUrl(route);
            return window.location.pathname === new URL(url, window.location.origin).pathname;
        },

        handleCurrencyChange() {
            // Принудительно обновляем данные для пересчета цен
            if (this.cartItems.length > 0) {
                this.cartItems = [...this.cartItems];
            }
        },

        handleBalanceUpdate(event) {
            // Обновляем балансы при получении события
            if (event.detail) {
                this.mainBalance = event.detail.main ?? this.mainBalance;
                this.bonusBalance = event.detail.bonus ?? this.bonusBalance;
            }
        }

    },

    async mounted() {
        // Слушаем события смены валюты
        window.addEventListener('currency-changed', this.handleCurrencyChange);

        // Слушаем события обновления баланса
        window.addEventListener('balance-updated', this.handleBalanceUpdate);

        // Инициализация Bootstrap tooltips
        if (window.bootstrap?.Tooltip) {
            this.tooltips = [...this.$el.querySelectorAll('[data-bs-toggle="tooltip"]')]
                .map(el => new window.bootstrap.Tooltip(el));
        }
    },

    beforeUnmount() {
        // Убираем слушатели при размонтировании
        window.removeEventListener('currency-changed', this.handleCurrencyChange);
        window.removeEventListener('balance-updated', this.handleBalanceUpdate);

        // Уничтожаем tooltips
        if (this.tooltips) {
            this.tooltips.forEach(t => t.dispose());
        }
    }
}
</script>