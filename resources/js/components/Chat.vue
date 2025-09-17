<template>
    <div class="chat-widget position-fixed" :style="chatStyle" ref="chatWidget">
        <!-- Collapsed state -->
        <div v-if="isCollapsed" class="chat-collapsed">
            <button @click="toggleChat"
                class="btn theme-btn rounded-circle position-relative d-flex justify-content-center align-items-center"
                style="width: 60px; height: 60px;">
                <i class="ri-message-3-line fs-2"></i>
                <span v-if="unreadCount > 0"
                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </button>
        </div>

        <!-- Expanded state -->
        <div v-else class="card shadow-lg" style="width: 350px; height: 500px;">
            <!-- Header -->
            <div class="card-header theme-btn text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h6 class="mb-0 me-2">Чат</h6>
                    <span class="bg-success rounded-circle me-1 d-inline-block" style="width: 6px; height: 6px;"></span>
                    <small>{{ onlineCount }}</small>
                </div>
                <button @click="toggleChat" class="btn btn-sm btn-link text-white p-0">
                    <i class="ri-close-line fs-5"></i>
                </button>
            </div>

            <!-- Messages -->
            <div class="card-body overflow-auto" ref="messagesContainer" style="height: calc(100% - 120px);">
                <div v-if="loading" class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>

                <div v-else-if="messages.length === 0" class="text-center text-muted py-3">
                    Сообщений пока нет
                </div>

                <div v-else>
                    <div v-for="(message, index) in messages" :key="index" class="mb-2">
                        <div class="d-flex align-items-start">
                            <img :src="message.client_avatar || '/images/default-avatar.png'"
                                class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;"
                                :alt="message.client_name">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-baseline">
                                    <strong class="me-2 text-truncate" style="max-width: 150px;">
                                        {{ message.client_name }}
                                    </strong>
                                    <small class="text-muted">{{ formatTime(message.created_at) }}</small>
                                </div>
                                <div class="text-break">{{ message.message }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input -->
            <div class="card-footer">
                <div v-if="isBanned" class="alert alert-danger py-2 mb-0 small">
                    <i class="ri-error-warning-line me-1"></i>
                    Вы забанены в чате
                    <div v-if="banReason" class="mt-1">Причина: {{ banReason }}</div>
                </div>

                <div v-else-if="throttleSeconds > 0" class="alert alert-warning py-2 mb-0 small">
                    <i class="ri-time-line me-1"></i>
                    Подождите {{ throttleSeconds }} сек
                </div>

                <form v-else @submit.prevent="sendMessage" class="d-flex gap-2">
                    <input v-model="newMessage" type="text" class="form-control form-control-sm"
                        placeholder="Введите сообщение..." maxlength="500" :disabled="sending">
                    <button type="submit" class="btn theme-btn py-1" :disabled="!newMessage.trim() || sending">
                        <i v-if="sending" class="spinner-border spinner-border-sm"></i>
                        <i v-else class="ri-send-plane-line fs-5 text-white"></i>
                    </button>
                </form>

                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">{{ newMessage.length }}/500</small>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import Echo from 'laravel-echo';

export default {
    name: 'Chat',
    data() {
        return {
            isCollapsed: true,
            messages: [],
            newMessage: '',
            loading: false,
            sending: false,
            unreadCount: 0,
            isBanned: false,
            banReason: null,
            banUntil: null,
            throttleSeconds: 0,
            onlineCount: 0,
            echo: null,
            throttleTimer: null
        };
    },
    computed: {
        chatStyle() {
            return {
                right: '70px',
                bottom: '20px',
                zIndex: 9999
            };
        }
    },
    mounted() {
        // Initialize Echo for WebSocket
        this.initializeEcho();

        // Check ban status
        this.checkBanStatus();
    },
    beforeUnmount() {
        if (this.echo) {
            this.echo.leave('presence-chat');
        }
        if (this.throttleTimer) {
            clearInterval(this.throttleTimer);
        }
    },
    methods: {
        initializeEcho() {

            // Initialize Echo with Reverb - используем точно такую же конфигурацию как в расширении
            this.echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: 'cs-skins.s1temaker.ru',
                wsPort: 80,
                wssPort: 443,
                forceTLS: true,
                enabledTransports: ['ws', 'wss'],
                wsPath: '/ws',
                disableStats: true,
            });

            // Subscribe to presence channel for automatic user counting
            const channel = this.echo.join('presence-chat')
                .here((users) => {
                    this.onlineCount = users.length;
                })
                .joining((user) => {
                    this.onlineCount++;
                })
                .leaving((user) => {
                    this.onlineCount = Math.max(0, this.onlineCount - 1);
                })
                .error((error) => {
                    console.error('Presence channel error:', error);
                });

            // Listen for new messages
            channel.listen('.message.sent', (e) => {
                this.messages.push(e);
                if (this.isCollapsed) {
                    this.unreadCount++;
                } else {
                    this.$nextTick(() => {
                        this.scrollToBottom();
                    });
                }
            });

        },
        toggleChat() {
            this.isCollapsed = !this.isCollapsed;
            if (!this.isCollapsed) {
                this.unreadCount = 0;
                this.loadMessages();
            }
        },
        async loadMessages() {
            this.loading = true;
            try {
                const response = await axios.get('/api/chat/messages');
                this.messages = response.data.messages;
            } catch (error) {
                console.error('Failed to load messages:', error);
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    this.scrollToBottom();
                });
            }
        },
        async sendMessage() {
            if (!this.newMessage.trim() || this.sending) return;

            this.sending = true;
            const message = this.newMessage;
            this.newMessage = '';

            try {
                const response = await axios.post('/api/chat/send', { message });
            } catch (error) {
                if (error.response?.status === 403) {
                    // Banned
                    this.isBanned = true;
                    this.banReason = error.response.data.ban?.reason;
                    this.banUntil = error.response.data.ban?.until;
                } else if (error.response?.status === 429) {
                    // Throttled
                    this.throttleSeconds = error.response.data.throttle_seconds || 2;
                    this.startThrottleTimer();
                } else {
                    // Restore message on error
                    this.newMessage = message;
                    alert('Не удалось отправить сообщение');
                }
            } finally {
                this.sending = false;
            }
        },
        async checkBanStatus() {
            try {
                const response = await axios.get('/api/chat/ban-status');
                this.isBanned = response.data.is_banned;
                if (this.isBanned) {
                    this.banReason = response.data.ban?.reason;
                    this.banUntil = response.data.ban?.until;
                }
            } catch (error) {
                console.error('Failed to check ban status:', error);
            }
        },
        startThrottleTimer() {
            if (this.throttleTimer) {
                clearInterval(this.throttleTimer);
            }

            this.throttleTimer = setInterval(() => {
                this.throttleSeconds--;
                if (this.throttleSeconds <= 0) {
                    clearInterval(this.throttleTimer);
                    this.throttleTimer = null;
                }
            }, 1000);
        },
        formatTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);

            if (diffMins < 1) return 'только что';
            if (diffMins < 60) return `${diffMins} мин назад`;

            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours} ч назад`;

            return date.toLocaleDateString('ru-RU', {
                day: 'numeric',
                month: 'short'
            });
        },
        scrollToBottom() {
            if (this.$refs.messagesContainer) {
                this.$refs.messagesContainer.scrollTop = this.$refs.messagesContainer.scrollHeight;
            }
        },
    }
};
</script>