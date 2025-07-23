<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService
    ) {}

    /**
     * Показать страницу корзины
     */
    public function index(): View
    {
        return view('cart.index');
    }

    /**
     * API: Получить содержимое корзины
     */
    public function getItems(): JsonResponse
    {
        try {
            // Валидируем корзину (удаляем недоступные товары)
            $removedItems = $this->cartService->validate();
            
            $items = $this->cartService->getDetailedItems();
            $total = $this->cartService->getTotal();
            $count = $this->cartService->getCount();

            \Log::info('Cart items data', ['items' => $items->values()->toArray()]);

            $response = [
                'success' => true,
                'data' => [
                    'items' => $items->values(), // Убираем ключи для фронтенда
                    'total' => $total,
                    'count' => $count,
                ],
            ];

            // Если были удалены недоступные товары, сообщаем об этом
            if (!empty($removedItems)) {
                $response['warnings'] = [
                    'removed_items' => $removedItems,
                    'message' => 'Некоторые товары были удалены из корзины, так как они больше не доступны',
                ];
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении корзины: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Добавить товар в корзину
     */
    public function add(Request $request): JsonResponse
    {
        \Log::info('Cart add request', ['listing_id' => $request->listing_id, 'all_data' => $request->all()]);
        
        $request->validate([
            'listing_id' => 'required|integer|exists:listings,id',
        ]);

        try {
            $cartItem = $this->cartService->add($request->listing_id);
            \Log::info('Cart add success', ['listing_id' => $request->listing_id, 'cart_item' => $cartItem]);
            
            return response()->json([
                'success' => true,
                'message' => 'Товар добавлен в корзину',
                'data' => $cartItem,
                'cart_count' => $this->cartService->getCount(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Cart add error', ['listing_id' => $request->listing_id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * API: Удалить товар из корзины
     */
    public function destroy(int $listingId): JsonResponse
    {
        try {
            $removed = $this->cartService->remove($listingId);
            
            if (!$removed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Товар не найден в корзине',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Товар удален из корзины',
                'cart_count' => $this->cartService->getCount(),
                'cart_total' => $this->cartService->getTotal(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении товара: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Очистить корзину
     */
    public function clear(): JsonResponse
    {
        try {
            $this->cartService->clear();
            
            return response()->json([
                'success' => true,
                'message' => 'Корзина очищена',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при очистке корзины: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Проверить, есть ли товар в корзине
     */
    public function check(int $listingId): JsonResponse
    {
        try {
            $inCart = $this->cartService->has($listingId);
            
            return response()->json([
                'success' => true,
                'in_cart' => $inCart,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке корзины: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Получить счетчик товаров в корзине
     */
    public function count(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'count' => $this->cartService->getCount(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении счетчика: ' . $e->getMessage(),
            ], 500);
        }
    }
}