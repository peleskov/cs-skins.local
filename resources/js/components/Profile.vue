<template>
  <div class="profile-container">
    <div class="row g-3">
      <!-- Profile Sidebar -->
      <div class="col-lg-3">
        <div class="profile-sidebar sticky-top">
          <div class="profile-cover">
            <img 
              class="img-fluid profile-pic" 
              :src="client.steam_avatar || '/images/icons/p5.png'" 
              alt="profile"
            >
          </div>
          <div class="profile-name">
            <h5 class="user-name">{{ client.name }}</h5>
            <h6>{{ client.email || 'Email не указан' }}</h6>
          </div>
          <ul class="profile-list">
            <li :class="{ active: activeTab === 'profile' }">
              <i class="ri-user-3-line"></i>
              <a href="#" @click.prevent="setActiveTab('profile')">Профиль</a>
            </li>
            <li :class="{ active: activeTab === 'trading' }">
              <i class="ri-shopping-bag-3-line"></i>
              <a href="#" @click.prevent="setActiveTab('trading')">Торговля</a>
            </li>
            <li :class="{ active: activeTab === 'inventory' }">
              <i class="ri-treasure-map-line"></i>
              <a href="#" @click.prevent="setActiveTab('inventory')">Инвентарь</a>
            </li>
            <li :class="{ active: activeTab === 'auctions' }">
              <i class="ri-store-2-line"></i>
              <a href="#" @click.prevent="setActiveTab('auctions')">Мои аукционы</a>
            </li>
            <li :class="{ active: activeTab === 'balance' }">
              <i class="ri-bank-card-line"></i>
              <a href="#" @click.prevent="setActiveTab('balance')">Баланс</a>
            </li>
            <li :class="{ active: activeTab === 'settings' }">
              <i class="ri-settings-3-line"></i>
              <a href="#" @click.prevent="setActiveTab('settings')">Настройки</a>
            </li>
            <li>
              <i class="ri-logout-box-r-line"></i>
              <a href="#" @click.prevent="logout">Выйти</a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Profile Content -->
      <div class="col-lg-9">
        <!-- Profile Info Tab -->
        <div v-if="activeTab === 'profile'" class="change-profile-content">
          <div class="title">
            <div class="loader-line"></div>
            <h3>Информация профиля</h3>
          </div>
          <ul class="profile-details-list">
            <li>
              <div class="profile-content">
                <div class="d-flex align-items-center gap-sm-2 gap-1">
                  <i class="ri-user-3-fill"></i>
                  <span>Имя :</span>
                </div>
                <h6>{{ client.name }}</h6>
              </div>
            </li>
            <li>
              <div class="profile-content">
                <div class="d-flex align-items-center gap-sm-2 gap-1">
                  <i class="ri-mail-fill"></i>
                  <span>Email :</span>
                </div>
                <h6>
                  {{ client.email || 'Не указан' }}
                  <span 
                    v-if="client.email && client.email_verified_at"
                    class="badge bg-success-subtle ms-2"
                  >
                    Подтвержден
                  </span>
                  <span 
                    v-else-if="client.email && !client.email_verified_at"
                    class="badge bg-warning ms-2"
                  >
                    Не подтвержден
                  </span>
                </h6>
              </div>
              <div class="d-flex gap-2">
                <button 
                  class="btn theme-outline" 
                  @click="showEmailModal = true"
                >
                  {{ client.email ? 'Изменить' : 'Добавить' }}
                </button>
                <button 
                  v-if="client.email && !client.email_verified_at && canResendVerification"
                  class="btn btn-sm btn-primary"
                  @click="resendVerification"
                  :disabled="isResendingVerification"
                >
                  {{ isResendingVerification ? 'Отправка...' : 'Отправить повторно' }}
                </button>
                <small 
                  v-else-if="client.email && !client.email_verified_at && !canResendVerification"
                  class="text-muted align-self-center"
                >
                  Повторная отправка через {{ timeUntilCanResend }}
                </small>
              </div>
            </li>
            <li>
              <div class="profile-content">
                <div class="d-flex align-items-center gap-sm-2 gap-1">
                  <i class="ri-steam-fill"></i>
                  <span>Steam ID :</span>
                </div>
                <h6>{{ client.steam_id || 'Не привязан' }}</h6>
              </div>
            </li>
            <li>
              <div class="profile-content">
                <div class="d-flex align-items-center gap-sm-2 gap-1">
                  <i class="ri-exchange-line"></i>
                  <span>Trade URL :</span>
                </div>
                <h6>{{ client.steam_trade_url ? 'Настроен' : 'Не указан' }}</h6>
              </div>
              <div class="d-flex gap-2">
                <button 
                  class="btn theme-outline" 
                  @click="showTradeUrlModal = true"
                  :disabled="!client.steam_id"
                >
                  {{ client.steam_trade_url ? 'Изменить' : 'Настроить' }}
                </button>
              </div>
            </li>
            <li>
              <div class="profile-content">
                <div class="d-flex align-items-center gap-sm-2 gap-1">
                  <i class="ri-telegram-fill"></i>
                  <span>Telegram :</span>
                </div>
                <h6>
                  {{ client.telegram_username || 'Не привязан' }}
                  <span 
                    v-if="client.telegram_id"
                    class="badge bg-success-subtle ms-2"
                  >
                    Подтвержден
                  </span>
                </h6>
              </div>
              <div class="d-flex gap-2">
                <button 
                  v-if="!client.telegram_id"
                  class="btn theme-outline" 
                  @click="showTelegramModal = true"
                >
                  Привязать
                </button>
                <button 
                  v-else
                  class="btn btn-outline-danger" 
                  @click="unlinkTelegram"
                >
                  Отвязать
                </button>
              </div>
            </li>
          </ul>
        </div>

        <!-- Inventory Tab -->
        <div v-else-if="activeTab === 'inventory'" class="change-profile-content">
          <div class="title">
            <div class="loader-line"></div>
            <div class="d-flex justify-content-between align-items-center">
              <h3>Steam Инвентарь</h3>
              <button 
                v-if="inventoryData"
                class="btn theme-outline btn-sm" 
                @click="syncInventory"
                :disabled="isSyncing || syncCooldownRemaining > 0"
              >
                <i :class="['ri-refresh-line', 'me-1', { 'ri-spin': isSyncing }]"></i>
                <span v-if="isSyncing">Обновление...</span>
                <span v-else-if="syncCooldownRemaining > 0">Обновить через {{ formatCooldownTime(syncCooldownRemaining) }}</span>
                <span v-else>Обновить инвентарь</span>
              </button>
            </div>
          </div>
          <div v-if="isSyncing" class="text-center py-5">
            <div class="loader-gif">
              <div class="radar-ring"></div>
              <img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
            </div>
            <p class="mt-3">Обновляем инвентарь...</p>
          </div>
          <inventory-grid 
            v-else-if="inventoryData"
            :initial-items="inventoryData.items"
            :initial-stats="inventoryData.stats"
            @inventory-updated="handleInventoryUpdate"
          />
          <div v-else class="text-center py-5">
            <div class="loader-gif">
              <div class="radar-ring"></div>
              <img src="/images/logo_ico.svg" alt="loading" class="img-fluid">
            </div>
            <p class="mt-3">Загружаем инвентарь...</p>
          </div>
        </div>

        <!-- Other Tabs Placeholder -->
        <div v-else class="text-center py-5">
          <h4>{{ getTabTitle(activeTab) }}</h4>
          <p class="text-muted">Эта функция будет реализована позже</p>
        </div>
      </div>
    </div>

    <!-- Email Modal -->
    <div 
      v-if="showEmailModal"
      class="modal show d-block"
      tabindex="-1"
      style="background: rgba(0,0,0,0.5);"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ client.email ? 'Изменить' : 'Добавить' }} Email</h5>
            <button 
              type="button" 
              class="btn-close" 
              @click="showEmailModal = false"
            ></button>
          </div>
          <form @submit.prevent="updateEmail">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Email адрес</label>
                <input 
                  v-model="emailForm.email"
                  type="email" 
                  class="form-control"
                  :class="{ 'is-invalid': emailForm.errors.email }"
                  required
                >
                <div v-if="emailForm.errors.email" class="invalid-feedback">
                  {{ emailForm.errors.email }}
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button 
                type="button" 
                class="btn btn-secondary" 
                @click="showEmailModal = false"
              >
                Отмена
              </button>
              <button 
                type="submit" 
                class="btn btn-primary"
                :disabled="emailForm.loading"
              >
                {{ emailForm.loading ? 'Сохранение...' : 'Сохранить' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Trade URL Modal -->
    <div 
      v-if="showTradeUrlModal"
      class="modal show d-block"
      tabindex="-1"
      style="background: rgba(0,0,0,0.5);"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Настройка Trade URL</h5>
            <button 
              type="button" 
              class="btn-close" 
              @click="showTradeUrlModal = false"
            ></button>
          </div>
          <form @submit.prevent="updateTradeUrl">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Steam Trade URL</label>
                <input 
                  v-model="tradeUrlForm.tradeUrl"
                  type="url" 
                  class="form-control"
                  :class="{ 'is-invalid': tradeUrlForm.errors.tradeUrl }"
                  placeholder="https://steamcommunity.com/tradeoffer/new/?partner=..."
                  required
                >
                <div v-if="tradeUrlForm.errors.tradeUrl" class="invalid-feedback">
                  {{ tradeUrlForm.errors.tradeUrl }}
                </div>
                <div class="form-text">
                  <small>
                    Как получить Trade URL: Steam → Инвентарь → Настройки приватности инвентаря → URL для обмена
                  </small>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button 
                type="button" 
                class="btn btn-secondary" 
                @click="showTradeUrlModal = false"
              >
                Отмена
              </button>
              <button 
                type="submit" 
                class="btn btn-primary"
                :disabled="tradeUrlForm.loading"
              >
                {{ tradeUrlForm.loading ? 'Сохранение...' : 'Сохранить' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Telegram Modal -->
    <div 
      v-if="showTelegramModal"
      class="modal show d-block"
      tabindex="-1"
      style="background: rgba(0,0,0,0.5);"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Привязка Telegram</h5>
            <button 
              type="button" 
              class="btn-close" 
              @click="showTelegramModal = false"
            ></button>
          </div>
          <div class="modal-body">
            <div v-if="telegramVerificationCode" class="text-center">
              <h4>Код верификации:</h4>
              <h2 class="text-primary">{{ telegramVerificationCode }}</h2>
              <p>Отправьте этот код боту <strong>@cs_skins_bot</strong> в Telegram</p>
              <button class="btn btn-primary" @click="checkTelegramVerification">
                Проверить верификацию
              </button>
            </div>
            <div v-else class="text-center">
              <button class="btn btn-primary" @click="generateTelegramCode">
                Получить код верификации
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import InventoryGrid from './InventoryGrid.vue';
import { useToast } from "vue-toastification";

export default {
  name: 'Profile',
  components: {
    InventoryGrid
  },
  props: {
    initialClient: {
      type: Object,
      required: true
    }
  },
  setup() {
    const toast = useToast();
    return { toast };
  },
  
  data() {
    // Получаем начальную вкладку
    const getInitialTab = () => {
      // Проверяем, что мы в браузере
      if (typeof window === 'undefined') {
        return 'profile';
      }
      
      // Проверяем hash в URL
      const hash = window.location.hash.substring(1);
      if (hash && ['profile', 'trading', 'inventory', 'auctions', 'balance', 'settings'].includes(hash)) {
        return hash;
      }
      
      // Проверяем localStorage
      try {
        const savedTab = localStorage.getItem('profile-active-tab');
        if (savedTab && ['profile', 'trading', 'inventory', 'auctions', 'balance', 'settings'].includes(savedTab)) {
          return savedTab;
        }
      } catch (e) {
        // localStorage недоступен
      }
      
      return 'profile';
    };

    return {
      client: { ...this.initialClient },
      activeTab: getInitialTab(),
      inventoryData: null,
      isSyncing: false,
      syncCooldownRemaining: 0,
      cooldownTimer: null,
      showEmailModal: false,
      showTradeUrlModal: false,
      showTelegramModal: false,
      telegramVerificationCode: null,
      isResendingVerification: false,
      timeUntilCanResend: '',
      canResendVerification: true,
      emailForm: {
        email: this.initialClient.email || '',
        loading: false,
        errors: {}
      },
      tradeUrlForm: {
        tradeUrl: this.initialClient.steam_trade_url || '',
        loading: false,
        errors: {}
      },
    }
  },
  methods: {
    setActiveTab(tab) {
      this.activeTab = tab;
      
      // Сохраняем активную вкладку в localStorage
      try {
        localStorage.setItem('profile-active-tab', tab);
      } catch (e) {
        // localStorage недоступен
      }
      
      // Обновляем hash в URL
      if (typeof window !== 'undefined') {
        window.history.replaceState(null, null, '#' + tab);
      }
      
      // Загружаем данные для инвентаря при переходе на вкладку
      if (tab === 'inventory' && !this.inventoryData) {
        this.loadInventoryData();
      }
    },
    
    async loadInventoryData() {
      try {
        const response = await fetch('/inventory', {
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });
        
        const data = await response.json();
        
        if (data.success) {
          this.inventoryData = data.data;
          
          // Проверяем, нужно ли запустить кулдаун для синхронизации
          if (data.data.stats && data.data.stats.last_sync) {
            const lastSyncTime = new Date(data.data.stats.last_sync);
            const now = new Date();
            const timeDiff = now - lastSyncTime;
            const cooldownTime = 2 * 60 * 1000; // 2 минуты в мс
            
            if (timeDiff < cooldownTime) {
              const remainingSeconds = Math.ceil((cooldownTime - timeDiff) / 1000);
              this.startSyncCooldown(remainingSeconds);
            }
          }
          
          // Если инвентарь пустой, показываем сообщение
          if (data.data.items.length === 0) {
            this.toast.info('Ваш Steam инвентарь пуст или приватный. Убедитесь, что инвентарь публичный в настройках Steam. После изменения настроек инвентаря в Steam попробуйте еще раз через 10-15 минут.', {
              timeout: 10000
            });
          }
        } else {
          // Если API вернул ошибку, устанавливаем пустой инвентарь
          this.inventoryData = { items: [], stats: {} };
          this.toast.error(data.message || 'Не удалось загрузить инвентарь');
        }
      } catch (error) {
        console.error('Error loading inventory:', error);
        // В любом случае устанавливаем пустые данные чтобы убрать лоадер
        this.inventoryData = { items: [], stats: {} };
        this.toast.error('Не удалось загрузить инвентарь. Убедитесь, что ваш Steam инвентарь публичный и попробуйте ещё раз. После изменения настроек инвентаря в Steam попробуйте еще раз через 10-15 минут.', {
          timeout: 10000
        });
      }
    },

    async updateEmail() {
      this.emailForm.loading = true;
      this.emailForm.errors = {};

      try {
        const response = await fetch('/profile/update-email', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            email: this.emailForm.email
          })
        });

        const data = await response.json();

        if (data.success) {
          this.client.email = this.emailForm.email;
          this.client.email_verified_at = null;
          this.showEmailModal = false;
          this.toast.success('Email обновлен. Проверьте почту для подтверждения.');
        } else {
          this.emailForm.errors = data.errors || {};
          this.toast.error(data.message || 'Не удалось обновить email');
        }
      } catch (error) {
        console.error('Email update error:', error);
        this.toast.error('Произошла ошибка при обновлении email');
      } finally {
        this.emailForm.loading = false;
      }
    },

    async updateTradeUrl() {
      this.tradeUrlForm.loading = true;
      this.tradeUrlForm.errors = {};

      try {
        const response = await fetch('/profile/update-trade-url', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            steam_trade_url: this.tradeUrlForm.tradeUrl
          })
        });

        const data = await response.json();

        if (data.success) {
          this.client.steam_trade_url = this.tradeUrlForm.tradeUrl;
          this.showTradeUrlModal = false;
          this.toast.success('Trade URL обновлен');
        } else {
          this.tradeUrlForm.errors = data.errors || {};
          this.toast.error(data.message || 'Не удалось обновить Trade URL');
        }
      } catch (error) {
        console.error('Trade URL update error:', error);
        this.toast.error('Произошла ошибка при обновлении Trade URL');
      } finally {
        this.tradeUrlForm.loading = false;
      }
    },

    async generateTelegramCode() {
      try {
        const response = await fetch('/profile/telegram/verify', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });

        const data = await response.json();

        if (data.success) {
          this.telegramVerificationCode = data.code;
        } else {
          this.toast.error(data.message || 'Не удалось получить код');
        }
      } catch (error) {
        console.error('Telegram code generation error:', error);
        this.toast.error('Произошла ошибка при генерации кода');
      }
    },

    async checkTelegramVerification() {
      try {
        const response = await fetch('/profile/telegram/verify', {
          method: 'GET',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });

        const data = await response.json();

        if (data.verified) {
          this.client.telegram_id = data.telegram_id;
          this.client.telegram_username = data.telegram_username;
          this.showTelegramModal = false;
          this.telegramVerificationCode = null;
          this.toast.success('Telegram аккаунт привязан');
        } else {
          this.toast.info('Верификация еще не завершена');
        }
      } catch (error) {
        console.error('Telegram verification check error:', error);
        this.toast.error('Произошла ошибка при проверке верификации');
      }
    },

    async unlinkTelegram() {
      if (!confirm('Вы уверены, что хотите отвязать Telegram?')) {
        return;
      }

      try {
        const response = await fetch('/profile/telegram/unlink', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });

        const data = await response.json();

        if (data.success) {
          this.client.telegram_id = null;
          this.client.telegram_username = null;
          this.toast.success('Telegram аккаунт отвязан');
        } else {
          this.toast.error(data.message || 'Не удалось отвязать Telegram');
        }
      } catch (error) {
        console.error('Telegram unlink error:', error);
        this.toast.error('Произошла ошибка при отвязке Telegram');
      }
    },

    async resendVerification() {
      this.isResendingVerification = true;

      try {
        const response = await fetch('/email/resend', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });

        const data = await response.json();

        if (data.success) {
          this.toast.success('Письмо отправлено повторно');
          this.updateResendTimer();
        } else {
          this.toast.error(data.message || 'Не удалось отправить письмо');
        }
      } catch (error) {
        console.error('Resend verification error:', error);
        this.toast.error('Произошла ошибка при отправке письма');
      } finally {
        this.isResendingVerification = false;
      }
    },

    updateResendTimer() {
      // Логика таймера для повторной отправки
      this.canResendVerification = false;
      let seconds = 60;
      
      const timer = setInterval(() => {
        if (seconds <= 0) {
          this.canResendVerification = true;
          this.timeUntilCanResend = '';
          clearInterval(timer);
        } else {
          this.timeUntilCanResend = `${seconds} сек`;
          seconds--;
        }
      }, 1000);
    },

    logout() {
      if (confirm('Вы уверены, что хотите выйти?')) {
        window.location.href = '/auth/logout';
      }
    },

    getTabTitle(tab) {
      const titles = {
        trading: 'Торговля',
        auctions: 'Мои аукционы',
        balance: 'Баланс',
        settings: 'Настройки'
      };
      return titles[tab] || 'Раздел';
    },


    async handleInventoryUpdate() {
      // Перезагружаем данные инвентаря после синхронизации
      await this.loadInventoryData();
    },

    async syncInventory() {
      this.isSyncing = true;
      
      try {
        const response = await fetch('/inventory/sync', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });

        const data = await response.json();

        if (data.success) {
          this.toast.success(`Инвентарь обновлен! Загружено предметов: ${data.data.items_count}`);
          
          // Обновляем данные без перезагрузки страницы
          await this.loadInventoryData();
          
          // Запускаем кулдаун на 2 минуты
          this.startSyncCooldown(120); // 2 минуты = 120 секунд
        } else {
          this.toast.error(data.message);
          
          // Если есть информация о кулдауне, запускаем его
          if (data.data && data.data.cooldown_remaining) {
            this.startSyncCooldown(data.data.cooldown_remaining);
          }
        }
      } catch (error) {
        console.error('Sync error:', error);
        this.toast.error('Произошла ошибка при обновлении инвентаря');
      } finally {
        this.isSyncing = false;
      }
    },

    startSyncCooldown(seconds) {
      this.syncCooldownRemaining = seconds;
      
      // Очищаем предыдущий таймер если он есть
      if (this.cooldownTimer) {
        clearInterval(this.cooldownTimer);
      }
      
      this.cooldownTimer = setInterval(() => {
        this.syncCooldownRemaining--;
        
        if (this.syncCooldownRemaining <= 0) {
          clearInterval(this.cooldownTimer);
          this.cooldownTimer = null;
        }
      }, 1000);
    },

    formatCooldownTime(seconds) {
      const minutes = Math.floor(seconds / 60);
      const remainingSeconds = seconds % 60;
      
      if (minutes > 0) {
        return `${minutes} мин ${remainingSeconds} сек`;
      }
      return `${remainingSeconds} сек`;
    },

    handleHashChange() {
      // Обрабатываем изменение хэша в URL
      const hash = window.location.hash.substring(1);
      if (hash && ['profile', 'trading', 'inventory', 'auctions', 'balance', 'settings'].includes(hash)) {
        this.setActiveTab(hash);
      }
    }
  },

  mounted() {
    // Загружаем данные для инвентаря если это активная вкладка при загрузке
    if (this.activeTab === 'inventory' && !this.inventoryData) {
      this.loadInventoryData();
    }

    // Слушаем изменения хэша в URL
    window.addEventListener('hashchange', this.handleHashChange);
  },

  beforeUnmount() {
    // Убираем слушатель при уничтожении компонента
    window.removeEventListener('hashchange', this.handleHashChange);
    
    // Очищаем таймер кулдауна
    if (this.cooldownTimer) {
      clearInterval(this.cooldownTimer);
    }
  }
}
</script>