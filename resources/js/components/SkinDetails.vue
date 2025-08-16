<template>
    <div class="product-detail-section section-b-space">
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
                                <img class="img-fluid badge"
                                    src="https://cs-skins.s1temaker.ru/images/svg/star-white.svg" alt="medal">
                                <h6>ST</h6>
                            </div>
                            <div v-if="listing.is_souvenir" class="seller-badge souvenir-badge">
                                <h6>Souvenir</h6>
                            </div>
                            <div 
                                data-favorite-button 
                                :data-listing-id="listing.id"
                                :data-is-favorite="listing.is_favorite"
                                class="favorite-button-placeholder position-absolute"
                                style="top: 15px; left: 15px; z-index: 10;">
                            </div>
                            <img class="img-fluid w-100" 
                                 :src="getImageUrl()" 
                                 :alt="getItemName()"
                                 @error="handleImageError">
                        </div>
                        <!-- Дополнительные ссылки -->
                        <div class="additional-links mb-4">
                            <div class="d-flex flex-wrap gap-2">
                                <a :href="getInGameInspectUrl()" target="_blank"
                                    class="btn btn-sm theme-outline">
                                    <i class="ri-gamepad-line me-1"></i>В игре
                                </a>
                                <a :href="getSteamMarketUrl()" target="_blank"
                                    class="btn btn-sm theme-outline">
                                    <i class="ri-external-link-line me-1"></i>В Steam
                                </a>
                                <button v-if="hasScreenshots()" 
                                    @click="showScreenshots"
                                    class="btn btn-sm theme-outline">
                                    <i class="ri-image-line me-1"></i>Скриншоты
                                </button>
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
                                    <h3 class="price mb-0">{{ formatPrice(listing.price) }} ₽</h3>
                                </div>
                                <div>
                                    <p class="seller-info text-muted mb-0">
                                        Продавец: <strong>{{ listing.seller.name }}</strong>
                                    </p>
                                </div>
                            </div>

                            <div class="d-flex gap-3">
                                <div 
                                    data-cart-button 
                                    :data-listing-id="listing.id" 
                                    :data-is-in-cart="listing.is_in_cart"
                                    data-size="large" 
                                    data-variant="primary"
                                    class="flex-fill cart-button-placeholder">
                                </div>
                                <button class="btn theme-outline flex-fill" @click="quickBuy">
                                    <i class="ri-flashlight-line me-2"></i>Быстрая покупка
                                </button>
                            </div>
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
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Состояние:</span>
                                        <span class="info-value">{{ listing.wear_name }}</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Износ:</span>
                                        <span class="info-value">{{ listing.wear_value.toFixed(4) }}</span>
                                    </div>
                                </div>
                                <div v-if="listing.pattern_index" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Паттерн:</span>
                                        <span class="info-value">#{{ listing.pattern_index }}</span>
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
                                        <span class="info-label">Asset ID:</span>
                                        <span class="info-value">{{ listing.steam_asset_id }}</span>
                                    </div>
                                </div>
                                <!-- Теги из новой системы -->
                                <div v-if="listing.tags && listing.tags.length > 0" v-for="tag in listing.tags" :key="tag.id" class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">{{ tag.category_name }}:</span>
                                        <span class="info-value" :style="{ color: tag.color ? '#' + tag.color : '' }">{{ tag.display_name }}</span>
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
                <h4 class="mb-4">Другие предложения этого предмета</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Продавец</th>
                                <th>Состояние</th>
                                <th>Износ</th>
                                <th>Цена</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="other in otherListings" :key="other.id">
                                <td>{{ other.seller.name }}</td>
                                <td>{{ other.wear_name }}</td>
                                <td>{{ other.wear_value.toFixed(4) }}</td>
                                <td><strong>{{ formatPrice(other.price) }} ₽</strong></td>
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
    </div>
</template>

<script>
import { createApp } from 'vue'
import axios from 'axios'
import CartButton from './CartButton.vue'
import FavoriteButton from './FavoriteButton.vue'
import { formatPrice, handleApiError } from '../utils/helpers'

export default {
    name: 'SkinDetails',
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
            error: null
        };
    },
    mounted() {
        this.loadListing();
    },
    methods: {
        async loadListing() {
            try {
                const response = await axios.get(`/api/marketplace/listing/${this.listingId}`);
                const data = response.data;

                this.listing = data.listing;
                this.otherListings = data.otherListings || [];
                
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

        // Оптимизированная инициализация кнопок корзины и избранного
        initializeCartButton() {
            // Инициализируем кнопку корзины
            const cartButton = document.querySelector('[data-cart-button]:not(.cart-initialized)');
            
            if (cartButton && this.listing) {
                const listingId = parseInt(cartButton.dataset.listingId) || this.listing.id;
                const size = cartButton.dataset.size || 'large';
                const variant = cartButton.dataset.variant || 'primary';
                const initialIsInCart = cartButton.dataset.isInCart === 'true' || this.listing.is_in_cart || false;
                
                const cartApp = createApp(CartButton, { listingId, size, variant, initialIsInCart });
                cartApp.mount(cartButton);
                cartButton.classList.add('cart-initialized');
            }
            
            // Инициализируем кнопку избранного
            const favoriteButton = document.querySelector('[data-favorite-button]:not(.favorite-initialized)');
            
            if (favoriteButton && this.listing) {
                const listingId = parseInt(favoriteButton.dataset.listingId) || this.listing.id;
                const initialIsFavorite = favoriteButton.dataset.isFavorite === 'true' || this.listing.is_favorite || false;
                
                
                const favoriteApp = createApp(FavoriteButton, { listingId, initialIsFavorite });
                favoriteApp.mount(favoriteButton);
                favoriteButton.classList.add('favorite-initialized');
            }
        },

        async quickBuy() {
            if (!confirm('Вы уверены, что хотите совершить быструю покупку?')) {
                return;
            }

            try {
                const response = await axios.post('/api/marketplace/quick-buy', {
                    listing_id: this.listing.id
                });

                const data = response.data;
                alert('Покупка оформлена');
            } catch (error) {
                const message = error.response?.data?.message || handleApiError(error);
                alert(message);
            }
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
            
            // Используем screenshot_urls из API
            if (this.listing.screenshot_urls) {
                if (this.listing.screenshot_urls.front) {
                    screenshots.push({
                        url: this.listing.screenshot_urls.front,
                        type: 'front',
                        title: 'Передняя сторона'
                    });
                }
                
                if (this.listing.screenshot_urls.back) {
                    screenshots.push({
                        url: this.listing.screenshot_urls.back,
                        type: 'back',
                        title: 'Задняя сторона'
                    });
                }
            }
            
            // Создаем модальное окно для показа скриншотов
            this.openScreenshotModal(screenshots);
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
            document.getElementById('screenshotModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        },
    }
};
</script>