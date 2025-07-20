import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF Token Interceptor - автоматически обновляет истекшие токены
axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response && error.response.status === 419) {
            try {
                // Получаем новый CSRF токен
                const { data } = await axios.get('/api/csrf-token');
                
                // Обновляем токен в meta теге
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    metaTag.setAttribute('content', data.csrf_token);
                }
                
                // Обновляем заголовки axios
                axios.defaults.headers.common['X-CSRF-TOKEN'] = data.csrf_token;
                
                // Повторяем исходный запрос с новым токеном
                error.config.headers['X-CSRF-TOKEN'] = data.csrf_token;
                return axios.request(error.config);
            } catch (refreshError) {
                // Если не удалось обновить токен, перезагружаем страницу
                console.error('Failed to refresh CSRF token:', refreshError);
                window.location.reload();
            }
        }
        
        return Promise.reject(error);
    }
);
