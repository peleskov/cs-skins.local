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
use Illuminate\Support\Facades\Log;
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
     * Покупка товаров из корзины
     */
    public function cartBuy(Request $request): JsonResponse
    {
        if (!auth('client')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться для создания заказа'
            ], 401);
        }

        try {
            // Валидируем корзину
            $this->validateCart();

            // Получаем товары из корзины
            $cartItems = $this->cartService->getDetailedItems();

            // Создаем заказы
            $result = $this->createOrder($cartItems);

            if ($result['success']) {
                // Очищаем корзину после успешной покупки
                $this->cartService->clear();

                return response()->json([
                    'success' => true,
                    'message' => count($result['orders']) === 1
                        ? 'Заказ успешно оплачен!'
                        : 'Заказы успешно оплачены!',
                    'orders' => $result['orders'],
                    'total_orders' => count($result['orders'])
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании заказа: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Быстрая покупка одного товара
     */
    public function quickBuy(Request $request): JsonResponse
    {
        if (!auth('client')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться для покупки'
            ], 401);
        }

        $request->validate([
            'listing_id' => 'required|integer|exists:listings,id'
        ]);

        try {
            // Находим товар
            $listing = \App\Models\Listing::findOrFail($request->listing_id);

            // Проверяем доступность товара
            if (!$listing->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Товар больше не доступен для покупки'
                ], 400);
            }

            // Проверяем что это не свой товар
            if ($listing->seller_id === auth('client')->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя купить свой собственный товар'
                ], 400);
            }

            // Создаем коллекцию с одним товаром в том же формате, что и корзина
            $items = collect([
                [
                    'listing_id' => $listing->id,
                    'item' => [
                        'name' => $listing->inventory_item_name,
                        'image_url' => $listing->inventory_icon_url ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $listing->inventory_icon_url : null,
                        'type' => $listing->inventory_type,
                        'market_hash_name' => $listing->market_hash_name,
                        'steam_asset_id' => $listing->steam_asset_id,
                    ],
                    'price' => (float) $listing->price,
                    'wear_name' => $listing->wear_name,
                    'wear_value' => (float) $listing->wear_value,
                    'is_stattrak' => $listing->is_stattrak,
                    'is_souvenir' => $listing->is_souvenir,
                    'seller_id' => $listing->seller_id,
                    'seller' => [
                        'id' => $listing->seller_id,
                        'name' => $listing->seller->name ?? 'Неизвестный продавец',
                    ],
                ]
            ]);

            // Создаем заказ
            $result = $this->createOrder($items);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Товар успешно куплен!',
                    'order' => $result['orders'][0] ?? null
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при покупке: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Создать заказы из переданных товаров
     */
    private function createOrder($items, $buyer = null, $seller = null): array
    {
        try {
            // Определяем участников сделки
            $buyerClient = $buyer ?: auth('client')->user();
            
            // Валидируем все условия ДО создания заказа
            $this->validateBuyerTradeUrl($buyerClient);
            $this->validateItemsAvailability($items);
            
            // Считаем общую сумму всех заказов
            $totalAmount = $items->sum('price');

            DB::beginTransaction();

            try {
                // Сначала списываем общую сумму атомарно
                if (!$buyerClient->debit($totalAmount)) {
                    throw new \Exception('Недостаточно средств на балансе для оплаты заказа');
                }
                
                // Группируем товары по продавцам
                $itemsBySeller = $items->groupBy('seller_id');
                $createdOrders = [];

                foreach ($itemsBySeller as $sellerId => $sellerItems) {
                    $sellerTotal = $sellerItems->sum('price');

                    // Создаем заказ сразу оплаченным
                    $order = Order::create([
                        'order_number' => Order::generateOrderNumber(),
                        'buyer_id' => $buyerClient->id,
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

                DB::commit();

                return [
                    'success' => true,
                    'orders' => $createdOrders
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
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
     * Получить продажи пользователя
     */
    public function getMySales(): JsonResponse
    {
        if (!auth('client')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться'
            ], 401);
        }

        try {
            $orders = Order::with(['buyer:id,name,steam_id', 'tradeOffer.statusHistory'])
                ->where('seller_id', auth('client')->id())
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении продаж: ' . $e->getMessage()
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
    private function validateBuyerTradeUrl($client = null): void
    {
        $client = $client ?: auth('client')->user();

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

    /**
     * Создание заказа для выигранного аукциона
     */
    public function auctionBuy($auction, $winner, $seller)
    {
        try {
            // Валидируем Trade URL победителя
            $this->validateBuyerTradeUrl($winner);
            
            // Проверяем доступность листинга
            $listing = \App\Models\Listing::find($auction->listing_id);
            if (!$listing || !$listing->isActive()) {
                throw new \Exception('Товар больше не доступен');
            }
            
            // Формируем данные для заказа
            $items = collect([
                [
                    'listing_id' => $auction->listing_id,
                    'item' => [
                        'name' => $listing->inventory_item_name,
                        'image_url' => $listing->inventory_icon_url ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $listing->inventory_icon_url : null,
                        'type' => $listing->inventory_type,
                        'market_hash_name' => $listing->market_hash_name,
                        'steam_asset_id' => $listing->steam_asset_id,
                    ],
                    'price' => (float) $auction->current_price,
                    'wear_name' => $listing->wear_name,
                    'wear_value' => (float) $listing->wear_value,
                    'is_stattrak' => $listing->is_stattrak,
                    'is_souvenir' => $listing->is_souvenir,
                    'seller_id' => $auction->seller_id,
                    'seller' => [
                        'id' => $auction->seller_id,
                        'name' => $seller->name ?? 'Неизвестный продавец',
                    ],
                ]
            ]);
            
            // Используем транзакцию для атомарности операций
            DB::beginTransaction();
            
            try {
                // Средства уже были списаны при ставке, нужно вернуть их перед созданием заказа
                // чтобы метод createOrder мог их списать снова (так работает его логика)
                $winner->credit($auction->current_price);
                
                // Теперь создаем заказ через существующий метод
                $result = $this->createOrder($items, $winner, $seller);
                
                if ($result['success']) {
                    DB::commit();
                    return $result['orders'][0] ?? null;
                }
                
                // Если заказ не создался, откатываем транзакцию
                // Это автоматически отменит возврат средств
                DB::rollBack();
                throw new \Exception($result['message'] ?? 'Не удалось создать заказ');
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Создание заказа для приза из кейса
     */
    public function casePrizeBuy($case, $prizeItem, $buyer)
    {
        try {
            // Валидируем Trade URL покупателя
            $this->validateBuyerTradeUrl($buyer);
            
            // Получаем владельца предмета
            $seller = \App\Models\Client::find($prizeItem->client_id);
            if (!$seller) {
                throw new \Exception('Владелец предмета не найден');
            }
            
            // Формируем данные для заказа
            $items = collect([
                [
                    'listing_id' => null, // Для призов кейсов листинга нет
                    'case_id' => $case->id, // Добавляем ID кейса для отслеживания
                    'item' => [
                        'name' => $prizeItem->item_name,
                        'image_url' => $prizeItem->icon_url ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $prizeItem->icon_url : null,
                        'type' => 'case_prize',
                        'market_hash_name' => $prizeItem->market_hash_name ?? $prizeItem->item_name,
                        'steam_asset_id' => $prizeItem->steam_asset_id,
                    ],
                    'price' => (float) $case->price, // Стоимость кейса
                    'actual_prize_value' => (float) ($prizeItem->getCurrentPrice() ?: 0), // Реальная стоимость приза
                    'wear_name' => $prizeItem->getWearConditionAttribute() ?? null,
                    'wear_value' => (float) $prizeItem->float_value ?? 0,
                    'is_stattrak' => $prizeItem->is_stattrak ?? false,
                    'is_souvenir' => $prizeItem->is_souvenir ?? false,
                    'seller_id' => $prizeItem->client_id, // Владелец предмета (бот)
                    'seller' => [
                        'id' => $prizeItem->client_id,
                        'name' => $seller->name,
                    ],
                ]
            ]);
            
            // Используем транзакцию для атомарности операций
            DB::beginTransaction();
            
            try {
                // Создаем заказ напрямую без списания средств (они уже списаны)
                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'buyer_id' => $buyer->id,
                    'seller_id' => $prizeItem->client_id, // Владелец предмета
                    'total_amount' => (float) $case->price, // Стоимость кейса
                    'cart_snapshot' => $items->toArray(),
                    'status' => Order::STATUS_PROCESSING,
                    'payment_status' => Order::PAYMENT_STATUS_PAID,
                    'paid_at' => now(),
                    'payment_transaction_id' => 'CASE_PRIZE_' . $case->id . '_' . uniqid(),
                    'payment_method' => 'case_prize',
                    'notes' => 'Приз из кейса "' . $case->name . '"',
                ]);

                DB::commit();

                return $order;
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Быстрая продажа предмета боту
     */
    public function quickSell(Request $request): JsonResponse
    {
        if (!auth('client')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходимо авторизоваться для продажи'
            ], 401);
        }

        // Проверяем активную сессию расширения
        $client = auth('client')->user();
        $sessionCache = new \App\Services\Steam\SessionCache();
        if (!$sessionCache->isActive($client->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Расширение не запущено или сессия истекла. Запустите расширение и попробуйте снова'
            ], 400);
        }

        $request->validate([
            'asset_id' => 'required|string'
        ]);

        try {

            // Проверяем Trade URL
            if (empty($client->steam_trade_url)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Необходимо настроить Trade URL в профиле'
                ], 400);
            }

            // Находим предмет в инвентаре пользователя
            $item = \App\Models\ClientInventoryItem::where('client_id', $client->id)
                ->where('steam_asset_id', $request->asset_id)
                ->where('tradable', true)
                ->where('marketable', true)
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Предмет не найден или недоступен для продажи'
                ], 404);
            }

            // Проверяем существующий листинг для этого предмета
            $existingListing = \App\Models\Listing::where('steam_asset_id', $item->steam_asset_id)
                ->where('seller_id', $client->id)
                ->whereIn('status', ['pending', 'active'])
                ->first();

            // Рассчитываем цену выкупа
            $buyoutPriceUSD = $item->calculateBuyoutPrice();
            if (!$buyoutPriceUSD || $buyoutPriceUSD <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Предмет не принимается для быстрой продажи'
                ], 400);
            }

            // Конвертируем в рубли
            $buyoutPriceRUB = \App\Models\Currency::convert($buyoutPriceUSD, 'USD', 'RUB');

            // Получаем доступного бота
            $botService = new \App\Services\BotRotationService();
            $bot = $botService->getNextAvailableBot($buyoutPriceRUB);

            if (!$bot) {
                return response()->json([
                    'success' => false,
                    'message' => 'В данный момент нет доступных ботов для покупки'
                ], 503);
            }

            DB::beginTransaction();

            try {
                // Используем существующий листинг или создаем новый
                if ($existingListing) {
                    $listing = $existingListing;
                    $listing->status = 'active';
                    $listing->save();
                } else {
                    // Создаем листинг через InventoryController
                    $inventoryController = new \App\Http\Controllers\InventoryController();
                    $createRequest = new Request(['steam_asset_id' => $item->steam_asset_id]);
                    $createResult = $inventoryController->createListing($createRequest);

                    if ($createResult->getStatusCode() !== 200) {
                        DB::rollBack();
                        $errorData = json_decode($createResult->getContent(), true);
                        return response()->json([
                            'success' => false,
                            'message' => $errorData['message'] ?? 'Ошибка создания листинга'
                        ], 400);
                    }

                    // Получаем созданный листинг
                    $resultData = json_decode($createResult->getContent(), true);
                    $listing = \App\Models\Listing::find($resultData['data']['listing_id']);
                }
                // Обновляем цену и статус для покупки ботом
                $listing->price = $buyoutPriceRUB;
                $listing->currency = 'RUB';
                $listing->status = 'active';
                $listing->save();

                // Создаем заказ используя существующую логику с ботом как покупателем
                $items = collect([
                    [
                        'listing_id' => $listing->id,
                        'item' => [
                            'name' => $listing->inventory_item_name,
                            'image_url' => $listing->inventory_icon_url ? 'https://steamcommunity-a.akamaihd.net/economy/image/' . $listing->inventory_icon_url : null,
                            'market_hash_name' => $listing->market_hash_name,
                            'steam_asset_id' => $listing->steam_asset_id,
                        ],
                        'price' => (float) $listing->price,
                        'wear_name' => $listing->wear_condition,
                        'wear_value' => (float) $listing->wear_value,
                        'is_stattrak' => $listing->is_stattrak,
                        'is_souvenir' => $listing->is_souvenir,
                        'seller_id' => $listing->seller_id,
                        'seller' => [
                            'id' => $listing->seller_id,
                            'name' => $client->name,
                        ],
                    ]
                ]);

                $result = $this->createOrder($items, $bot, $client);

                if ($result['success']) {
                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Предмет успешно продан боту!',
                        'order' => $result['orders'][0] ?? null
                    ]);
                } else {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Ошибка при покупке ботом'
                    ], 400);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Quick sell error', [
                'client_id' => auth('client')->id(),
                'asset_id' => $request->asset_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при продаже предмета'
            ], 500);
        }
    }
}
