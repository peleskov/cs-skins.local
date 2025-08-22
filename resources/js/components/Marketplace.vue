<template>
  <section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden bg-white">
    <div class="container-fluid">
      <div class="title text-center">
        <h2>Магазин скинов</h2>
        <div class="loader-line" style="left: calc(50% - 40px);"></div>
        <div class="sub-title">
          <p>Найдите популярные скины рядом.</p>
        </div>
      </div>

      <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-md-4 col-lg-3 col-xl-2">
          <div class="left-box wow fadeInUp">
            <div class="shop-left-sidebar">
              <!-- Поиск -->
              <div class="search-box">
                <div class="form-input position-relative">
                  <input type="search" class="form-control search" placeholder="Поиск по скинам..."
                    v-model="filters.search" @input="debouncedSearch">
                  <i class="ri-search-line search-icon"></i>
                </div>
              </div>

              <div class="accordion sidebar-accordion" id="accordionPanelsStayOpenExample">
                <!-- Фильтр цены -->
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapsePrice">
                      <span class="dark-text">Цена</span>
                    </button>
                  </h2>
                  <div id="collapsePrice" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                      <div class="price-range">
                        <div class="row g-2">
                          <div class="col-6">
                            <div class="form-input">
                              <input type="number" class="form-control" placeholder="Мин" min="0"
                                v-model="filters.minPrice" @change="applyFilters">
                            </div>
                          </div>
                          <div class="col-6">
                            <div class="form-input">
                              <input type="number" class="form-control" placeholder="Макс" min="0"
                                v-model="filters.maxPrice" @change="applyFilters">
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Категории -->
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapseOne">
                      <span class="dark-text">Категории</span>
                    </button>
                  </h2>
                  <div id="collapseOne" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                      <ul class="category-list custom-padding custom-height scroll-bar">
                        <li v-for="category in categories" :key="category.type">
                          <a href="#" @click.prevent="toggleCategory(category.type)"
                            :class="{ active: filters.types === category.type }">
                            <div class="form-check ps-0 m-0 category-list-box">
                              <div class="form-check-label">
                                <span class="name">{{ category.name }}</span>
                                <span class="number">({{ category.count }})</span>
                              </div>
                            </div>
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                <!-- Теги -->
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapseTwo">
                      <span class="dark-text">Теги</span>
                    </button>
                  </h2>
                  <div id="collapseTwo" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                      <ul class="filter-item-list">
                        <li v-for="tag in tags" :key="`${tag.type}-${tag.value}`" class="text-truncate">
                          <a href="#" @click.prevent="toggleTag(tag)" :class="{ active: isTagActive(tag) }" :title="`${tag.name} (${tag.count})`">
                            {{ tag.name }} ({{ tag.count }})
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Кнопка очистки фильтров -->
              <div class="mt-4 pt-3 border-top">
                <button class="btn theme-outline cart-btn w-100" @click="clearAllFilters" :disabled="!hasActiveFilters">
                  <i class="ri-refresh-line me-2"></i>
                  Очистить все
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Основной контент -->
        <div class="col-md-8 col-lg-9 col-xl-10 ratio3_2">
          <!-- Сортировка и количество -->
          <div class="row mb-4">
            <div class="col-md-6">
              <p class="small text-muted mb-0">
                Всего предложений {{ pagination.total }}, показано {{ shownCount }}
              </p>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-center justify-content-end">
                <label class="me-2">Сортировка:</label>
                <select class="form-select form-select-sm" style="width: auto;" v-model="sortValue"
                  @change="handleSortChange">
                  <option value="listed_at-desc">Новые</option>
                  <option value="price-asc">Цена: дешевые</option>
                  <option value="price-desc">Цена: дорогие</option>
                  <option value="wear_value-asc">Износ: лучшие</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Контейнер для товаров -->
          <div class="row g-4">
            <div v-for="listing in listings" :key="listing.id" class="col-lg-2 col-md-4">
              <div class="vertical-product-box">
                <div v-if="listing.is_stattrak" class="seller-badge new-badge">
                  <img class="img-fluid badge" src="https://cs-skins.s1temaker.ru/images/svg/star-white.svg"
                    alt="medal">
                  <h6>ST</h6>
                </div>
                <div class="vertical-product-box-img">
                  <a :href="`/marketplace/${listing.id}`">
                    <img class="product-img-top w-100 bg-img skin-image" 
                      :src="getListingImageUrl(listing)"
                      :alt="listing.item?.name_ru || listing.inventory_item_name || 'Неизвестный предмет'" @error="handleImageError">
                  </a>
                  <div class="offers">
                    <div class="d-flex align-items-center justify-content-between">
                      <h4>{{ formatPrice(listing.price, 'RUB') }}</h4>
                    </div>
                  </div>
                </div>
                <div class="vertical-product-body">
                  <div class="d-flex flex-column mt-sm-3 mt-2 mb-2">
                    <a :href="`/marketplace/${listing.id}`">
                      <h4 class="vertical-product-title">{{ listing.item?.name_ru || listing.inventory_item_name || 'Неизвестный предмет' }}</h4>
                    </a>
                    <h5 class="product-items mb-2">{{ listing.wear_name }} {{ listing.item?.rarity_translated || '' }}</h5>
                    <p class="text-muted small">от {{ listing.seller?.name || 'Неизвестный продавец' }}</p>
                  </div>
                  <div class="location-distance d-flex align-items-center justify-content-between gap-2 pt-sm-3 pt-2">
                    <div 
                      data-cart-button 
                      :data-listing-id="listing.id" 
                      :data-is-in-cart="listing.is_in_cart"
                      data-size="small" 
                      data-variant="outline"
                      class="cart-button-placeholder flex-fill">
                    </div>
                    <div 
                      data-favorite-button 
                      :data-listing-id="listing.id"
                      :data-is-favorite="listing.is_favorite"
                      class="favorite-button-placeholder">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Индикатор загрузки -->
          <div v-if="isLoading" class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Загрузка...</span>
            </div>
            <p class="mt-2 text-muted">Загрузка предложений...</p>
          </div>

          <!-- Кнопка "Загрузить еще" -->
          <div v-if="!isLoading && pagination.hasMorePages" class="text-center mt-4">
            <button class="btn theme-outline cart-btn" @click="loadMore">
              Загрузить еще
            </button>
          </div>

          <!-- Сообщение об отсутствии товаров -->
          <div v-if="!isLoading && listings.length === 0" class="text-center py-5">
            <i class="ri-search-line display-4 text-muted mb-3"></i>
            <h4 class="text-muted">Предложения не найдены</h4>
            <p class="text-muted">Попробуйте изменить параметры поиска</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import { ref, reactive, onMounted, computed, nextTick, onUnmounted } from 'vue'
import { createApp } from 'vue'
import axios from 'axios'
import CartButton from './CartButton.vue'
import FavoriteButton from './FavoriteButton.vue'
import { formatPrice } from '../utils/helpers'

export default {
  name: 'Marketplace',
  props: {
    initialListings: {
      type: Array,
      default: () => []
    },
    initialTotal: {
      type: Number,
      default: 0
    },
    initialHasMore: {
      type: Boolean,
      default: false
    }
  },
  setup(props) {
    // Состояние данных
    const listings = ref([...props.initialListings])
    const categories = ref([])
    const tags = ref([])
    const isLoading = ref(false)
    const currentPage = ref(2)
    const searchTimeout = ref(null)

    const pagination = reactive({
      total: props.initialTotal,
      hasMorePages: props.initialHasMore
    })

    const filters = reactive({
      search: '',
      minPrice: '',
      maxPrice: '',
      types: '',
      stattrak: false,
      souvenir: false,
      wearRange: '',
      tags: [],
      sortBy: 'listed_at',
      sortOrder: 'desc'
    })

    const sortValue = ref('listed_at-desc')

    // Вычисляемые свойства
    const shownCount = computed(() => listings.value.length)

    const hasActiveFilters = computed(() => {
      return !!(
        filters.search ||
        filters.minPrice ||
        filters.maxPrice ||
        filters.types ||
        filters.stattrak ||
        filters.souvenir ||
        filters.wearRange ||
        (filters.tags && filters.tags.length > 0) ||
        sortValue.value !== 'listed_at-desc'
      )
    })


    // Утилиты для создания параметров запроса
    const createBaseParams = (excludeTypes = false, excludeTags = false) => {
      const params = new URLSearchParams()

      if (filters.search) params.append('search', filters.search)
      if (filters.minPrice) params.append('min_price', filters.minPrice)
      if (filters.maxPrice) params.append('max_price', filters.maxPrice)
      if (!excludeTypes && filters.types) params.append('types', filters.types)
      if (filters.stattrak) params.append('stattrak', filters.stattrak)
      if (filters.souvenir) params.append('souvenir', filters.souvenir)
      
      if (filters.wearRange) {
        if (Array.isArray(filters.wearRange)) {
          filters.wearRange.forEach(wear => params.append('wear_range[]', wear))
        } else {
          params.append('wear_range', filters.wearRange)
        }
      }

      if (!excludeTags && filters.tags && filters.tags.length > 0) {
        params.append('tags', filters.tags.join(','))
      }

      return params
    }

    // API функции
    const loadTags = async () => {
      try {
        const params = createBaseParams(false, false) // Включаем все фильтры включая categories для тегов
        const response = await axios.get(`/marketplace/api/tags?${params}`)
        tags.value = response.data
      } catch (error) {
        console.error('Ошибка загрузки тегов:', error)
      }
    }

    const loadCategories = async () => {
      try {
        const params = createBaseParams(false, false) // Включаем все фильтры включая tags для категорий
        const response = await axios.get(`/marketplace/api/categories?${params}`)
        categories.value = response.data
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error)
      }
    }

    const loadListings = async (append = false) => {
      if (isLoading.value) return

      isLoading.value = true

      try {
        const params = createBaseParams()
        params.append('page', append ? currentPage.value : 1)
        params.append('per_page', 24)
        params.append('sort_by', filters.sortBy)
        params.append('sort_order', filters.sortOrder)

        const response = await axios.get(`/marketplace/api/listings?${params}`)
        const data = response.data

        if (append) {
          listings.value.push(...data.data)
          currentPage.value++
        } else {
          listings.value = data.data
          currentPage.value = 2
        }

        pagination.total = data.pagination.total
        pagination.hasMorePages = data.pagination.has_more_pages

        nextTick(() => {
          initializeButtons()
        })

      } catch (error) {
        console.error('Ошибка загрузки товаров:', error)
      } finally {
        isLoading.value = false
      }
    }

    // Функции фильтрации
    const debouncedSearch = () => {
      clearTimeout(searchTimeout.value)
      searchTimeout.value = setTimeout(() => {
        applyFilters()
      }, 300)
    }

    const applyFilters = () => {
      saveFiltersToStorage()
      loadCategories()
      loadTags()
      loadListings(false)
    }

    const toggleCategory = (type) => {
      filters.types = filters.types === type ? '' : type
      saveFiltersToStorage()
      loadCategories()
      loadTags()
      loadListings(false)
    }

    const toggleTag = (tag) => {
      if (tag.type === 'stattrak' || tag.type === 'souvenir') {
        filters[tag.type] = !filters[tag.type]
      } else if (tag.type === 'wear') {
        if (!Array.isArray(filters.wearRange)) {
          filters.wearRange = filters.wearRange ? [filters.wearRange] : []
        }

        const index = filters.wearRange.indexOf(tag.value)
        if (index > -1) {
          filters.wearRange.splice(index, 1)
        } else {
          filters.wearRange.push(tag.value)
        }

        if (filters.wearRange.length === 0) {
          filters.wearRange = ''
        }
      } else {
        const tagKey = `${tag.type}:${tag.value}`
        const index = filters.tags.indexOf(tagKey)
        
        if (index > -1) {
          filters.tags.splice(index, 1)
        } else {
          filters.tags.push(tagKey)
        }
      }

      applyFilters()
    }

    const isTagActive = (tag) => {
      if (tag.type === 'stattrak' || tag.type === 'souvenir') {
        return filters[tag.type]
      } else if (tag.type === 'wear') {
        if (Array.isArray(filters.wearRange)) {
          return filters.wearRange.includes(tag.value)
        }
        return filters.wearRange === tag.value
      } else {
        const tagKey = `${tag.type}:${tag.value}`
        return filters.tags.includes(tagKey)
      }
    }

    // Функции сортировки
    const handleSortChange = () => {
      const [sortBy, sortOrder] = sortValue.value.split('-')
      filters.sortBy = sortBy
      filters.sortOrder = sortOrder
      localStorage.setItem('marketplace_sort', sortValue.value)
      applyFilters()
    }

    // Функции управления состоянием
    const clearAllFilters = () => {
      filters.search = ''
      filters.minPrice = ''
      filters.maxPrice = ''
      filters.types = ''
      filters.stattrak = false
      filters.souvenir = false
      filters.wearRange = ''
      filters.tags = []
      filters.sortBy = 'listed_at'
      filters.sortOrder = 'desc'

      sortValue.value = 'listed_at-desc'

      localStorage.removeItem('marketplace_filters')
      localStorage.removeItem('marketplace_sort')

      loadCategories()
      loadTags()
      loadListings(false)
    }

    const loadMore = () => {
      loadListings(true)
    }

    // Функции хранения
    const saveFiltersToStorage = () => {
      const filtersToSave = {
        search: filters.search,
        minPrice: filters.minPrice,
        maxPrice: filters.maxPrice,
        types: filters.types,
        stattrak: filters.stattrak,
        souvenir: filters.souvenir,
        wearRange: filters.wearRange,
        tags: filters.tags
      }
      localStorage.setItem('marketplace_filters', JSON.stringify(filtersToSave))
    }

    const restoreFiltersFromStorage = () => {
      try {
        // Восстанавливаем фильтры
        const savedFilters = localStorage.getItem('marketplace_filters')
        if (savedFilters) {
          const parsedFilters = JSON.parse(savedFilters)
          Object.assign(filters, parsedFilters)
        }

        // Восстанавливаем сортировку
        const savedSort = localStorage.getItem('marketplace_sort')
        if (savedSort) {
          sortValue.value = savedSort
          const [sortBy, sortOrder] = savedSort.split('-')
          filters.sortBy = sortBy
          filters.sortOrder = sortOrder
        }

        // Перезагружаем данные если есть активные фильтры
        if (hasActiveFilters.value || filters.types) {
          loadCategories()
          loadTags()
          loadListings(false)
        }
      } catch (error) {
        console.error('Ошибка восстановления фильтров:', error)
      }
    }

    // Утилиты UI
    const handleImageError = (event) => {
      event.target.closest('.vertical-product-box-img').classList.add('image-error')
    }

    const createVueApp = (component, props) => {
      const app = createApp(component, props)
      return app
    }

    const initializeButtons = () => {
      // Инициализация кнопок корзины
      const cartButtons = document.querySelectorAll('[data-cart-button]:not(.cart-initialized)')
      cartButtons.forEach(button => {
        const listingId = parseInt(button.dataset.listingId)
        const size = button.dataset.size || 'normal'
        const variant = button.dataset.variant || 'primary'
        const initialIsInCart = button.dataset.isInCart === 'true'
        
        if (listingId) {
          const app = createVueApp(CartButton, { listingId, size, variant, initialIsInCart })
          app.mount(button)
          button.classList.add('cart-initialized')
        }
      })
      
      // Инициализация кнопок избранного
      const favoriteButtons = document.querySelectorAll('[data-favorite-button]:not(.favorite-initialized)')
      favoriteButtons.forEach(button => {
        const listingId = parseInt(button.dataset.listingId)
        const initialIsFavorite = button.dataset.isFavorite === 'true'
        
       
        if (listingId) {
          const app = createVueApp(FavoriteButton, { listingId, initialIsFavorite })
          app.mount(button)
          button.classList.add('favorite-initialized')
        }
      })
    }

    const getListingImageUrl = (listing) => {
      if (!listing) {
        return '/images/skin_no_image.svg'
      }
      
      if (listing.inventory_icon_url) {
        if (!listing.inventory_icon_url.startsWith('http')) {
          return `https://community.steamstatic.com/economy/image/${listing.inventory_icon_url}`
        }
        return listing.inventory_icon_url
      }
      
      if (listing.item && listing.item.image_url) {
        return listing.item.image_url
      }
      
      return '/images/skin_no_image.svg'
    }

    // Инициализация
    onMounted(() => {
      loadCategories()
      loadTags()
      restoreFiltersFromStorage()
      
        // Функция обработки смены валюты
      const handleCurrencyChange = () => {
        // Принудительно обновляем все цены, создавая новый массив
        listings.value = listings.value.map(listing => ({...listing}))
      }

      // Слушаем события смены валюты
      window.addEventListener('currency-changed', handleCurrencyChange)
      
      nextTick(() => {
        initializeButtons()
      })
    })

    // Очистка слушателя при размонтировании
    onUnmounted(() => {
      window.removeEventListener('currency-changed', handleCurrencyChange)
    })

    return {
      listings,
      categories,
      tags,
      isLoading,
      pagination,
      filters,
      sortValue,
      shownCount,
      hasActiveFilters,
      debouncedSearch,
      applyFilters,
      toggleCategory,
      toggleTag,
      isTagActive,
      handleSortChange,
      loadMore,
      formatPrice,
      clearAllFilters,
      handleImageError,
      getListingImageUrl
    }
  }
}
</script>