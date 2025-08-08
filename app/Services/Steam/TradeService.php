<?php

namespace App\Services\Steam;

use App\Models\TradeOffer;
use App\Models\TradeOfferStatusHistory;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class TradeService
{
    private SessionCache $sessionCache;

    public function __construct(SessionCache $sessionCache)
    {
        $this->sessionCache = $sessionCache;
    }

    // Статусы ошибок Steam (копируем из Node.js версии)
    public const ERROR_TRADE_BAN = 'TradeBan';
    public const ERROR_NEW_DEVICE = 'NewDevice';
    public const ERROR_TARGET_CANNOT_TRADE = 'TargetCannotTrade';
    public const ERROR_OFFER_LIMIT_EXCEEDED = 'OfferLimitExceeded';
    public const ERROR_ITEM_SERVER_UNAVAILABLE = 'ItemServerUnavailable';
    public const ERROR_NOT_LOGGED_IN = 'NotLoggedIn';

    /**
     * Создание трейд оффера через Steam Community (копируем логику из Node.js)
     */
    public function createTradeOffer(TradeOffer $tradeOffer): array
    {
        if ($tradeOffer->steam_trade_offer_id) {
            throw new Exception("This offer has already been sent");
        }

        if (empty($tradeOffer->asset_ids)) {
            throw new Exception("Cannot send an empty trade offer");
        }

        $seller = Client::find($tradeOffer->seller_id);
        $buyer = Client::find($tradeOffer->buyer_id);

        if (!$seller || !$buyer) {
            throw new Exception('Seller or buyer not found');
        }

        if (empty($buyer->steam_trade_url)) {
            throw new Exception('Buyer trade URL not found');
        }

        $sessionData = $this->sessionCache->get($seller->id);

        if (!$sessionData) {
            throw new Exception('No cached Steam session available for seller');
        }

        // Подготавливаем данные для Steam Community (точно как в Node.js)
        $offerData = $this->buildOfferData($tradeOffer->asset_ids);
        $params = $this->buildTradeParams($buyer->steam_trade_url);

        $formData = [
            'sessionid' => $sessionData['sessionid'],
            'serverid' => 1,
            'partner' => $buyer->steam_id,
            'tradeoffermessage' => '',
            'json_tradeoffer' => json_encode($offerData),
            'captcha' => '',
            'trade_offer_create_params' => json_encode($params),
            'tradeofferid_countered' => ''
        ];

        // Формируем referer как в Node.js
        $token = $this->extractTokenFromTradeUrl($buyer->steam_trade_url);
        $referer = "https://steamcommunity.com/tradeoffer/new/?partner={$buyer->getAccountId()}" .
            ($token ? "&token={$token}" : '');

        /*
        Log::info('Creating Steam trade offer', [
            'trade_offer_id' => $tradeOffer->id,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'asset_count' => count($tradeOffer->asset_ids)
        ]);
        */

        // Делаем запрос к Steam Community с cookies сессии
        $response = Http::withHeaders([
            'Referer' => $referer,
            'Cookie' => $this->buildCookieHeader($sessionData)
        ])
            ->timeout(30)
            ->asForm()
            ->post('https://steamcommunity.com/tradeoffer/new/send', $formData);

        return $this->handleTradeOfferResponse($response, $tradeOffer);
    }

    /**
     * Отмена трейд оффера через Steam API
     */
    public function cancelTradeOffer(TradeOffer $tradeOffer): array
    {
        if (!$tradeOffer->steam_trade_offer_id) {
            throw new Exception("Cannot cancel an unsent offer");
        }

        $seller = Client::find($tradeOffer->seller_id);
        if (!$seller) {
            throw new Exception('Seller not found');
        }

        $sessionData = $this->sessionCache->get($seller->id);

        if (!$sessionData) {
            throw new Exception('No cached Steam session available for seller');
        }

        // Сначала проверяем текущий статус трейда
        try {
            $currentStatus = $this->checkTradeStatus($tradeOffer->steam_trade_offer_id, $tradeOffer->seller_id);
            $tradeOffer->update(['status' => $currentStatus]);

            // Проверяем можно ли отменить
            $cancelableStatuses = ['Active', 'CreatedNeedsConfirmation'];

            if (!in_array($currentStatus, $cancelableStatuses)) {
                Log::channel('steam_api')->warning('Trade offer cannot be cancelled in current status', [
                    'trade_offer_id' => $tradeOffer->id,
                    'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id,
                    'current_status' => $currentStatus
                ]);

                return [
                    'success' => false,
                    'status' => $currentStatus,
                    'message' => "Cannot cancel trade in status: {$currentStatus}"
                ];
            }
        } catch (Exception $e) {
            Log::channel('steam_api')->error('Failed to check trade status before cancel', [
                'trade_offer_id' => $tradeOffer->id,
                'error' => $e->getMessage()
            ]);
            // Продолжаем попытку отмены даже если не смогли проверить статус
        }

        $buyer = Client::find($tradeOffer->buyer_id);
        $token = $this->extractTokenFromTradeUrl($buyer->steam_trade_url);
        $referer = "https://steamcommunity.com/tradeoffer/{$tradeOffer->steam_trade_offer_id}/?partner={$buyer->getAccountId()}" .
            ($token ? "&token={$token}" : '');

        $response = Http::withHeaders([
            'Referer' => $referer,
            'Cookie' => $this->buildCookieHeader($sessionData)
        ])
            ->timeout(15)
            ->asForm()
            ->post("https://steamcommunity.com/tradeoffer/{$tradeOffer->steam_trade_offer_id}/cancel", [
                'sessionid' => $sessionData['sessionid']
            ]);

        return $this->handleCancelResponse($response, $tradeOffer);
    }


    /**
     * Построение структуры данных оффера (точно как в Node.js)
     */
    private function buildOfferData(array $assetIds): array
    {
        $assets = [];

        foreach ($assetIds as $assetId) {
            if (strpos($assetId, '_') !== false) {
                [$contextId, $actualAssetId] = explode('_', $assetId, 2);
            } else {
                $contextId = '2'; // CS2 context
                $actualAssetId = $assetId;
            }

            $assets[] = [
                'appid' => 730, // CS2
                'contextid' => $contextId,
                'amount' => 1,
                'assetid' => $actualAssetId
            ];
        }

        return [
            'newversion' => true,
            'version' => count($assets) + 1,
            'me' => [
                'assets' => $assets,
                'currency' => [],
                'ready' => false
            ],
            'them' => [
                'assets' => [],
                'currency' => [],
                'ready' => false
            ]
        ];
    }

    /**
     * Построение параметров трейда
     */
    private function buildTradeParams(string $tradeUrl): array
    {
        $params = [];

        $token = $this->extractTokenFromTradeUrl($tradeUrl);
        if ($token) {
            $params['trade_offer_access_token'] = $token;
        }

        return $params;
    }

    /**
     * Извлечение токена из trade URL
     */
    private function extractTokenFromTradeUrl(string $tradeUrl): ?string
    {
        if (preg_match('/[?&]token=([a-zA-Z0-9_-]+)/', $tradeUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Построение заголовка Cookie из данных сессии
     */
    private function buildCookieHeader(array $sessionData): string
    {
        $cookies = [];

        if (isset($sessionData['sessionid'])) {
            $cookies[] = "sessionid={$sessionData['sessionid']}";
        }

        if (isset($sessionData['steamLoginSecure'])) {
            $cookies[] = "steamLoginSecure={$sessionData['steamLoginSecure']}";
        }

        return implode('; ', $cookies);
    }

    /**
     * Обработка ответа от Steam при создании трейда (копируем логику из Node.js)
     */
    private function handleTradeOfferResponse($response, TradeOffer $tradeOffer): array
    {
        if ($response->status() === 401) {
            $this->handleSteamError(self::ERROR_NOT_LOGGED_IN, 'HTTP error 401');
            throw new Exception('Not Logged In');
        }

        if ($response->status() !== 200) {
            throw new Exception("HTTP error {$response->status()}");
        }

        $body = $response->json();

        if (!$body) {
            throw new Exception('Malformed JSON response');
        }

        if (isset($body['strError'])) {
            Log::channel('steam_api')->error('Steam trade offer error', [
                'trade_offer_id' => $tradeOffer->id,
                'error_message' => $body['strError'],
                'response_body' => $body
            ]);

            $this->parseSteamError($body['strError']);
            throw new Exception($body['strError']);
        }

        if (!isset($body['tradeofferid'])) {
            Log::channel('steam_api')->error('Steam response missing tradeofferid', [
                'trade_offer_id' => $tradeOffer->id,
                'response_body' => $body
            ]);
            throw new Exception('Unknown response');
        }

        // Определяем статус на основе ответа Steam
        $steamStatus = 'Active'; // По умолчанию Active

        if (isset($body['needs_email_confirmation']) && $body['needs_email_confirmation']) {
            $steamStatus = 'CreatedNeedsConfirmation';
        }

        if (isset($body['needs_mobile_confirmation']) && $body['needs_mobile_confirmation']) {
            $steamStatus = 'CreatedNeedsConfirmation';
        }

        $tradeOffer->steam_trade_offer_id = $body['tradeofferid'];
        $tradeOffer->status = $steamStatus;
        $tradeOffer->save();

        /*
        Log::info('Steam trade offer created successfully', [
            'trade_offer_id' => $tradeOffer->id,
            'steam_trade_offer_id' => $body['tradeofferid'],
            'steam_status' => $steamStatus
        ]);
        */

        // Определяем результат для Order
        if ($steamStatus === 'Active') {
            return ['success' => true, 'message' => 'Успех'];
        }

        if ($steamStatus === 'CreatedNeedsConfirmation') {
            return ['success' => true, 'message' => 'Успех, ждем подтверждения продавца'];
        }

        // Все остальные статусы (не должно случиться при создании)
        Log::channel('steam_api')->warning('Unexpected Steam status during trade creation', [
            'status' => $steamStatus,
            'trade_offer_id' => $tradeOffer->id,
            'response_body' => $body
        ]);

        return ['success' => false, 'message' => 'Продавец недоступен или достиг лимита на трейды'];
    }

    /**
     * Обработка ответа при отмене трейда
     */
    private function handleCancelResponse($response, TradeOffer $tradeOffer): array
    {
        if ($response->status() === 401) {
            $this->handleSteamError(self::ERROR_NOT_LOGGED_IN, 'HTTP error 401');
            throw new Exception('Not Logged In');
        }

        if ($response->status() !== 200) {
            throw new Exception("HTTP error {$response->status()}");
        }

        $body = $response->json();

        if (!$body) {
            throw new Exception('Malformed JSON response');
        }

        if (isset($body['strError'])) {
            Log::channel('steam_api')->error('Steam cancel trade offer error', [
                'trade_offer_id' => $tradeOffer->id,
                'error_message' => $body['strError'],
                'response_body' => $body
            ]);

            $this->parseSteamError($body['strError']);
            throw new Exception($body['strError']);
        }

        // Обновляем статус трейда
        $tradeOffer->update([
            'status' => TradeOffer::STATUS_CANCELED
        ]);

        return [
            'success' => true,
            'status' => 'canceled'
        ];
    }

    /**
     * Парсинг специфичных ошибок Steam (копируем из Node.js)
     */
    private function parseSteamError(string $errorMessage): ?string
    {
        if (preg_match('/You cannot trade with .* because they have a trade ban\./', $errorMessage)) {
            return $this->handleSteamError(self::ERROR_TRADE_BAN, $errorMessage);
        }

        if (preg_match('/You have logged in from a new device/', $errorMessage)) {
            return $this->handleSteamError(self::ERROR_NEW_DEVICE, $errorMessage);
        }

        if (preg_match('/is not available to trade\. More information will be shown to/', $errorMessage)) {
            return $this->handleSteamError(self::ERROR_TARGET_CANNOT_TRADE, $errorMessage);
        }

        if (preg_match('/sent too many trade offers/', $errorMessage)) {
            return $this->handleSteamError(self::ERROR_OFFER_LIMIT_EXCEEDED, $errorMessage);
        }

        if (preg_match('/unable to contact the game\'s item server/', $errorMessage)) {
            return $this->handleSteamError(self::ERROR_ITEM_SERVER_UNAVAILABLE, $errorMessage);
        }

        return null;
    }

    /**
     * Обработка специфичных ошибок Steam
     */
    private function handleSteamError(string $errorType, string $message): string
    {
        Log::warning("Steam trade error: {$errorType}", [
            'error_type' => $errorType,
            'message' => $message
        ]);

        return $errorType;
    }

    /**
     * Проверка статуса трейд оффера (копируем логику из Node.js steam-tradeoffer-manager)
     */
    public function checkTradeStatus(string $steamTradeOfferId, int $sellerId): string
    {
        $seller = Client::find($sellerId);
        if (!$seller) {
            throw new Exception('Seller not found');
        }

        $sessionData = $this->sessionCache->get($seller->id);

        if (!$sessionData) {
            throw new Exception('No cached Steam session available for seller');
        }

        Log::info('Checking Steam trade offer status', [
            'steam_trade_offer_id' => $steamTradeOfferId,
            'seller_id' => $sellerId
        ]);

        // Извлекаем access_token из steamLoginSecure cookie (как в steam-tradeoffer-manager)
        $accessToken = $this->extractAccessTokenFromSession($sessionData);

        if (!$accessToken) {
            throw new Exception('Access token not found in session data');
        }

        // Используем Steam Web API как в steam-tradeoffer-manager
        $response = Http::timeout(15)
            ->get('https://api.steampowered.com/IEconService/GetTradeOffer/v1/', [
                'access_token' => $accessToken,
                'tradeofferid' => $steamTradeOfferId,
                'language' => 'english'
            ]);

        if ($response->status() !== 200) {
            throw new Exception("HTTP error {$response->status()}");
        }

        $body = $response->json();

        if (!$body || !isset($body['response'])) {
            throw new Exception('Malformed API response');
        }

        if (!isset($body['response']['offer'])) {
            throw new Exception('No matching offer found');
        }

        $offer = $body['response']['offer'];
        $status = $this->mapTradeOfferState($offer['trade_offer_state']);

        Log::info('Steam trade offer status retrieved', [
            'steam_trade_offer_id' => $steamTradeOfferId,
            'status' => $status,
            'raw_state' => $offer['trade_offer_state']
        ]);

        return $status;
    }

    public function updateTradeStatuses(int $sellerId, array $steamTrades): void
    {
        if (empty($steamTrades)) {
            return;
        }

        $cacheKey = "trade_statuses_{$sellerId}";
        $cacheTtl = (int) env('MAX_RESERVATION_TIME_MINUTES', 120) * 60;
        $cachedStatuses = Cache::get($cacheKey, []);

        $hasChanges = false;

        foreach ($steamTrades as $steamTrade) {
            $steamTradeOfferId = $steamTrade['trade_offer_id'] ?? null;
            $newStatus = $steamTrade['status'] ?? null;

            if (!$steamTradeOfferId || $newStatus === null) {
                continue;
            }

            $oldStatus = $cachedStatuses[$steamTradeOfferId] ?? null;

            if ($oldStatus !== $newStatus) {
                $tradeOffer = TradeOffer::where('steam_trade_offer_id', $steamTradeOfferId)
                    ->where('seller_id', $sellerId)
                    ->first();

                if (!$tradeOffer) {
                    continue;
                }

                $newStatusText = $this->mapTradeOfferState($newStatus);
                $tradeOffer->update(['status' => $newStatusText]);

                $cachedStatuses[$steamTradeOfferId] = $newStatus;
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            Cache::put($cacheKey, $cachedStatuses, $cacheTtl);
        }
    }

    /**
     * Извлечение access_token из steamLoginSecure cookie (как в steam-tradeoffer-manager)
     */
    private function extractAccessTokenFromSession(array $sessionData): ?string
    {
        if (!isset($sessionData['steamLoginSecure'])) {
            return null;
        }

        $cookieValue = urldecode($sessionData['steamLoginSecure']);
        $parts = explode('||', $cookieValue);

        if (count($parts) < 2) {
            return null;
        }

        return $parts[1]; // access_token - вторая часть после ||
    }


    /**
     * Маппинг числовых статусов Steam на текстовые (из steam-tradeoffer-manager)
     */
    private function mapTradeOfferState(int $state): string
    {
        // Константы из steam-tradeoffer-manager ETradeOfferState
        switch ($state) {
            case 1:
                return 'Invalid';
            case 2:
                return 'Active';
            case 3:
                return 'Accepted';
            case 4:
                return 'Countered';
            case 5:
                return 'Expired';
            case 6:
                return 'Canceled';
            case 7:
                return 'Declined';
            case 8:
                return 'InvalidItems';
            case 9:
                return 'CreatedNeedsConfirmation';
            case 10:
                return 'CanceledBySecondFactor';
            case 11:
                return 'InEscrow';
            default:
                return 'Unknown';
        }
    }
}
