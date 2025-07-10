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
                        <div class="product-main-image">
                            <div v-if="listing.is_stattrak" class="seller-badge new-badge">
                                <img class="img-fluid badge"
                                    src="https://cs-skins.s1temaker.ru/images/svg/star-white.svg" alt="medal">
                                <h6>ST</h6>
                            </div>
                            <div v-if="listing.is_souvenir" class="seller-badge souvenir-badge">
                                <h6>Souvenir</h6>
                            </div>
                            <img class="img-fluid w-100" 
                                 :src="getWearImageUrl()" 
                                 :alt="listing.item.name_ru"
                                 @error="handleImageError">
                        </div>
                        <!-- Дополнительные ссылки -->
                        <div class="additional-links mb-4">
                            <div class="d-flex flex-wrap gap-2">
                                <a :href="getScreenshotUrl()" target="_blank"
                                    class="btn btn-sm theme-outline">
                                    <i class="ri-camera-line me-1"></i>Скриншот
                                </a>
                                <a :href="getInGameInspectUrl()" target="_blank"
                                    class="btn btn-sm theme-outline">
                                    <i class="ri-gamepad-line me-1"></i>В игре
                                </a>
                                <a :href="getSteamMarketUrl(listing.item.steam_market_hash_name)" target="_blank"
                                    class="btn btn-sm theme-outline">
                                    <i class="ri-external-link-line me-1"></i>В Steam
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Правая часть: детали и действия -->
                <div class="col-lg-6">
                    <div class="product-detail-content">
                        <!-- Заголовок и цена -->
                        <div class="mb-4">
                            <h2 class="product-title mb-2">{{ listing.item.name_ru }}</h2>
                            <p class="product-subtitle text-muted mb-3">{{ listing.item.name_en }}</p>

                            <!-- Описание предмета -->
                            <div v-if="listing.item.description_ru" class="item-description mb-3">
                                <p class="text-muted">{{ listing.item.description_ru }}</p>
                            </div>
                        </div>

                        <!-- Цена и кнопки -->
                        <div class="price-section mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h3 class="price mb-0">${{ listing.price.toFixed(2) }}</h3>
                                    <p v-if="listing.item.min_steam_price" class="steam-price text-muted mb-0">
                                        Steam: ${{ listing.item.min_steam_price.toFixed(2) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="seller-info text-muted mb-0">
                                        Продавец: <strong>{{ listing.seller.name }}</strong>
                                    </p>
                                </div>
                            </div>

                            <div class="d-flex gap-3">
                                <button class="btn theme-btn flex-fill" @click="addToCart">
                                    <i class="ri-shopping-cart-line me-2"></i>В корзину
                                </button>
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
                                        <span class="info-value">{{ getTypeLabel(listing.item.type) }}</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Редкость:</span>
                                        <span class="info-value">{{ getRarityLabel(listing.item.rarity) }}</span>
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
                                        <span class="info-label">ID предмета:</span>
                                        <span class="info-value">{{ listing.item.id }}</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Лотов на Steam:</span>
                                        <span class="info-value">{{ listing.item.steam_listings_count }}</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">Steam Market Hash:</span>
                                        <span class="info-value">{{ listing.item.steam_market_hash_name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Информация о валидности -->
                        <div class="validity-info-box mb-4">
                            <div v-if="listing.item.is_valid" class="validity-item valid">
                                <div class="validity-icon">
                                    <i class="ri-checkbox-circle-line"></i>
                                </div>
                                <div class="validity-content">
                                    <h6 class="validity-title">Доступна быстрая продажа боту</h6>
                                    <p class="validity-price">Цена выкупа: <span class="price-value">${{
                                        listing.item.buyout_price?.toFixed(2) || '0.00' }}</span></p>
                                </div>
                            </div>
                            <div v-else class="validity-item invalid">
                                <div class="validity-icon">
                                    <i class="ri-information-line"></i>
                                </div>
                                <div class="validity-content">
                                    <h6 class="validity-title">Не востребован</h6>
                                    <p class="validity-description">Менее 200 лотов на Steam маркете</p>
                                </div>
                            </div>
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
                                <td><strong>${{ other.price.toFixed(2) }}</strong></td>
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
export default {
    name: 'SkinDetails',
    props: {
        listingId: {
            type: [Number, String],
            required: true
        }
    },
    data() {
        return {
            listing: null,
            otherListings: [],
            loading: true,
            error: null,
            translations: {
                types: {},
                rarities: {}
            }
        };
    },
    mounted() {
        this.loadListing();
        this.loadTranslations();
    },
    methods: {
        async loadListing() {
            try {
                const response = await fetch(`/api/marketplace/listing/${this.listingId}`);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Ошибка загрузки данных');
                }

                this.listing = data.listing;
                this.otherListings = data.otherListings || [];
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        async loadTranslations() {
            try {
                const response = await fetch('/api/translations/items');
                const data = await response.json();
                this.translations = data;
            } catch (error) {
                console.error('Ошибка загрузки переводов:', error);
            }
        },

        getTypeLabel(type) {
            return this.translations.types[type] || type;
        },

        getRarityLabel(rarity) {
            return this.translations.rarities[rarity] || rarity;
        },

        async addToCart() {
            try {
                const response = await fetch('/api/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ listing_id: this.listing.id })
                });

                const data = await response.json();

                if (response.ok) {
                    // TODO: Показать уведомление об успешном добавлении
                    alert('Товар добавлен в корзину');
                } else {
                    alert(data.message || 'Ошибка добавления в корзину');
                }
            } catch (error) {
                alert('Произошла ошибка при добавлении в корзину');
            }
        },

        async quickBuy() {
            if (!confirm('Вы уверены, что хотите совершить быструю покупку?')) {
                return;
            }

            try {
                const response = await fetch('/api/marketplace/quick-buy', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ listing_id: this.listing.id })
                });

                const data = await response.json();

                if (response.ok) {
                    // TODO: Перенаправить на страницу оплаты или показать успех
                    alert('Покупка оформлена');
                } else {
                    alert(data.message || 'Ошибка оформления покупки');
                }
            } catch (error) {
                alert('Произошла ошибка при оформлении покупки');
            }
        },

        getInGameUrl(inspectUrl) {
            // Конвертируем steam:// ссылку в игровую ссылку
            if (inspectUrl && inspectUrl.startsWith('steam://')) {
                return inspectUrl;
            }
            return null;
        },
        
        getInGameInspectUrl() {
            // Генерируем inspect ссылку для открытия в игре
            // Если есть готовый inspect_url - используем его
            if (this.listing.inspect_url) {
                return this.listing.inspect_url;
            }
            
            // Иначе генерируем базовую ссылку для поиска в игре
            const hashName = this.listing.item.steam_market_hash_name;
            const encodedHashName = encodeURIComponent(hashName);
            
            // Базовый формат для поиска предмета в игре
            return `steam://rungame/730/76561202255233023/+csgo_econ_action_preview_search%20${encodedHashName}`;
        },

        getSteamMarketUrl(steamMarketHashName) {
            // Создаем ссылку на Steam Market
            const encodedName = encodeURIComponent(steamMarketHashName);
            return `https://steamcommunity.com/market/listings/730/${encodedName}`;
        },
        
        getWearImageUrl() {
            // Получаем изображение согласно износу с fallback логикой
            const wearValue = this.listing.wear_value;
            let wearImage = null;
            
            // Определяем нужное изображение по износу
            if (wearValue <= 0.07 && this.listing.item.image_fn) {
                wearImage = this.listing.item.image_fn;
            } else if (wearValue <= 0.15 && this.listing.item.image_mw) {
                wearImage = this.listing.item.image_mw;
            } else if (wearValue <= 0.38 && this.listing.item.image_ft) {
                wearImage = this.listing.item.image_ft;
            } else if (wearValue <= 0.45 && this.listing.item.image_ww) {
                wearImage = this.listing.item.image_ww;
            } else if (this.listing.item.image_bs) {
                wearImage = this.listing.item.image_bs;
            }
            
            // Приоритет: специфичное изображение → основное → заглушка
            if (wearImage) {
                return wearImage;
            } else if (this.listing.item.image_url) {
                return this.listing.item.image_url;
            } else {
                // Заглушка
                return '/images/skin_no_image.svg';
            }
        },

        getScreenshotUrl() {
            // Для скриншота используем ту же логику
            return this.getWearImageUrl();
        },

        handleImageError(event) {
            // Добавляем класс для обработки ошибки изображения (как в маркетплейсе)
            event.target.closest('.product-main-image').classList.add('image-error');
        }
    }
};
</script>
