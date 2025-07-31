<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Models\TradeOffer;
use App\Models\Listing;
use App\Models\Transaction;

echo "=== Очистка заказов и связанных данных ===\n\n";

// Подсчитываем текущие данные
$ordersCount = Order::count();
$tradeOffersCount = TradeOffer::count();
$transactionsCount = Transaction::count();
$reservedListings = Listing::where('reserved_by_order_id', '!=', null)->count();

echo "Найдено для удаления:\n";
echo "- Заказов: $ordersCount\n";
echo "- TradeOffers: $tradeOffersCount\n";
echo "- Транзакций: $transactionsCount\n";
echo "- Зарезервированных листингов: $reservedListings\n\n";

// Сначала отменяем все заказы (это автоматически освободит листинги)
echo "Отмена активных заказов...\n";
$activeOrders = Order::whereIn('status', ['paid', 'processing'])->get();
foreach ($activeOrders as $order) {
    $order->cancel('Системная очистка данных');
}
echo "✓ {$activeOrders->count()} активных заказов отменено\n";

// Теперь очищаем таблицы
echo "Удаление данных...\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0');

DB::table('trade_offers')->truncate();
echo "✓ TradeOffers удалены\n";

DB::table('orders')->truncate();
echo "✓ Orders удалены\n";

DB::table('transactions')->truncate();
echo "✓ Transactions удалены\n";

DB::statement('SET FOREIGN_KEY_CHECKS=1');

// Проверяем что все листинги освобождены
$stillReserved = Listing::where('reserved_by_order_id', '!=', null)->count();
if ($stillReserved > 0) {
    echo "⚠ Найдено $stillReserved зарезервированных листингов, освобождаем принудительно...\n";
    Listing::where('reserved_by_order_id', '!=', null)->update([
        'status' => 'active',
        'reserved_by_order_id' => null
    ]);
    echo "✓ Листинги освобождены принудительно\n";
} else {
    echo "✓ Все листинги уже освобождены\n";
}

echo "=== Очистка завершена! ===\n";