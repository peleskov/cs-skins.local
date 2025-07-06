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
                  <input 
                    type="search" 
                    class="form-control search" 
                    placeholder="Поиск по скинам..."
                    v-model="filters.search"
                    @input="debouncedSearch"
                  >
                  <i class="ri-search-line search-icon"></i>
                </div>
              </div>
              
              <div class="accordion sidebar-accordion" id="accordionPanelsStayOpenExample">
                <!-- Фильтр цены -->
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePrice">
                      <span class="dark-text">Цена</span>
                    </button>
                  </h2>
                  <div id="collapsePrice" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                      <div class="price-range">
                        <div class="row g-2">
                          <div class="col-6">
                            <div class="form-input">
                              <input 
                                type="number" 
                                class="form-control" 
                                placeholder="Мин" 
                                min="0"
                                v-model="filters.minPrice"
                                @change="applyFilters"
                              >
                            </div>
                          </div>
                          <div class="col-6">
                            <div class="form-input">
                              <input 
                                type="number" 
                                class="form-control" 
                                placeholder="Макс" 
                                min="0"
                                v-model="filters.maxPrice"
                                @change="applyFilters"
                              >
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
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                      <span class="dark-text">Категории</span>
                    </button>
                  </h2>
                  <div id="collapseOne" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                      <ul class="category-list custom-padding custom-height scroll-bar">
                        <li v-for="category in categories" :key="category.type">
                          <a 
                            href="#" 
                            @click.prevent="toggleCategory(category.type)"
                            :class="{ active: filters.types === category.type }"
                          >
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
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                      <span class="dark-text">Теги</span>
                    </button>
                  </h2>
                  <div id="collapseTwo" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                      <ul class="filter-item-list">
                        <li v-for="tag in tags" :key="tag.type + '-' + tag.value">
                          <a 
                            href="#" 
                            @click.prevent="toggleTag(tag)"
                            :class="{ active: isTagActive(tag) }"
                          >
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
                <button 
                  class="btn theme-outline cart-btn w-100" 
                  @click="clearAllFilters"
                  :disabled="!hasActiveFilters"
                >
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
                <select 
                  class="form-select form-select-sm" 
                  style="width: auto;"
                  v-model="sortValue"
                  @change="handleSortChange"
                >
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
            <div 
              v-for="listing in listings" 
              :key="listing.id"
              class="col-lg-2 col-md-4"
            >
              <div class="vertical-product-box">
                <div v-if="listing.is_stattrak" class="seller-badge new-badge">
                  <img class="img-fluid badge" src="https://cs-skins.s1temaker.ru/images/svg/star-white.svg" alt="medal">
                  <h6>ST</h6>
                </div>
                <div class="vertical-product-box-img">
                  <a :href="`/marketplace/listing/${listing.id}`">
                    <img 
                      class="product-img-top w-100 bg-img skin-image" 
                      :src="listing.item.image_url" 
                      :alt="listing.item.name_ru"
                      @error="handleImageError"
                    >
                  </a>
                  <div class="offers">
                    <div class="d-flex align-items-center justify-content-between">
                      <h4>${{ formatPrice(listing.price) }}</h4>
                    </div>
                  </div>
                </div>
                <div class="vertical-product-body">
                  <div class="d-flex flex-column mt-sm-3 mt-2 mb-2">
                    <a :href="`/marketplace/listing/${listing.id}`">
                      <h4 class="vertical-product-title">{{ listing.item.name_ru }}</h4>
                    </a>
                    <h5 class="product-items mb-2">{{ listing.wear_name }} {{ listing.item.rarity_translated }}</h5>
                    <p class="text-muted small">от {{ listing.seller.name }}</p>
                  </div>
                  <div class="location-distance d-flex align-items-center justify-content-between pt-sm-3 pt-2">
                    <a href="#" class="btn theme-outline cart-btn rounded-2">В корзину</a>
                    <a href="#!" class="like-btn">
                      <i class="ri-heart-3-fill fill-icon"></i>
                      <i class="ri-heart-3-line outline-icon"></i>
                      <div class="effect-group">
                        <span class="effect"></span>
                        <span class="effect"></span>
                        <span class="effect"></span>
                        <span class="effect"></span>
                        <span class="effect"></span>
                      </div>
                    </a>
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
import { ref, reactive, onMounted, computed, watch } from 'vue'

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
      sortBy: 'listed_at',
      sortOrder: 'desc'
    })
    
    const sortValue = ref('listed_at-desc')
    
    const shownCount = computed(() => listings.value.length)
    
    // Проверка наличия активных фильтров
    const hasActiveFilters = computed(() => {
      return !!(
        filters.search ||
        filters.minPrice ||
        filters.maxPrice ||
        filters.types ||
        filters.stattrak ||
        filters.souvenir ||
        filters.wearRange ||
        sortValue.value !== 'listed_at-desc'
      )
    })
    
    // Проверка активности состояния износа
    const isWearActive = (value) => {
      if (Array.isArray(filters.wearRange)) {
        return filters.wearRange.includes(value)
      }
      return filters.wearRange === value
    }
    
    // Загрузка тегов с учетом текущих фильтров
    const loadTags = async () => {
      try {
        // Создаем параметры запроса с текущими фильтрами (кроме самих тегов)
        const params = new URLSearchParams()
        
        if (filters.search) params.append('search', filters.search)
        if (filters.minPrice) params.append('min_price', filters.minPrice)
        if (filters.maxPrice) params.append('max_price', filters.maxPrice)
        if (filters.types) params.append('types', filters.types)
        
        const url = `/marketplace/api/tags?${params}`
        const response = await fetch(url)
        tags.value = await response.json()
        
      } catch (error) {
        console.error('Ошибка загрузки тегов:', error)
      }
    }
    
    // Загрузка категорий с учетом текущих фильтров
    const loadCategories = async () => {
      try {
        // Создаем параметры запроса с текущими фильтрами (кроме типа)
        const params = new URLSearchParams()
        
        if (filters.search) params.append('search', filters.search)
        if (filters.minPrice) params.append('min_price', filters.minPrice)
        if (filters.maxPrice) params.append('max_price', filters.maxPrice)
        if (filters.stattrak) params.append('stattrak', filters.stattrak)
        if (filters.souvenir) params.append('souvenir', filters.souvenir)
        if (filters.wearRange) {
          if (Array.isArray(filters.wearRange)) {
            filters.wearRange.forEach(wear => params.append('wear_range[]', wear))
          } else {
            params.append('wear_range', filters.wearRange)
          }
        }
        
        const url = `/marketplace/api/categories?${params}`
        const response = await fetch(url)
        categories.value = await response.json()
        
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error)
      }
    }
    
    // Загрузка предложений
    const loadListings = async (append = false) => {
      if (isLoading.value) return
      
      isLoading.value = true
      
      try {
        const params = new URLSearchParams({
          page: append ? currentPage.value : 1,
          per_page: 24
        })
        
        // Добавляем фильтры
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== '' && value !== false && value !== null) {
            if (key === 'minPrice') params.append('min_price', value)
            else if (key === 'maxPrice') params.append('max_price', value)
            else if (key === 'wearRange') {
              if (Array.isArray(value)) {
                value.forEach(wear => params.append('wear_range[]', wear))
              } else {
                params.append('wear_range', value)
              }
            }
            else if (key === 'sortBy') params.append('sort_by', value)
            else if (key === 'sortOrder') params.append('sort_order', value)
            else params.append(key, value)
          }
        })
        
        const url = `/marketplace/api/listings?${params}`
        const response = await fetch(url)
        const data = await response.json()
        
        if (append) {
          listings.value.push(...data.data)
          currentPage.value++
        } else {
          listings.value = data.data
          currentPage.value = 2
        }
        
        pagination.total = data.pagination.total
        pagination.hasMorePages = data.pagination.has_more_pages
        
      } catch (error) {
        console.error('Ошибка загрузки товаров:', error)
      } finally {
        isLoading.value = false
      }
    }
    
    // Поиск с задержкой
    const debouncedSearch = () => {
      clearTimeout(searchTimeout.value)
      searchTimeout.value = setTimeout(() => {
        saveFiltersToStorage()
        loadCategories()
        loadTags()
        loadListings(false)
      }, 300)
    }
    
    // Применение фильтров
    const applyFilters = () => {
      // Сохраняем фильтры в localStorage
      saveFiltersToStorage()
      // Обновляем категории, теги и товары
      loadCategories()
      loadTags()
      loadListings(false)
    }
    
    // Сохранение фильтров в localStorage
    const saveFiltersToStorage = () => {
      const filtersToSave = {
        search: filters.search,
        minPrice: filters.minPrice,
        maxPrice: filters.maxPrice,
        types: filters.types,
        stattrak: filters.stattrak,
        souvenir: filters.souvenir,
        wearRange: filters.wearRange
      }
      localStorage.setItem('marketplace_filters', JSON.stringify(filtersToSave))
    }
    
    // Восстановление фильтров из localStorage
    const restoreFiltersFromStorage = () => {
      try {
        const savedFilters = localStorage.getItem('marketplace_filters')
        if (savedFilters) {
          const parsedFilters = JSON.parse(savedFilters)
          Object.assign(filters, parsedFilters)
        }
      } catch (error) {
        console.error('Ошибка восстановления фильтров:', error)
      }
    }
    
    // Переключение категорий
    const toggleCategory = (type) => {
      if (filters.types === type) {
        filters.types = ''
      } else {
        filters.types = type
      }
      // Сохраняем фильтры и обновляем только товары (категории не нужно обновлять при смене категории)
      saveFiltersToStorage()
      loadListings(false)
    }
    
    // Переключение тегов (универсальная функция)
    const toggleTag = (tag) => {
      if (tag.type === 'stattrak' || tag.type === 'souvenir') {
        filters[tag.type] = !filters[tag.type]
      } else if (tag.type === 'wear') {
        // Преобразуем wearRange в массив если это еще не массив
        if (!Array.isArray(filters.wearRange)) {
          filters.wearRange = filters.wearRange ? [filters.wearRange] : []
        }
        
        const index = filters.wearRange.indexOf(tag.value)
        if (index > -1) {
          // Убираем из массива
          filters.wearRange.splice(index, 1)
        } else {
          // Добавляем в массив
          filters.wearRange.push(tag.value)
        }
        
        // Если массив пустой, сбрасываем фильтр
        if (filters.wearRange.length === 0) {
          filters.wearRange = ''
        }
      }
      
      applyFilters()
    }
    
    // Проверка активности тега
    const isTagActive = (tag) => {
      if (tag.type === 'stattrak' || tag.type === 'souvenir') {
        return filters[tag.type]
      } else if (tag.type === 'wear') {
        return isWearActive(tag.value)
      }
      return false
    }
    
    // Обработка сортировки
    const handleSortChange = () => {
      const [sortBy, sortOrder] = sortValue.value.split('-')
      filters.sortBy = sortBy
      filters.sortOrder = sortOrder
      
      // Сохраняем в localStorage
      localStorage.setItem('marketplace_sort', sortValue.value)
      
      applyFilters()
    }
    
    // Загрузить еще
    const loadMore = () => {
      loadListings(true)
    }
    
    // Форматирование цены
    const formatPrice = (price) => {
      return parseFloat(price).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    }
    
    // Очистка всех фильтров
    const clearAllFilters = () => {
      // Сбрасываем все фильтры
      filters.search = ''
      filters.minPrice = ''
      filters.maxPrice = ''
      filters.types = ''
      filters.stattrak = false
      filters.souvenir = false
      filters.wearRange = ''
      filters.sortBy = 'listed_at'
      filters.sortOrder = 'desc'
      
      // Сбрасываем сортировку
      sortValue.value = 'listed_at-desc'
      
      // Очищаем localStorage
      localStorage.removeItem('marketplace_filters')
      localStorage.removeItem('marketplace_sort')
      
      // Перезагружаем данные
      loadCategories()
      loadTags()
      loadListings(false)
    }
    
    // Обработка ошибок изображений
    const handleImageError = (event) => {
      event.target.closest('.vertical-product-box-img').classList.add('image-error')
    }
    
    // Восстановление сохраненной сортировки
    const restoreSavedSort = () => {
      const savedSort = localStorage.getItem('marketplace_sort')
      if (savedSort) {
        sortValue.value = savedSort
        const [sortBy, sortOrder] = savedSort.split('-')
        filters.sortBy = sortBy
        filters.sortOrder = sortOrder
      }
      
      // Проверяем, нужно ли перезагрузить данные
      const hasFilters = filters.search || filters.minPrice || filters.maxPrice || 
                        filters.types || filters.stattrak || filters.souvenir || 
                        filters.wearRange || (savedSort && savedSort !== 'listed_at-desc')
      
      if (hasFilters) {
        // Обновляем категории если есть фильтры (кроме фильтра по типу)
        const hasNonTypeFilters = filters.search || filters.minPrice || filters.maxPrice || 
                                 filters.stattrak || filters.souvenir || filters.wearRange
        if (hasNonTypeFilters) {
          loadCategories()
        }
        loadListings(false)
      }
    }
    
    onMounted(() => {
      loadCategories()
      loadTags()
      restoreFiltersFromStorage()
      restoreSavedSort()
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
      saveFiltersToStorage,
      restoreFiltersFromStorage,
      isWearActive
    }
  }
}
</script>