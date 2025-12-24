/**
 * API клиент для работы с корзиной
 */

import axios from 'axios';
import { handleApiError } from './helpers';

class CartAPI {
    constructor() {
        this.baseUrl = '/api/cart';
    }

    /**
     * Получить товары из корзины
     */
    async getItems() {
        try {
            const response = await axios.get(this.baseUrl);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Добавить товар в корзину
     */
    async addItem(listingId) {
        try {
            const response = await axios.post(`${this.baseUrl}/add`, {
                listing_id: listingId
            });
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Удалить товар из корзины
     */
    async removeItem(listingId) {
        try {
            const response = await axios.delete(`${this.baseUrl}/${listingId}`);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Очистить корзину
     */
    async clearCart() {
        try {
            const response = await axios.delete(this.baseUrl);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Получить количество товаров в корзине
     */
    async getCount() {
        try {
            const response = await axios.get(`${this.baseUrl}/count`);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

}

// Экспортируем singleton instance
export const cartAPI = new CartAPI();

// Экспортируем также класс для тестирования
export default CartAPI;

/**
 * API клиент для работы с заказами
 */
class OrderAPI {
    constructor() {
        this.baseUrl = '/api/orders';
    }

    /**
     * Создать заказ из корзины
     * @param {Object} data - Опциональные данные
     * @param {Array} data.listing_ids - Массив ID листингов для покупки (если не указан - все из корзины)
     */
    async createOrder(data = {}) {
        try {
            const response = await axios.post(`${this.baseUrl}/create`, data);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Быстрая покупка товара
     */
    async quickBuy(listingId) {
        try {
            const response = await axios.post(`${this.baseUrl}/quick-buy`, {
                listing_id: listingId
            });
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Оплатить заказ
     */
    async payOrder(orderId) {
        try {
            const response = await axios.post(`${this.baseUrl}/${orderId}/pay`);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Получить мои заказы
     */
    async getMyOrders(page = 1) {
        try {
            const response = await axios.get(`${this.baseUrl}/purchases?page=${page}`);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Получить мои продажи
     */
    async getMySales(page = 1) {
        try {
            const response = await axios.get(`${this.baseUrl}/sales?page=${page}`);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Отменить заказ
     */
    async cancelOrder(orderId) {
        try {
            const response = await axios.post(`${this.baseUrl}/${orderId}/cancel`);
            return response.data;
        } catch (error) {
            throw error;
        }
    }

    /**
     * Быстрая продажа предмета боту
     */
    async quickSell(assetId) {
        try {
            const response = await axios.post(`${this.baseUrl}/quick-sell`, {
                asset_id: assetId
            });
            return response.data;
        } catch (error) {
            throw error;
        }
    }
}

// Экспортируем singleton instance
export const orderAPI = new OrderAPI();