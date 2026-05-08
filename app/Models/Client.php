<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use App\Models\Favorite;

class Client extends Authenticatable
{
    use Notifiable;

    protected $attributes = [
        'notification_settings' => '["toast", "telegram"]',
    ];

    use \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['balance', 'bonus_balance', 'is_verified', 'withdraw_blocked'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'name',
        'email',
        'steam_id',
        'steam_avatar',
        'steam_trade_url',
        'balance',
        'bonus_balance',
        'payment_password',
        'is_verified',
        'is_bot',
        'locale',
        'email_verified_at',
        'email_verification_sent_at',
        'telegram_id',
        'telegram_username',
        'verification_code',
        'verification_expires_at',
        'extension_token',
        'extension_token_generated_at',
        'notification_settings',
        'trial_used',
        'pin_code',
        'pin_verified_at',
        'avatar_border_color',
        'nickname_color',
        'withdraw_blocked',
    ];

    protected $hidden = [
        'payment_password',
        'remember_token',
        'extension_token',
        'pin_code',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'bonus_balance' => 'decimal:2',
        'is_verified' => 'boolean',
        'is_bot' => 'boolean',
        'email_verified_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'extension_token_generated_at' => 'datetime',
        'notification_settings' => 'array',
        'trial_used' => 'boolean',
        'pin_verified_at' => 'datetime',
        'withdraw_blocked' => 'boolean',
    ];

    /**
     * Проверка, верифицирован ли email
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Можно ли отправить письмо повторно (прошло 1 минута)
     */
    public function canResendVerificationEmail()
    {
        if (!$this->email_verification_sent_at) {
            return true;
        }

        return $this->secondsUntilCanResend() <= 0;
    }

    /**
     * Получить время до возможности повторной отправки в секундах
     */
    public function secondsUntilCanResend()
    {
        if (!$this->email_verification_sent_at) {
            return 0;
        }

        $secondsSinceLastSent = $this->email_verification_sent_at->diffInSeconds(now());
        
        // Возвращаем остаток или 0 (целое число)
        return (int) max(0, 60 - $secondsSinceLastSent);
    }
    
    /**
     * Получить отформатированное время до повторной отправки
     */
    public function formattedTimeUntilCanResend()
    {
        $seconds = $this->secondsUntilCanResend();
        
        if ($seconds <= 0) {
            return '0 сек';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes > 0) {
            return $minutes . ' мин ' . $remainingSeconds . ' сек';
        }
        
        return $remainingSeconds . ' сек';
    }

    /**
     * Извлечь Steam ID64 из Trade URL
     */
    public static function extractSteamIdFromTradeUrl($tradeUrl)
    {
        if (!$tradeUrl) {
            return null;
        }

        // Паттерн для Steam Trade URL
        $pattern = '/steamcommunity\.com\/tradeoffer\/new\/\?partner=(\d+)/';
        
        if (preg_match($pattern, $tradeUrl, $matches)) {
            $steamId32 = $matches[1];
            // Конвертируем Steam ID32 в Steam ID64
            return self::convertSteamId32ToId64($steamId32);
        }
        
        return null;
    }

    /**
     * Конвертировать Steam ID32 в Steam ID64
     */
    public static function convertSteamId32ToId64($steamId32)
    {
        return (string)((int)$steamId32 + 76561197960265728);
    }

    /**
     * Валидация Trade URL
     */
    public static function validateTradeUrl($tradeUrl, $expectedSteamId = null)
    {
        if (!$tradeUrl) {
            return ['valid' => false, 'message' => 'Trade URL не указан'];
        }

        // Проверка формата
        $pattern = '/^https:\/\/steamcommunity\.com\/tradeoffer\/new\/\?partner=\d+&token=[a-zA-Z0-9_-]+$/';
        
        if (!preg_match($pattern, $tradeUrl)) {
            return ['valid' => false, 'message' => 'Неверный формат Trade URL'];
        }

        // Извлекаем Steam ID из URL
        $extractedSteamId = self::extractSteamIdFromTradeUrl($tradeUrl);
        
        if (!$extractedSteamId) {
            return ['valid' => false, 'message' => 'Не удалось извлечь Steam ID из Trade URL'];
        }

        // Проверяем соответствие Steam ID если указан
        if ($expectedSteamId && $extractedSteamId !== $expectedSteamId) {
            return ['valid' => false, 'message' => 'Trade URL не соответствует вашему Steam ID'];
        }

        return ['valid' => true, 'steam_id' => $extractedSteamId];
    }


    public function sellingListings(): HasMany
    {
        return $this->hasMany(Listing::class, 'seller_id');
    }

    public function boughtListings(): HasMany
    {
        return $this->hasMany(Listing::class, 'buyer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isBot(): bool
    {
        return $this->is_bot;
    }

    public function getAvailableBalance(): float
    {
        return $this->balance;
    }

    public function hasEnoughBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    public function credit(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function debit(float $amount): bool
    {
        // Атомарная операция: проверка и списание в одном запросе
        $updated = self::where('id', $this->id)
            ->where('balance', '>=', $amount)
            ->decrement('balance', $amount);

        if ($updated) {
            // Обновляем локальное значение баланса
            $this->refresh();
            return true;
        }

        return false;
    }

    // ========== Бонусный баланс ==========

    /**
     * Проверка наличия бонусного баланса
     */
    public function hasBonusBalance(): bool
    {
        return $this->bonus_balance > 0;
    }

    /**
     * Получить бонусный баланс
     */
    public function getBonusBalance(): float
    {
        return (float) $this->bonus_balance;
    }

    /**
     * Получить общий баланс (основной + бонусный)
     */
    public function getTotalBalance(): float
    {
        return (float) $this->balance + (float) $this->bonus_balance;
    }

    /**
     * Проверка достаточности общего баланса (основной + бонусный)
     */
    public function hasEnoughTotalBalance(float $amount): bool
    {
        return $this->getTotalBalance() >= $amount;
    }

    /**
     * Начислить бонусный баланс
     */
    public function creditBonus(float $amount): void
    {
        $this->increment('bonus_balance', $amount);
    }

    /**
     * Списать с бонусного баланса (атомарно)
     */
    public function debitBonus(float $amount): bool
    {
        $updated = self::where('id', $this->id)
            ->where('bonus_balance', '>=', $amount)
            ->decrement('bonus_balance', $amount);

        if ($updated) {
            $this->refresh();
            return true;
        }

        return false;
    }

    /**
     * Списать средства с приоритетом бонусного баланса
     * Возвращает массив с информацией о списании
     *
     * @return array{success: bool, bonus_used: float, balance_used: float}
     */
    public function debitWithBonusPriority(float $amount): array
    {
        $bonusBalance = (float) $this->bonus_balance;
        $mainBalance = (float) $this->balance;

        // Проверяем достаточность общего баланса
        if (($bonusBalance + $mainBalance) < $amount) {
            return [
                'success' => false,
                'bonus_used' => 0,
                'balance_used' => 0,
            ];
        }

        // Рассчитываем сколько списать с каждого баланса
        $bonusToUse = min($bonusBalance, $amount);
        $balanceToUse = $amount - $bonusToUse;

        // Атомарное списание
        $updated = self::where('id', $this->id)
            ->where('bonus_balance', '>=', $bonusToUse)
            ->where('balance', '>=', $balanceToUse)
            ->update([
                'bonus_balance' => \DB::raw("bonus_balance - {$bonusToUse}"),
                'balance' => \DB::raw("balance - {$balanceToUse}"),
            ]);

        if ($updated) {
            $this->refresh();
            return [
                'success' => true,
                'bonus_used' => $bonusToUse,
                'balance_used' => $balanceToUse,
            ];
        }

        return [
            'success' => false,
            'bonus_used' => 0,
            'balance_used' => 0,
        ];
    }

    /**
     * Связь с бонусными транзакциями
     */
    public function bonusTransactions(): HasMany
    {
        return $this->hasMany(BonusTransaction::class);
    }

    /**
     * Связь с инвентарём кейсов
     */
    public function caseInventoryItems(): HasMany
    {
        return $this->hasMany(CaseInventoryItem::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'client_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(ClientInventoryItem::class);
    }


    public function tradableInventoryItems(): HasMany
    {
        return $this->inventoryItems()->tradable();
    }

    public function marketableInventoryItems(): HasMany
    {
        return $this->inventoryItems()->marketable();
    }

    /**
     * Генерирует новый токен для расширения
     */
    public function generateExtensionToken(): string
    {
        $token = 'ext_' . Str::random(60);
        
        $this->update([
            'extension_token' => $token,
            'extension_token_generated_at' => now(),
        ]);
        
        return $token;
    }

    /**
     * Проверяет наличие токена расширения
     */
    public function hasExtensionToken(): bool
    {
        return !empty($this->extension_token);
    }

    /**
     * Получает токен расширения (генерирует если нет)
     */
    public function getExtensionToken(): string
    {
        if (!$this->hasExtensionToken()) {
            return $this->generateExtensionToken();
        }
        
        return $this->extension_token;
    }

    /**
     * Регенерирует токен расширения
     */
    public function regenerateExtensionToken(): string
    {
        return $this->generateExtensionToken();
    }

    /**
     * Получить Account ID (32-битный) из Steam ID64
     */
    public function getAccountId(): string
    {
        if (!$this->steam_id) {
            return '';
        }

        return (string)((int)$this->steam_id - 76561197960265728);
    }

    /**
     * Генерирует код верификации для Telegram
     */
    public function generateTelegramVerificationCode(): string
    {
        $code = 'CODE_' . Str::upper(Str::random(8));

        $this->update([
            'verification_code' => $code,
            'verification_expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    /**
     * Проверяет, активен ли код верификации
     */
    public function hasActiveVerificationCode(): bool
    {
        return $this->verification_code
            && $this->verification_expires_at
            && $this->verification_expires_at->isFuture();
    }

    /**
     * Очищает код верификации
     */
    public function clearVerificationCode(): void
    {
        $this->update([
            'verification_code' => null,
            'verification_expires_at' => null,
        ]);
    }

    /**
     * Получить сумму средств в холде как продавец (ожидает выплаты)
     */
    public function getSellerHeldBalance(): float
    {
        return (float) Order::where('seller_id', $this->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('tradeOffer', function ($query) {
                $query->where('delay_settlement', true)
                    ->where('settlement_date', '>', now());
            })
            ->whereDoesntHave('transactions', function ($query) {
                $query->where('type', Transaction::TYPE_SALE);
            })
            ->sum('total_amount');
    }

    /**
     * Получить сумму средств в холде как покупатель (покупки на удержании)
     */
    public function getBuyerHeldBalance(): float
    {
        return (float) Order::where('buyer_id', $this->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('tradeOffer', function ($query) {
                $query->where('delay_settlement', true)
                    ->where('settlement_date', '>', now());
            })
            ->sum('total_amount');
    }

    /**
     * Получить заказы в холде как продавец
     */
    public function getSellerHeldOrders()
    {
        return Order::where('seller_id', $this->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('tradeOffer', function ($query) {
                $query->where('delay_settlement', true)
                    ->where('settlement_date', '>', now());
            })
            ->whereDoesntHave('transactions', function ($query) {
                $query->where('type', Transaction::TYPE_SALE);
            })
            ->with(['tradeOffer', 'buyer'])
            ->get();
    }

    /**
     * Получить заказы в холде как покупатель
     */
    public function getBuyerHeldOrders()
    {
        return Order::where('buyer_id', $this->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('tradeOffer', function ($query) {
                $query->where('delay_settlement', true)
                    ->where('settlement_date', '>', now());
            })
            ->with(['tradeOffer', 'seller'])
            ->get();
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class)->orderByDesc('created_at');
    }

    // ========== PREMIUM-подписка ==========

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('is_active', true)->latest();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        $subscription = $this->subscription;

        return $subscription && $subscription->isValid();
    }

    public function isPremium(): bool
    {
        return $this->hasActiveSubscription();
    }

    public function premiumFeatureEnabled(string $feature): bool
    {
        $subscription = $this->subscription;

        if (!$subscription || !$subscription->isValid()) {
            return false;
        }

        return $subscription->isFeatureEnabled($feature);
    }

    // ========== Реферальная система ==========

    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class);
    }
}
