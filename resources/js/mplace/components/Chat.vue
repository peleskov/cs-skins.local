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
                    <div v-for="message in messages" :key="messageFingerprint(message)" class="mb-2">
                        <div class="message-wrap d-flex align-items-start">
                            <div class="position-relative me-2">
                                <img :src="message.client_avatar || '/images/default-avatar.png'"
                                    class="rounded-circle"
                                    :style="{ width: '30px', height: '30px', objectFit: 'cover', border: message.client_avatar_border_color ? '2px solid ' + message.client_avatar_border_color : '' }"
                                    :alt="message.client_name">
                                <span v-if="message.client_is_premium" class="badge-premium">VIP</span>
                            </div>
                            <div class="flex-grow-1">
                                <strong class="client-name me-2 text-truncate d-block"
                                    :style="{ maxWidth: '250px', color: message.client_nickname_color || '' }">
                                    {{ message.client_name }}
                                </strong>
                                <div class="message-text text-break" v-html="formatMessage(message)"></div>
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

                <div v-else>
                    <div class="position-relative">
                        <textarea v-model="newMessage" class="form-control form-control-sm"
                            placeholder="Введите сообщение..." maxlength="500" :disabled="sending" rows="3"
                            @keydown.enter.exact.prevent="sendMessage" style="padding-bottom: 20px; resize: none;">
                        </textarea>
                        <small class="message-count text-muted position-absolute" style="bottom: 5px; right: 10px;">
                            {{ newMessage.length }}/500
                        </small>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
                        <button @click="insertMarketplaceLink" class="btn theme-outline" type="button">
                            <i class="ri-link me-1"></i>
                            Мой профиль
                        </button>
                        <button @click="sendMessage" class="btn theme-btn" :disabled="!newMessage.trim() || sending"
                            type="button">
                            <i v-if="sending" class="spinner-border spinner-border-sm"></i>
                            <i v-else class="ri-send-plane-line me-1"></i>
                            <span>
                                Отправить
                            </span>
                        </button>
                    </div>
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
                zIndex: 1040 // Ниже чем offcanvas (1045), но выше обычного контента
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
                wsHost: import.meta.env.VITE_REVERB_HOST,
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
                // Дедупликация по fingerprint — исключаем дубли от гонки WS/GET
                const fp = this.messageFingerprint(e);
                if (this.messages.some((m) => this.messageFingerprint(m) === fp)) {
                    return;
                }
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
                const loaded = response.data.messages || [];
                // Мерж с уже накопленными WS-сообщениями (защита от гонки)
                const loadedFps = new Set(loaded.map((m) => this.messageFingerprint(m)));
                const extras = this.messages.filter((m) => !loadedFps.has(this.messageFingerprint(m)));
                this.messages = [...loaded, ...extras];
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
            let message = this.newMessage;
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
        insertMarketplaceLink() {
            // Вставляем эмодзи ссылки 🔗 в место курсора
            const textarea = this.$el.querySelector('textarea');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = this.newMessage;

            // Вставляем символ 🔗 в позицию курсора
            this.newMessage = text.substring(0, start) + '🔗' + text.substring(end);

            // Устанавливаем курсор после вставленного символа (эмодзи занимает 2 позиции)
            this.$nextTick(() => {
                textarea.selectionStart = textarea.selectionEnd = start + 2;
                textarea.focus();
            });
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
        formatMessage(message) {
            let text = message.message;
            if (text.includes('🔗')) {
                const profileLink = `<a href="/marketplace?seller_id=${message.client_id}" target="_blank">Мой профиль</a>`;
                text = text.replace(/🔗/g, profileLink);
            }
            return text;
        },
        messageFingerprint(m) {
            return `${m.client_id}|${m.created_at}|${m.message}`;
        },
        scrollToBottom() {
            if (this.$refs.messagesContainer) {
                this.$refs.messagesContainer.scrollTop = this.$refs.messagesContainer.scrollHeight;
            }
        },
    }
};
</script>

<style lang="scss" scoped>
@import '../../../scss/mplace/components/chat-widget';
</style>