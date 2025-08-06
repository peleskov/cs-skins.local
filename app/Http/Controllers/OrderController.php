<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use App\Services\CancelOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use App\Jobs\ReleaseExpiredOrderItem;

class OrderController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CancelOrderService $cancelService
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
            // Валидируем все условия ДО создания заказа
            $this->validateCart();
            $this->validateBuyerTradeUrl();
            $cartItems = $this->cartService->getDetailedItems();
            $total = $this->cartService->getTotal();
            $this->validateItemsAvailability($cartItems);
            $this->validateBalance($total);

            DB::beginTransaction();

            try {
                // Группируем товары по продавцам
                $itemsBySeller = $cartItems->groupBy('seller_id');
                $createdOrders = [];
                
                foreach ($itemsBySeller as $sellerId => $sellerItems) {
                    $sellerTotal = $sellerItems->sum('price');
                    
                    // Списываем средства с баланса до создания заказа
                    $client = auth('client')->user();
                    $client->debit($sellerTotal);
                    
                    // Создаем заказ сразу оплаченным
                    $order = Order::create([
                        'order_number' => Order::generateOrderNumber(),
                        'buyer_id' => auth('client')->id(),
                        'seller_id' => $sellerId,
                        'total_amount' => $sellerTotal,
                        'cart_snapshot' => $sellerItems->toArray(),
                        'status' => Order::STATUS_PROCESSING,
                        'payment_status' => Order::PAYMENT_STATUS_PAID,
                        'paid_at' => now(),
                        'payment_transaction_id' => 'BALANCE_' . uniqid(),
                        'payment_method' => 'balance',
                    ]);
                    
                    // Загружаем данные продавца
                    $order->load('seller:id,name');
                    
                    $createdOrders[] = [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => (float) $order->total_amount,
                        'status' => $order->status,
                        'payment_status' => $order->payment_status,
                        'paid_at' => $order->paid_at->toISOString(),
                        'seller_id' => $sellerId,
                        'seller' => $order->seller
                    ];
                }

                // Очищаем корзину после успешной оплаты всех заказов
                $this->cartService->clear();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => count($createdOrders) === 1 
                        ? 'Заказ успешно оплачен!' 
                        : 'Заказы успешно оплачены!',
                    'orders' => $createdOrders,
                    'total_orders' => count($createdOrders)
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
            $orders = Order::with(['seller:id,name,steam_id', 'tradeOffer.statusHistory'])
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

    /**
     * Валидация корзины
     */
    private function validateCart(): void
    {
        // Проверяем, что корзина не пуста
        if ($this->cartService->getCount() === 0) {
            throw new \Exception('Корзина пуста');
        }

        // Валидируем корзину
        $removedItems = $this->cartService->validate();
        if (!empty($removedItems)) {
            throw new \Exception('Некоторые товары в корзине больше не доступны');
        }

        // Проверяем, что в корзине остались товары после валидации
        $cartItems = $this->cartService->getDetailedItems();
        $total = $this->cartService->getTotal();
        
        if ($cartItems->isEmpty() || $total <= 0) {
            throw new \Exception('В корзине нет доступных товаров для заказа');
        }
    }

    /**
     * Валидация Trade URL покупателя
     */
    private function validateBuyerTradeUrl(): void
    {
        $client = auth('client')->user();
        
        if (empty($client->steam_trade_url)) {
            throw new \Exception('Для оформления заказа необходимо указать Trade URL в профиле');
        }
    }

    /**
     * Валидация доступности товаров
     */
    private function validateItemsAvailability($cartItems): void
    {
        foreach ($cartItems as $item) {
            if (isset($item['seller_id'])) {
                $listing = \App\Models\Listing::find($item['listing_id']);
                if (!$listing || !$listing->isActive()) {
                    throw new \Exception('Товар "' . ($item['item']['name'] ?? 'Unknown') . '" больше не доступен');
                }
            }
        }
    }

    /**
     * Валидация баланса
     */
    private function validateBalance(float $total): void
    {
        $client = auth('client')->user();
        if (!$client->hasEnoughBalance($total)) {
            throw new \Exception('Недостаточно средств на балансе для оплаты заказа');
        }
    }


    /**
     * Отменить заказ
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $client = auth('client')->user();
        
        // Проверка прав
        if ($client->id !== $order->buyer_id && $client->id !== $order->seller_id) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет прав на отмену этого заказа'
            ], 403);
        }
        
        // Проверка статуса
        if ($order->status === Order::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'Завершенный заказ нельзя отменить'
            ], 400);
        }
        
        if ($order->status === Order::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Заказ уже отменен'
            ], 400);
        }
        
        // Определяем причину отмены
        $reason = $client->id === $order->buyer_id 
            ? 'Отменено покупателем' 
            : 'Отменено продавцом';
        
        // Отменяем заказ
        $result = $this->cancelService->cancelOrder($order, $reason);
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
