/**
 * API клиент для работы с корзиной
 */

import { getApiHeaders, handleApiError } from './helpers';

class CartAPI {
    constructor() {
        this.baseUrl = '/api/cart';
    }

    /**
     * Получить товары из корзины
     */
    async getItems() {
        try {
            const response = await fetch(this.baseUrl, {
                headers: getApiHeaders()
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            throw error;
        }
    }

    /**
     * Добавить товар в корзину
     */
    async addItem(listingId) {
        try {
            const response = await fetch(`${this.baseUrl}/add`, {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({ listing_id: listingId })
            });
            
            if (!response.ok) {
                // Пытаемся получить сообщение об ошибке от сервера
                let errorMessage = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (e) {
                    // Если не удалось распарсить JSON, используем дефолтное сообщение
                }
                throw new Error(errorMessage);
            }
            
            return await response.json();
        } catch (error) {
            throw error;
        }
    }

    /**
     * Удалить товар из корзины
     */
    async removeItem(listingId) {
        try {
            const response = await fetch(`${this.baseUrl}/${listingId}`, {
                method: 'DELETE',
                headers: getApiHeaders()
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            throw error;
        }
    }

    /**
     * Очистить корзину
     */
    async clearCart() {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'DELETE',
                headers: getApiHeaders()
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            throw error;
        }
    }

    /**
     * Получить количество товаров в корзине
     */
    async getCount() {
        try {
            const response = await fetch(`${this.baseUrl}/count`, {
                headers: getApiHeaders()
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            throw error;
        }
    }

}

// Экспортируем singleton instance
export const cartAPI = new CartAPI();

// Экспортируем также класс для тестирования
export default CartAPI;