<template>
    <div class="product-detail-section section-b-space mt-5">
        <div class="container">
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>

            <div v-else-if="error" class="alert alert-danger">
                {{ error }}
            </div>

            <div v-else-if="listing" class="row g-5">
                <!-- Левая часть: изображение и информация -->
                <div class="col-lg-6">
                    <div class="product-detail-image p-4 rounded-4">
                        <div class="product-main-image position-relative">
                            <div v-if="listing.is_stattrak" class="seller-badge new-badge">
                                <img class="img-fluid badge" src="/images/svg/star-white.svg" alt="medal">
                                <h6>ST</h6>
                            </div>
                            <div v-if="listing.is_souvenir" class="seller-badge souvenir-badge">
                                <h6>Souvenir</h6>
                            </div>
                            <div data-favorite-button :data-listing-id="listing.id"
                                :data-is-favorite="listing.is_favorite"
                                class="favorite-button-placeholder position-absolute"
                                style="top: 15px; left: 15px; z-index: 10;">
                            </div>
                            <img class="img-fluid w-100" :src="getImageUrl()" :alt="getItemName()"
                                @error="handleImageError">
                        </div>
                        <!-- Дополнительные ссылки -->
                        <div class="additional-links mb-4">
                            <div class="d-flex flex-wrap gap-2">
                                <a :href="getInGameInspectUrl()" target="_blank" class="btn btn-sm theme-outline">
                                    <i class="ri-gamepad-line me-1"></i>В игре
                                </a>
                                <a :href="getSteamMarketUrl()" target="_blank" class="btn btn-sm theme-outline">
                                    <i class="ri-external-link-line me-1"></i>В Steam
                                </a>
                                <button v-if="hasScreenshots()" @click="showScreenshots"
                                    class="btn btn-sm theme-outline">
                                    <i class="ri-image-line me-1"></i>Скриншоты
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- История цен Steam Market -->
                    <div v-if="steamPriceHistory.length > 0" class="product-detail-image p-4 rounded-4 mt-4">
                        <h5 class="mb-3">
                            <i class="ri-line-chart-line me-2"></i>История цен
                        </h5>
                        <div class="price-chart-container">
                            <apexchart type="line" height="300" :options="chartOptions" :series="chartSeries"
                                :key="currentCurrencySymbol">
                            </apexchart>
                        </div>
                        <div class="price-stats mt-3">
                            <div class="row text-center" v-if="steamPriceStats">
                                <div class="col-4">
                                    <small class="text-muted d-block">Средняя цена (30 дней)</small>
                                    <strong v-html="formatPrice(steamPriceStats.avg_price, 'USD')"></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Минимальная</small>
                                    <strong v-html="formatPrice(steamPriceStats.min_price, 'USD')"></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Максимальная</small>
                                    <strong v-html="formatPrice(steamPriceStats.max_price, 'USD')"></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Правая часть: детали и действия -->
                <div class="col-lg-6">
                    <div class="product-detail-content">
                        <!-- Заголовок и цена -->
                        <div class="mb-4">
                            <h2 class="product-title mb-2">{{ getItemName() }}</h2>
                            <p class="product-subtitle text-muted mb-3">{{ getItemNameEn() }}</p>

                            <!-- Описание предмета -->
                            <div v-if="getItemDescription()" class="item-description mb-3">
                                <p class="text-muted" v-html="getItemDescription()"></p>
                            </div>
                        </div>

                        <!-- Цена и кнопки -->
                        <div class="price-section mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h3 class="price mb-0" v-html="formatPrice(listing.price, 'RUB')"></h3>
                                </div>
                                <div>
                                    <p class="seller-info text-muted mb-0">
                                        Продавец: <a :href="'/marketplace?seller_id=' + listing.seller.id"
                                            class="fw-bold text-decoration-none">{{ listing.seller.name }}</a>
                                    </p>
                                </div>
                            </div>

                            <!-- Кнопки только для активных листингов -->
                            <div v-if="listing.status === 'active' && canPurchase" class="d-flex gap-3">
                                <div data-cart-button :data-listing-id="listing.id"
                                    :data-is-in-cart="listing.is_in_cart" data-size="large" data-variant="primary"
                                    class="flex-fill cart-button-placeholder">
                                </div>
                                <button class="btn theme-outline flex-fill"
                                    data-bs-toggle="modal" data-bs-target="#confirmPurchaseModal">
                                    <i class="ri-flashlight-line me-2"></i>Быстрая покупка
                                </button>
                            </div>

                            <!-- Бейдж для проданных -->
                            <div v-else-if="listing.status === 'sold'" class="alert alert-secondary mb-0">
                                <i class="ri-checkbox-circle-line me-2"></i>
                                <strong>Продано</strong>
                            </div>
                        </div>

                        <!-- Компонент аукциона -->
                        <AuctionDetails :listingId="listing.id" @auction-updated="onAuctionUpdated" />

                        <!-- Float Bar -->
                        <div v-if="listing.float_value || listing.wear_value" class="col-12">
                            <FloatBar :item="listing" :show-value="true" :show-min-max="true" />
                        </div>

                        <!-- Информация о состоянии -->
                        <div class="product-info-box mb-4">
                            <h5 class="mb-3">Информация о предмете</h5>
                            <div class="row g-2">
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Тип:</span>
                                        <span class="info-value">{{ listing.inventory_type }}</span>
                                    </div>
                                </div>
                                <div v-if="listing.wear_name && listing.wear_name !== 'Не указано'" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Состояние:</span>
                                        <span class="info-value">{{ listing.wear_name }}</span>
                                    </div>
                                </div>
                                <!-- Fallback для случаев без полных данных -->
                                <div v-if="listing.float_value || listing.wear_value" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Износ:</span>
                                        <span class="info-value">{{ parseFloat(listing.float_value ||
                                            listing.wear_value).toFixed(4) }}</span>
                                    </div>
                                </div>
                                <div v-if="listing.pattern_index" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Паттерн:</span>
                                        <span class="info-value">#{{ listing.pattern_index }}</span>
                                    </div>
                                </div>
                                <div v-if="listing.paint_index" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Индекс краски:</span>
                                        <span class="info-value">{{ listing.paint_index }}</span>
                                    </div>
                                </div>
                                <div v-if="listing.def_index" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Индекс предмета:</span>
                                        <span class="info-value">{{ listing.def_index }}</span>
                                    </div>
                                </div>
                                <div v-if="listing.is_stattrak" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">StatTrak™:</span>
                                        <span class="info-value">Да</span>
                                    </div>
                                </div>
                                <div v-if="listing.is_souvenir" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Souvenir:</span>
                                        <span class="info-value">Да</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">ID предмета:</span>
                                        <span class="info-value">{{ listing.steam_asset_id }}</span>
                                    </div>
                                </div>
                                <!-- Теги из новой системы -->
                                <div v-if="listing.tags && listing.tags.length > 0" v-for="tag in listing.tags"
                                    :key="tag.id" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">{{ tag.category_name }}:</span>
                                        <span class="info-value" :style="{ color: tag.color ? '#' + tag.color : '' }">{{
                                            tag.display_name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Информация о валидности -->
                        <div class="validity-info-box mb-4">
                        </div>

                    </div>
                </div>
            </div>

            <!-- Другие предложения этого предмета -->
            <div v-if="otherListings.length > 0" class="related-listings mt-5">
                <h4 class="mb-4">Другие предложения</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Предмет</th>
                                <th class="sortable" @click="sortOther('wear_name')">Состояние <span class="sort-arrows"><span class="arrow-up" :class="{ active: otherSortKey === 'wear_name' && otherSortAsc }">&#9650;</span><span class="arrow-down" :class="{ active: otherSortKey === 'wear_name' && !otherSortAsc }">&#9660;</span></span></th>
                                <th class="sortable" @click="sortOther('float')">Износ <span class="sort-arrows"><span class="arrow-up" :class="{ active: otherSortKey === 'float' && otherSortAsc }">&#9650;</span><span class="arrow-down" :class="{ active: otherSortKey === 'float' && !otherSortAsc }">&#9660;</span></span></th>
                                <th class="sortable" @click="sortOther('seller')">Продавец <span class="sort-arrows"><span class="arrow-up" :class="{ active: otherSortKey === 'seller' && otherSortAsc }">&#9650;</span><span class="arrow-down" :class="{ active: otherSortKey === 'seller' && !otherSortAsc }">&#9660;</span></span></th>
                                <th class="sortable" @click="sortOther('price')">Цена <span class="sort-arrows"><span class="arrow-up" :class="{ active: otherSortKey === 'price' && otherSortAsc }">&#9650;</span><span class="arrow-down" :class="{ active: otherSortKey === 'price' && !otherSortAsc }">&#9660;</span></span></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="other in sortedOtherListings" :key="other.id">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img
                                            :src="getOtherImageUrl(other)"
                                            :alt="other.inventory_item_name || other.market_hash_name"
                                            class="other-listing-img"
                                            style="width: 48px; height: 36px; object-fit: contain;"
                                            @error="$event.target.src = '/images/skin_no_image.svg'"
                                        >
                                        <span class="text-truncate" style="max-width: 200px;">
                                            {{ other.inventory_item_name || other.market_hash_name }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span v-if="other.wear_name && other.wear_name !== 'Не указано'"
                                        class="badge" :class="getWearBadgeClass(other.wear_name)">
                                        {{ getWearLabel(other.wear_name) }}
                                    </span>
                                    <span v-else class="text-muted">-</span>
                                </td>
                                <td>
                                    <span v-if="other.float_value">{{ parseFloat(other.float_value).toFixed(4) }}</span>
                                    <span v-else-if="other.wear_value">{{ parseFloat(other.wear_value).toFixed(4)
                                        }}</span>
                                    <span v-else class="text-muted">-</span>
                                </td>
                                <td>{{ other.seller.name }}</td>
                                <td><strong v-html="formatPrice(other.price, 'RUB')"></strong></td>
                                <td>
                                    <a :href="`/marketplace/${other.id}`" class="btn btn-sm theme-outline">
                                        Просмотр
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Confirm Purchase modal -->
        <div class="modal fade" id="confirmPurchaseModal" tabindex="-1" aria-labelledby="confirmPurchaseModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="confirmPurchaseModalLabel">
                            <i class="ri-question-line me-2"></i>Подтверждение покупки
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <i class="ri-shopping-cart-line text-warning" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Подтвердите покупку</h4>

                        <!-- Purchase details -->
                        <div v-if="listing" class="mt-3">
                            <div class="border rounded p-3 text-start">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <strong>{{ getItemName() }}</strong>
                                        <small class="text-muted d-block">{{ getItemNameEn() }}</small>
                                        <small class="text-muted d-block">Продавец: {{ getSellerName(listing.seller) }}</small>
                                    </span>
                                    <strong class="text-primary" v-html="formatPrice(listing.price, 'RUB')"></strong>
                                </div>
                            </div>
                        </div>

                        <p class="text-muted mt-3">
                            Вы действительно хотите купить этот предмет?<br>
                            Средства будут списаны с вашего баланса.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn theme-outline" data-bs-dismiss="modal">
                            Отмена
                        </button>
                        <button type="button" class="btn theme-btn" @click="quickBuy" data-bs-dismiss="modal">
                            Подтвердить покупку
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success modal -->
        <div v-if="showSuccessModal" class="modal d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="ri-check-line me-2"></i>Покупка успешно завершена!
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <i class="ri-check-double-line text-success" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Товар успешно куплен!</h4>

                        <!-- Order details -->
                        <div v-if="purchasedOrder" class="mt-3">
                            <div class="border rounded p-3 text-start">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <strong>Заказ {{ purchasedOrder.order_number }}</strong>
                                        <small class="text-muted d-block">{{ getItemName() }}</small>
                                        <small class="text-muted d-block">
                                            Продавец: {{ purchasedOrder.seller?.name || 'Не указан' }}</small>
                                    </span>
                                    <strong class="text-primary"
                                        v-html="formatPrice(purchasedOrder.total_amount)"></strong>
                                </div>
                            </div>
                        </div>

                        <p class="text-muted mt-3">
                            Заказ успешно оплачен и передан в обработку.
                            Вы получите уведомление, когда трейд-оффер будет отправлен.
                        </p>
                    </div>
                    <div class="modal-footer d-flex justify-content-center">
                        <button type="button" class="btn theme-outline" @click="goToProfile">
                            Мои покупки
                        </button>
                        <button type="button" class="btn theme-btn" @click="goToMarketplace">
                            Продолжить покупки
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { createApp } from 'vue'
import axios from 'axios'
import CartButton from './CartButton.vue'
import FavoriteButton from './FavoriteButton.vue'
import AuctionDetails from './AuctionDetails.vue'
import FloatBar from './FloatBar.vue'
import VueApexCharts from 'vue3-apexcharts'
import { formatPrice, handleApiError } from '../../shared/utils/helpers'
import { orderAPI } from '../../shared/utils/api'

export default {
    name: 'SkinDetails',
    components: {
        apexchart: VueApexCharts,
        AuctionDetails,
        FloatBar
    },
    props: {
        listingId: {
            type: [Number, String],
            required: true
        }
    },
    setup() {
        return { formatPrice };
    },
    data() {
        return {
            listing: null,
            otherListings: [],
            loading: true,
            error: null,
            steamPriceHistory: [],
            steamPriceStats: null,
            showSuccessModal: false,
            purchasedOrder: null,
            currencySymbol: '₽', // Реактивная переменная для символа валюты
            auctionState: null, // Состояние аукциона от дочернего компонента
            otherSortKey: 'price',
            otherSortAsc: true
        };
    },
    computed: {
        currentCurrencySymbol() {
            return this.currencySymbol;
        },

        sortedOtherListings() {
            if (this.otherListings.length === 0) return [];
            const wearOrder = { 'fn': 1, 'mw': 2, 'ft': 3, 'ww': 4, 'bs': 5 };
            const dir = this.otherSortAsc ? 1 : -1;
            return [...this.otherListings].sort((a, b) => {
                let cmp = 0;
                switch (this.otherSortKey) {
                    case 'wear_name':
                        cmp = (wearOrder[a.wear_name] || 99) - (wearOrder[b.wear_name] || 99);
                        break;
                    case 'float':
                        cmp = (parseFloat(a.float_value || a.wear_value) || 0) - (parseFloat(b.float_value || b.wear_value) || 0);
                        break;
                    case 'seller':
                        cmp = (a.seller?.name || '').localeCompare(b.seller?.name || '');
                        break;
                    case 'price':
                        cmp = a.price - b.price;
                        break;
                }
                return cmp * dir;
            });
        },

        canPurchase() {
            // Если нет активного аукциона - покупка доступна
            if (!this.auctionState || !this.auctionState.isActive) {
                return true;
            }

            // Покупка недоступна когда:
            // 1) Прошла половина времени аукциона ИЛИ
            // 2) Текущая ставка >= цена маркетплейса
            const pastHalf = this.auctionState.timeLeft <= (this.auctionState.totalDuration / 2);
            const bidAbovePrice = this.auctionState.currentPrice >= this.listing.price;

            return !(pastHalf || bidAbovePrice);
        },

        chartSeries() {
            if (!this.steamPriceHistory.length) return [];

            // Конвертируем цены из USD в выбранную валюту
            const priceData = this.steamPriceHistory.map(item => ({
                x: new Date(item.date).getTime(),
                y: this.convertUsdPrice(parseFloat(item.price))
            }));

            const volumeData = this.steamPriceHistory.map(item => ({
                x: new Date(item.date).getTime(),
                y: parseInt(item.volume || 0)
            }));

            return [
                {
                    name: 'Объём продаж',
                    type: 'column',
                    data: volumeData
                },
                {
                    name: `Цена (${this.currentCurrencySymbol})`,
                    type: 'line',
                    data: priceData
                }
            ];
        },
        chartOptions() {
            return {
                chart: {
                    height: 300,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent',
                    stacked: false
                },
                stroke: {
                    curve: 'smooth',
                    width: [0, 2] // 0 для столбцов, 2 для линии
                },
                fill: {
                    type: 'solid',
                    opacity: 1
                },
                colors: ['#e8e8e8', '#f2a93e'], // Светло-серый для объёмов, оранжевый для цены
                plotOptions: {
                    bar: {
                        columnWidth: '80%',
                        borderRadius: 0
                    }
                },
                grid: {
                    borderColor: '#e0e0e0',
                    strokeDashArray: 2,
                    xaxis: {
                        lines: { show: false }
                    },
                    yaxis: {
                        lines: { show: true }
                    }
                },
                xaxis: {
                    type: 'datetime',
                    tickAmount: 5, // Примерно 5 меток на оси (каждые ~7 дней для 30-дневного периода)
                    labels: {
                        style: {
                            colors: '#666',
                            fontSize: '11px'
                        },
                        datetimeUTC: false,
                        formatter: function (value, timestamp) {
                            const date = new Date(timestamp);
                            return date.toLocaleDateString('ru-RU', {
                                day: '2-digit',
                                month: 'short'
                            }).replace('.', '');
                        }
                    },
                    axisBorder: {
                        color: '#e0e0e0'
                    },
                    axisTicks: {
                        color: '#e0e0e0'
                    }
                },
                yaxis: [
                    {
                        seriesName: 'Объём продаж',
                        min: 0,
                        axisTicks: { show: false },
                        axisBorder: { show: false },
                        labels: {
                            style: { colors: '#999', fontSize: '11px' },
                            formatter: (value) => value.toLocaleString('ru-RU')
                        },
                        title: {
                            text: 'Объём',
                            style: { color: '#999', fontSize: '12px' }
                        }
                    },
                    {
                        opposite: true,
                        seriesName: `Цена (${this.currentCurrencySymbol})`,
                        min: 0,
                        axisTicks: { show: false },
                        axisBorder: { show: false },
                        labels: {
                            style: { colors: '#f2a93e', fontSize: '11px' },
                            formatter: (value) => {
                                // Значение уже конвертировано в chartSeries, просто форматируем
                                return `${this.currentCurrencySymbol}${value.toFixed(2)}`;
                            }
                        },
                        title: {
                            text: 'Цена',
                            style: { color: '#f2a93e', fontSize: '12px' }
                        }
                    }
                ],
                tooltip: {
                    shared: true,
                    theme: 'light',
                    x: {
                        format: 'dd MMM yyyy'
                    },
                    y: [
                        {
                            formatter: (value) => value ? value.toLocaleString('ru-RU') + ' шт.' : '0 шт.'
                        },
                        {
                            formatter: (value) => {
                                if (value) {
                                    // Значение уже конвертировано в chartSeries
                                    return `${this.currentCurrencySymbol}${value.toFixed(2)}`;
                                }
                                return `${this.currentCurrencySymbol}0.00`;
                            }
                        }
                    ]
                },
                legend: {
                    show: false
                }
            };
        }
    },
    mounted() {
        // Инициализируем символ валюты
        try {
            const saved = localStorage.getItem('selectedCurrency');
            this.currencySymbol = saved ? JSON.parse(saved).symbol : '₽';
        } catch (error) {
            this.currencySymbol = '₽';
        }

        this.loadListing();

        // Слушаем события смены валюты
        window.addEventListener('currency-changed', this.handleCurrencyChange);
    },

    beforeUnmount() {
        // Убираем слушатель при размонтировании
        window.removeEventListener('currency-changed', this.handleCurrencyChange);
    },
    methods: {
        getSellerName(seller) {
            return seller?.name || 'Не указан';
        },

        onAuctionUpdated(auctionData) {
            this.auctionState = auctionData;
        },

        // Метод для конвертации цен из USD в выбранную валюту
        convertUsdPrice(usdPrice) {
            return formatPrice(usdPrice, 'USD', true);
        },

        async loadListing() {
            try {
                const response = await axios.get(`/api/marketplace/listing/${this.listingId}`);
                const data = response.data;

                this.listing = data.listing;
                this.otherListings = data.otherListings || [];
                this.steamPriceHistory = data.listing.steam_price_history || [];
                this.steamPriceStats = data.listing.steam_price_stats;

                this.$nextTick(() => {
                    this.initializeCartButton();
                });
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Ошибка загрузки данных';
            } finally {
                this.loading = false;
            }
        },

        // Вычисляемые свойства как методы для оптимизации
        getItemName() {
            return this.listing?.inventory_item_name || this.listing?.item?.name_ru || 'Неизвестный предмет';
        },

        getItemNameEn() {
            return this.listing?.market_hash_name || this.listing?.item?.name_en || '';
        },

        getItemDescription() {
            if (!this.listing?.inventory_descriptions) return null;

            let descriptions = this.listing.inventory_descriptions;

            // Парсим JSON если это строка
            if (typeof descriptions === 'string') {
                try {
                    descriptions = JSON.parse(descriptions);
                } catch (e) {
                    return null;
                }
            }

            // Ищем описание
            if (Array.isArray(descriptions)) {
                const descItem = descriptions.find(desc => desc.name === 'description');
                return descItem?.value || null;
            }

            return null;
        },

        getImageUrl() {
            if (!this.listing) return '/images/skin_no_image.svg';

            // Приоритет: inventory_icon_url → item images по wear → fallback
            if (this.listing.inventory_icon_url) {
                if (this.listing.inventory_icon_url.startsWith('http')) {
                    return this.listing.inventory_icon_url;
                }
                return `https://community.steamstatic.com/economy/image/${this.listing.inventory_icon_url}`;
            }

            // Fallback к стандартному изображению без категории

            return '/images/skin_no_image.svg';
        },

        getInGameInspectUrl() {
            if (this.listing?.inspect_url) {
                return this.listing.inspect_url;
            }

            const hashName = this.getItemNameEn();
            const encodedHashName = encodeURIComponent(hashName);
            return `steam://rungame/730/76561202255233023/+csgo_econ_action_preview_search%20${encodedHashName}`;
        },

        getSteamMarketUrl() {
            const hashName = this.getItemNameEn();
            const encodedName = encodeURIComponent(hashName);
            return `https://steamcommunity.com/market/listings/730/${encodedName}`;
        },

        // Инициализация динамических кнопок
        initializeCartButton() {
            if (!this.listing) return;

            // Конфигурация для инициализации кнопок
            const buttonConfigs = [
                {
                    selector: '[data-cart-button]:not(.cart-initialized)',
                    component: CartButton,
                    className: 'cart-initialized',
                    getProps: (button) => ({
                        listingId: parseInt(button.dataset.listingId) || this.listing.id,
                        size: button.dataset.size || 'large',
                        variant: button.dataset.variant || 'primary',
                        initialIsInCart: button.dataset.isInCart === 'true' || this.listing.is_in_cart || false
                    })
                },
                {
                    selector: '[data-favorite-button]:not(.favorite-initialized)',
                    component: FavoriteButton,
                    className: 'favorite-initialized',
                    getProps: (button) => ({
                        listingId: parseInt(button.dataset.listingId) || this.listing.id,
                        initialIsFavorite: button.dataset.isFavorite === 'true' || this.listing.is_favorite || false
                    })
                }
            ];

            // Инициализируем каждую кнопку
            buttonConfigs.forEach(config => {
                const button = document.querySelector(config.selector);
                if (button) {
                    const app = createApp(config.component, config.getProps(button));
                    app.mount(button);
                    button.classList.add(config.className);
                }
            });
        },

        async quickBuy() {
            try {
                const response = await orderAPI.quickBuy(this.listing.id);

                if (response.success) {
                    // Сохраняем данные заказа для модального окна
                    this.purchasedOrder = response.order;
                    this.showSuccessModal = true;
                } else {
                    window.toast.error(response.message);
                }
            } catch (error) {
                // Обрабатываем специфичные ошибки
                if (error.response?.status === 401) {
                    // Не показываем тост, axios interceptor уже показал
                    // Просто перенаправляем на авторизацию
                    setTimeout(() => {
                        window.location.href = '/auth/steam';
                    }, 2000);
                    return;
                }

                // Для всех остальных ошибок не показываем тост
                // axios interceptor уже обработает их
            }
        },

        goToProfile() {
            window.location.href = '/profile#orders';
        },

        goToMarketplace() {
            window.location.href = '/marketplace';
        },

        handleImageError(event) {
            event.target.closest('.product-main-image').classList.add('image-error');
        },

        hasScreenshots() {
            return this.listing?.screenshots === 1 && this.listing?.screenshot_urls;
        },


        showScreenshots() {
            if (!this.hasScreenshots()) return;

            const screenshots = [];
            const urls = this.listing.screenshot_urls;

            // Конфигурация скриншотов
            const screenshotConfig = [
                { key: 'front', title: 'Передняя сторона' },
                { key: 'back', title: 'Задняя сторона' }
            ];

            // Добавляем только существующие скриншоты
            screenshotConfig.forEach(config => {
                if (urls && urls[config.key]) {
                    screenshots.push({
                        url: urls[config.key],
                        type: config.key,
                        title: config.title
                    });
                }
            });

            // Создаем модальное окно для показа скриншотов
            this.openScreenshotModal(screenshots);
        },

        handleCurrencyChange() {
            // Обновляем символ валюты из localStorage
            try {
                const saved = localStorage.getItem('selectedCurrency');
                this.currencySymbol = saved ? JSON.parse(saved).symbol : '₽';
            } catch (error) {
                this.currencySymbol = '₽';
            }

            // Принудительно обновляем данные для пересчета цен
            if (this.steamPriceStats) {
                this.steamPriceStats = { ...this.steamPriceStats };
            }
            if (this.steamPriceHistory.length > 0) {
                this.steamPriceHistory = [...this.steamPriceHistory];
            }
            if (this.listing) {
                this.listing = { ...this.listing };
            }
            if (this.otherListings.length > 0) {
                this.otherListings = [...this.otherListings];
            }
        },

        getOtherImageUrl(other) {
            if (other.inventory_icon_url) {
                if (other.inventory_icon_url.startsWith('http')) {
                    return other.inventory_icon_url;
                }
                return `https://community.steamstatic.com/economy/image/${other.inventory_icon_url}`;
            }
            return '/images/skin_no_image.svg';
        },

        sortOther(key) {
            if (this.otherSortKey === key) {
                this.otherSortAsc = !this.otherSortAsc;
            } else {
                this.otherSortKey = key;
                this.otherSortAsc = true;
            }
        },

        getWearLabel(wearName) {
            const labels = {
                'fn': 'FN',
                'mw': 'MW',
                'ft': 'FT',
                'ww': 'WW',
                'bs': 'BS'
            };
            return labels[wearName] || wearName;
        },

        getWearBadgeClass(wearName) {
            const classes = {
                'fn': 'bg-success',
                'mw': 'bg-info',
                'ft': 'bg-warning text-dark',
                'ww': 'bg-secondary',
                'bs': 'bg-danger'
            };
            return classes[wearName] || 'bg-secondary';
        },

        openScreenshotModal(screenshots) {
            // Создаем HTML для модального окна
            const modalHtml = `
                <div class="modal fade" id="screenshotModal" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-body p-0 position-relative">
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                <div id="screenshotCarousel" class="carousel slide" data-bs-ride="carousel">
                                    <div class="carousel-inner">
                                        ${screenshots.map((s, i) => `
                                            <div class="carousel-item ${i === 0 ? 'active' : ''}">
                                                <img src="${s.url}" class="d-block w-100" alt="${s.title || 'Screenshot'}">
                                                <div class="carousel-caption d-none d-md-block">
                                                    <h5>${s.title || 'Скриншот'}</h5>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                    ${screenshots.length > 1 ? `
                                        <button class="carousel-control-prev" type="button" data-bs-target="#screenshotCarousel" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon"></span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#screenshotCarousel" data-bs-slide="next">
                                            <span class="carousel-control-next-icon"></span>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Удаляем старое модальное окно если есть
            const oldModal = document.getElementById('screenshotModal');
            if (oldModal) {
                oldModal.remove();
            }

            // Добавляем новое модальное окно
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Показываем модальное окно
            const modal = new bootstrap.Modal(document.getElementById('screenshotModal'));
            modal.show();

            // Удаляем модальное окно после закрытия
            document.getElementById('screenshotModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        },
    }
};
</script>
