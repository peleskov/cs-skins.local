<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Client extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'steam_id',
        'steam_avatar',
        'steam_trade_url',
        'balance',
        'payment_password',
        'is_verified',
        'is_bot',
        'locale',
        'email_verified_at',
        'email_verification_sent_at',
    ];

    protected $hidden = [
        'payment_password',
        'remember_token',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_verified' => 'boolean',
        'is_bot' => 'boolean',
        'email_verified_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
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
}
