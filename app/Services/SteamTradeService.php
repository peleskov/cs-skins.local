<?php

namespace App\Services;

use App\Models\TradeOffer;
use App\Models\Client;
use App\Events\ExtensionEvents;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SteamTradeService
{
    private SteamSessionCache $sessionCache;

    public function __construct(SteamSessionCache $sessionCache)
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

        $sessionData = $this->sessionCache->get($seller->id);
        
        if (!$sessionData) {
            throw new Exception('No cached Steam session available for seller');
        }

        // Подготавливаем данные для Steam Community (точно как в Node.js)
        $offerData = $this->buildOfferData($tradeOffer->asset_ids);
        $params = $this->buildTradeParams($tradeOffer->buyer_trade_url);
        
        $formData = [
            'sessionid' => $sessionData['sessionid'],
            'serverid' => 1,
            'partner' => $buyer->steam_id,
            'tradeoffermessage' => $tradeOffer->message ?? '',
            'json_tradeoffer' => json_encode($offerData),
            'captcha' => '',
            'trade_offer_create_params' => json_encode($params),
            'tradeofferid_countered' => ''
        ];

        // Формируем referer как в Node.js
        $token = $this->extractTokenFromTradeUrl($tradeOffer->buyer_trade_url);
        $referer = "https://steamcommunity.com/tradeoffer/new/?partner={$buyer->getAccountId()}" . 
                   ($token ? "&token={$token}" : '');

        Log::info('Creating Steam trade offer', [
            'trade_offer_id' => $tradeOffer->id,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'asset_count' => count($tradeOffer->asset_ids)
        ]);

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
     * Отмена трейд оффера (копируем логику из Node.js)
     */
    public function cancelTradeOffer(TradeOffer $tradeOffer): array
    {
        if (!$tradeOffer->steam_trade_offer_id) {
            throw new Exception("Cannot cancel an unsent offer");
        }

        if (!in_array($tradeOffer->status, [TradeOffer::STATUS_SENT, TradeOffer::STATUS_PENDING])) {
            throw new Exception("Offer #{$tradeOffer->steam_trade_offer_id} is not active, so it may not be cancelled");
        }

        $seller = Client::find($tradeOffer->seller_id);
        if (!$seller) {
            throw new Exception('Seller not found');
        }

        $sessionData = $this->sessionCache->get($seller->id);
        
        if (!$sessionData) {
            throw new Exception('No cached Steam session available for seller');
        }

        $buyer = Client::find($tradeOffer->buyer_id);
        $token = $this->extractTokenFromTradeUrl($tradeOffer->buyer_trade_url);
        $referer = "https://steamcommunity.com/tradeoffer/{$tradeOffer->steam_trade_offer_id}/?partner={$buyer->getAccountId()}" . 
                   ($token ? "&token={$token}" : '');

        Log::info('Canceling Steam trade offer', [
            'trade_offer_id' => $tradeOffer->id,
            'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id
        ]);

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
            $this->parseSteamError($body['strError']);
            throw new Exception($body['strError']);
        }

        if (!isset($body['tradeofferid'])) {
            throw new Exception('Unknown response');
        }

        // Обновляем трейд оффер
        $tradeOffer->update([
            'steam_trade_offer_id' => $body['tradeofferid'],
            'status' => TradeOffer::STATUS_SENT,
            'steam_response' => $body
        ]);

        Log::info('Steam trade offer created successfully', [
            'trade_offer_id' => $tradeOffer->id,
            'steam_trade_offer_id' => $body['tradeofferid']
        ]);

        return [
            'success' => true,
            'steam_trade_offer_id' => $body['tradeofferid'],
            'status' => 'sent'
        ];
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
            $this->parseSteamError($body['strError']);
            throw new Exception($body['strError']);
        }

        // Обновляем статус трейда
        $tradeOffer->update([
            'status' => TradeOffer::STATUS_CANCELLED
        ]);

        Log::info('Steam trade offer canceled successfully', [
            'trade_offer_id' => $tradeOffer->id,
            'steam_trade_offer_id' => $tradeOffer->steam_trade_offer_id
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
}