import axios from 'axios';
import { handleApiError } from './utils/helpers';
window.axios = axios;

// Читаем данные из data-атрибутов body (вместо inline скрипта)
const body = document.body;
window.profileTabs = JSON.parse(body.dataset.profileTabs || '[]');
window.mainNavigation = JSON.parse(body.dataset.mainNavigation || '[]');
window.footerData = JSON.parse(body.dataset.footerData || '[]');
window.translations = JSON.parse(body.dataset.translations || '[]');
if (body.dataset.clientId) window.clientId = Number(body.dataset.clientId);

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
        
        // Централизованная обработка других ошибок
        if (error.response && window.toast) {
            let errorMessage;
            
            // Специальная обработка для 401 и 429
            if (error.response.status === 401) {
                errorMessage = 'Для выполнения этого действия необходимо войти через Steam';
            } else if (error.response.status === 429) {
                errorMessage = 'Слишком много запросов. Попробуйте через несколько секунд.';
            } else {
                // Извлекаем сообщение в стандартном Laravel формате
                errorMessage = error.response.data?.message || 
                             handleApiError(error);
            }
            
            window.toast.error(errorMessage);
        }
        
        return Promise.reject(error);
    }
);
