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
            return await response.json();
        } catch (error) {
            throw new Error(handleApiError(error));
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
            return await response.json();
        } catch (error) {
            throw new Error(handleApiError(error));
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
            return await response.json();
        } catch (error) {
            throw new Error(handleApiError(error));
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
            return await response.json();
        } catch (error) {
            throw new Error(handleApiError(error));
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
            return await response.json();
        } catch (error) {
            throw new Error(handleApiError(error));
        }
    }

    /**
     * Проверить наличие товара в корзине
     */
    async checkItem(listingId) {
        try {
            const response = await fetch(`${this.baseUrl}/check/${listingId}`, {
                headers: getApiHeaders()
            });
            return await response.json();
        } catch (error) {
            throw new Error(handleApiError(error));
        }
    }
}

// Экспортируем singleton instance
export const cartAPI = new CartAPI();

// Экспортируем также класс для тестирования
export default CartAPI;