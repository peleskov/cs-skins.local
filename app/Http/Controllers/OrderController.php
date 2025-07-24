<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use App\Jobs\ReleaseExpiredOrderItem;

class OrderController extends Controller
{
    public function __construct(
        private CartService $cartService
    ) {}

    /**
     * Показать страницу оформления заказа
     */
    public function index(): View|RedirectResponse
    {
        // Проверяем, что пользователь авторизован
        if (!auth('client')->check()) {
            return redirect()->route('auth.steam')->with('error', 'Необходимо авторизоваться для оформления заказа');
        }

        // Проверяем, что корзина не пуста
        if ($this->cartService->getCount() === 0) {
            return redirect()->route('cart')->with('warning', 'Корзина пуста. Добавьте товары для оформления заказа.');
        }

        return view('checkout.index');
    }

    /**
     * Создать заказ из корзины
     */
    public function createOrder(Request $request): JsonResponse
    {
        if (!auth('client')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться для создания заказа'
            ], 401);
        }

        try {
            // Проверяем, что корзина не пуста
            if ($this->cartService->getCount() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Корзина пуста'
                ], 400);
            }

            // Валидируем корзину
            $removedItems = $this->cartService->validate();
            if (!empty($removedItems)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Некоторые товары в корзине больше не доступны',
                    'removed_items' => $removedItems
                ], 400);
            }

            // Получаем детальную информацию о товарах в корзине
            $cartItems = $this->cartService->getDetailedItems();
            $total = $this->cartService->getTotal();
            
            // Проверяем, что в корзине остались товары после валидации
            if ($cartItems->isEmpty() || $total <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'В корзине нет доступных товаров для заказа'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Сначала пытаемся зарезервировать все товары
                $reservedItems = [];
                $failedItems = [];
                
                foreach ($cartItems as $item) {
                    if (isset($item['seller_id'])) {
                        // Получаем листинг для резервирования
                        $listing = \App\Models\Listing::find($item['listing_id']);
                        if ($listing && $listing->reserve()) {
                            $reservedItems[] = $item;
                        } else {
                            $failedItems[] = $item;
                        }
                    }
                }
                
                // Если не удалось зарезервировать все товары
                if (!empty($failedItems)) {
                    // Освобождаем уже зарезервированные товары
                    foreach ($reservedItems as $item) {
                        $listing = \App\Models\Listing::find($item['listing_id']);
                        if ($listing) {
                            $listing->activate(); // Возвращаем в статус ACTIVE
                        }
                    }
                    
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Некоторые товары уже проданы другим покупателям: ' . 
                            implode(', ', array_column($failedItems, 'item.name'))
                    ], 400);
                }

                // Проверяем и списываем баланс только после успешного резервирования всех товаров
                $client = auth('client')->user();
                if (!$client->hasEnoughBalance($total)) {
                    // Освобождаем зарезервированные товары
                    foreach ($reservedItems as $item) {
                        $listing = \App\Models\Listing::find($item['listing_id']);
                        if ($listing) {
                            $listing->activate();
                        }
                    }
                    
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Недостаточно средств на балансе для оплаты заказа'
                    ], 400);
                }

                // Списываем средства с баланса
                $client->debit($total);
                
                // Создаем сразу оплаченный заказ
                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'buyer_id' => auth('client')->id(),
                    'total_amount' => $total,
                    'cart_snapshot' => $cartItems->toArray(),
                    'status' => Order::STATUS_PAID,
                    'payment_status' => Order::PAYMENT_STATUS_PAID,
                    'payment_method' => 'balance',
                    'payment_transaction_id' => 'BALANCE_' . uniqid(),
                    'paid_at' => now()
                ]);

                // Создаем записи в order_items для зарезервированных товаров
                foreach ($reservedItems as $item) {
                    $reservationMinutes = (int) env('RESERVATION_TIME_MINUTES', 5);
                    $orderItem = \App\Models\OrderItem::create([
                        'order_id' => $order->id,
                        'listing_id' => $item['listing_id'],
                        'seller_id' => $item['seller_id'],
                        'quantity' => 1,
                        'reserved_until' => now()->addMinutes($reservationMinutes),
                        'item_name' => $item['item']['name'] ?? 'Unknown Item',
                        'item_image_url' => $item['item']['image_url'] ?? '',
                        'price' => $item['price'],
                        'seller_name' => $item['seller']['name'] ?? 'Unknown Seller',
                        'buyer_name' => $client->name ?? 'Unknown Buyer'
                    ]);
                    
                    // Устанавливаем статус отдельно, чтобы сработало событие updated
                    $orderItem->update(['status' => \App\Models\OrderItem::STATUS_RESERVED]);
                    
                    // Запускаем отложенный job для автоматической отмены резерва
                    ReleaseExpiredOrderItem::dispatch($orderItem->id)
                        ->delay($orderItem->reserved_until);
                }

                // Очищаем корзину после успешного создания и оплаты заказа
                $this->cartService->clear();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Заказ успешно оплачен!',
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => (float) $order->total_amount,
                        'status' => $order->status,
                        'payment_status' => $order->payment_status,
                        'paid_at' => $order->paid_at->toISOString()
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании заказа: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Тестовая оплата заказа
     */
    public function payOrder(Request $request, Order $order): JsonResponse
    {
        if (!auth('client')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        // Проверяем, что заказ принадлежит текущему пользователю
        if ($order->buyer_id !== auth('client')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Заказ не найден'
            ], 404);
        }

        // Проверяем, что заказ еще не оплачен
        if ($order->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Заказ уже оплачен'
            ], 400);
        }

        try {
            // Повторно валидируем корзину прямо перед резервированием
            $removedItems = $this->cartService->validate();
            if (!empty($removedItems)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Некоторые товары в корзине больше не доступны. Обновите корзину.',
                    'removed_items' => $removedItems
                ], 400);
            }
            
            // Проверяем, что в корзине остались товары
            if ($this->cartService->getCount() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Корзина пуста. Невозможно завершить оплату.'
                ], 400);
            }

            // Проверяем баланс пользователя
            $client = auth('client')->user();
            if (!$client->hasEnoughBalance($order->total_amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Недостаточно средств на балансе для оплаты заказа'
                ], 400);
            }

            // Списываем средства с баланса
            $client->debit($order->total_amount);
            
            // Отмечаем заказ как оплаченный
            $transactionId = 'BALANCE_' . uniqid();
            $order->pay($transactionId, 'balance');

            // Очищаем корзину только после успешной оплаты и создания order_items
            $this->cartService->clear();

            return response()->json([
                'success' => true,
                'message' => 'Заказ успешно оплачен!',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => (float) $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'paid_at' => $order->paid_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            // При ошибке оплаты НЕ очищаем корзину - пользователь может попробовать снова
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при оплате: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить заказы пользователя
     */
    public function getMyOrders(): JsonResponse
    {
        if (!auth('client')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        try {
            $orders = Order::with(['items' => function($query) {
                    // Загружаем order_items с их статусами
                    $query->select('id', 'order_id', 'listing_id', 'item_name', 'item_image_url', 'price', 'status', 'seller_name', 'reserved_until', 'cancellation_reason');
                }])
                ->where('buyer_id', auth('client')->id())
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении заказов: ' . $e->getMessage()
            ], 500);
        }
    }
}
